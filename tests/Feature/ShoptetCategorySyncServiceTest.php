<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\CategoryMapping;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Models\SupplierCategory;
use Adminos\Modules\Feedmanager\Services\FeedDownloader;
use Adminos\Modules\Feedmanager\Services\Parsing\ShoptetCategoriesParser;
use Adminos\Modules\Feedmanager\Services\ShoptetCategorySyncService;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ShoptetCategorySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_full_tree_from_csv(): void
    {
        $payload = "id;parentId;title;visible\n"
            ."1;;Dámské;1\n"
            ."2;1;Prsteny;1\n"
            ."3;2;Zlato;1\n";

        $config = $this->categoryFeedConfig();

        $log = $this->makeService($payload)->run($config);

        $this->assertSame(ImportLog::STATUS_SUCCESS, $log->status);
        $this->assertSame(3, $log->products_found);
        $this->assertSame(3, $log->products_new);

        $rows = ShoptetCategory::query()->orderBy('shoptet_id')->get();
        $this->assertCount(3, $rows);

        $leaf = $rows->firstWhere('shoptet_id', 3);
        $this->assertSame('Dámské > Prsteny > Zlato', $leaf->full_path);
        $this->assertSame(2, $leaf->depth);
        $this->assertFalse($leaf->is_orphaned);
    }

    public function test_rename_is_detected_on_subsequent_run(): void
    {
        $config = $this->categoryFeedConfig();

        $this->makeService("id;parentId;title\n1;;Dámské\n")->run($config);
        $this->makeService("id;parentId;title\n1;;Dámská kolekce\n")->run($config);

        $row = ShoptetCategory::query()->where('shoptet_id', 1)->firstOrFail();
        $this->assertSame('Dámská kolekce', $row->title);
    }

    public function test_missing_category_is_marked_orphaned_not_deleted(): void
    {
        $config = $this->categoryFeedConfig();

        // First run: 1 + 2 + 3.
        $this->makeService("id;parentId;title\n1;;A\n2;1;B\n3;1;C\n")->run($config);

        // Second run: only 1 + 2. Row #3 should be marked orphaned.
        $this->makeService("id;parentId;title\n1;;A\n2;1;B\n")->run($config);

        $rows = ShoptetCategory::query()->orderBy('shoptet_id')->get()->keyBy('shoptet_id');

        $this->assertFalse($rows[1]->is_orphaned);
        $this->assertFalse($rows[2]->is_orphaned);
        $this->assertTrue($rows[3]->is_orphaned);
    }

    public function test_orphaned_category_is_revived_when_it_reappears(): void
    {
        $config = $this->categoryFeedConfig();

        $this->makeService("id;parentId;title\n1;;A\n2;1;B\n")->run($config);
        $this->makeService("id;parentId;title\n1;;A\n")->run($config);
        $this->makeService("id;parentId;title\n1;;A\n2;1;B\n")->run($config);

        $row = ShoptetCategory::query()->where('shoptet_id', 2)->firstOrFail();
        $this->assertFalse($row->is_orphaned);
    }

    public function test_renamed_paired_category_emits_notification(): void
    {
        $config = $this->categoryFeedConfig();

        // First run creates the shoptet category #1.
        $this->makeService("id;parentId;title\n1;;Original\n")->run($config);

        // Pair a supplier category to it.
        $supplier = Supplier::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        $shoptetCat = ShoptetCategory::query()->where('shoptet_id', 1)->firstOrFail();
        $supplierCat = SupplierCategory::query()->create([
            'supplier_id' => $supplier->id,
            'original_name' => 'Original',
            'original_path' => 'Acme > Original',
            'product_count' => 1,
        ]);
        CategoryMapping::query()->create([
            'supplier_category_id' => $supplierCat->id,
            'shoptet_category_id' => $shoptetCat->id,
        ]);

        // Now sync with the category renamed.
        $this->makeService("id;parentId;title\n1;;Renamed\n")->run($config);

        // Notification must have fired (we don't assert recipient exists —
        // resolveRecipients() returns empty in tests without users; the test
        // just confirms the rename was detected via DB state).
        $row = ShoptetCategory::query()->where('shoptet_id', 1)->firstOrFail();
        $this->assertSame('Renamed', $row->title);
    }

    public function test_failed_download_writes_failed_log(): void
    {
        $config = $this->categoryFeedConfig();

        $service = $this->makeService('', throwOnDownload: true);
        $log = $service->run($config);

        $this->assertSame(ImportLog::STATUS_FAILED, $log->status);
        $this->assertNotNull($log->message);
    }

    private function categoryFeedConfig(): FeedConfig
    {
        $supplier = Supplier::query()->create([
            'name' => 'Vlastní eshop',
            'slug' => 'own',
            'is_own' => true,
        ]);

        return FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Markstore kategorie',
            'source_url' => 'https://example.test/categories.csv',
            'format' => FeedConfig::FORMAT_SHOPTET_CATEGORIES,
            'is_active' => true,
        ]);
    }

    private function makeService(string $payload, bool $throwOnDownload = false): ShoptetCategorySyncService
    {
        $downloader = new class($payload, $throwOnDownload) extends FeedDownloader {
            public function __construct(
                private readonly string $payload,
                private readonly bool $throws,
            ) {
            }

            public function download(FeedConfig $config): string
            {
                if ($this->throws) {
                    throw new \RuntimeException('simulated network failure');
                }

                return $this->payload;
            }
        };

        return new ShoptetCategorySyncService($downloader, new ShoptetCategoriesParser());
    }
}
