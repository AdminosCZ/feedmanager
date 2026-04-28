<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Services\CategoryMappingService;
use Adminos\Modules\Feedmanager\Services\FeedDownloader;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Adminos\Modules\Feedmanager\Services\Parsing\FeedParserFactory;
use Adminos\Modules\Feedmanager\Services\RuleEngine\RuleEngine;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

final class FeedImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_products_from_feed(): void
    {
        $config = $this->makeConfig();
        $importer = $this->makeImporter($this->fakeFeedXml());

        $log = $importer->run($config);

        $this->assertSame(ImportLog::STATUS_SUCCESS, $log->status);
        $this->assertSame(2, $log->products_found);
        $this->assertSame(2, $log->products_new);
        $this->assertSame(0, $log->products_updated);
        $this->assertSame(0, $log->products_failed);

        $this->assertSame(2, Product::query()->where('supplier_id', $config->supplier_id)->count());
    }

    public function test_updates_existing_products_matched_by_supplier_and_code(): void
    {
        $config = $this->makeConfig();

        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => 'SKU-1',
            'name' => 'Old name',
            'price_vat' => '50.0000',
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $log = $importer->run($config);

        $this->assertSame(2, $log->products_found);
        $this->assertSame(1, $log->products_new);
        $this->assertSame(1, $log->products_updated);

        $updated = Product::query()->where('supplier_id', $config->supplier_id)
            ->where('code', 'SKU-1')->first();
        $this->assertNotNull($updated);
        $this->assertSame('Demo product 1', $updated->name);
        $this->assertSame('99.9999', $updated->price_vat);
    }

    public function test_locked_fields_are_not_overwritten(): void
    {
        $config = $this->makeConfig();

        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => 'SKU-1',
            'name' => 'Hand-edited name',
            'price_vat' => '199.0000',
            'locked_fields' => ['name', 'price_vat'],
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame('Hand-edited name', $product->name);
        $this->assertSame('199.0000', $product->price_vat);
    }

    public function test_failed_download_is_logged_as_failure(): void
    {
        $config = $this->makeConfig();
        $importer = $this->makeImporter('', throwOnDownload: true);

        $log = $importer->run($config);

        $this->assertSame(ImportLog::STATUS_FAILED, $log->status);
        $this->assertNotNull($log->message);
        $this->assertSame(0, $log->products_found);
        $this->assertSame(FeedConfig::STATUS_FAILED, $config->fresh()->last_status);
    }

    public function test_unknown_format_is_logged_as_failure(): void
    {
        $config = $this->makeConfig(['format' => 'broken-format']);
        $importer = $this->makeImporter('<SHOP/>');

        $log = $importer->run($config);

        $this->assertSame(ImportLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('Unknown feed format', (string) $log->message);
    }

    public function test_records_supplier_id_and_feed_config_id_on_created_products(): void
    {
        $config = $this->makeConfig();
        $importer = $this->makeImporter($this->fakeFeedXml());

        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertNotNull($product);
        $this->assertSame($config->supplier_id, $product->supplier_id);
        $this->assertSame($config->id, $product->feed_config_id);
        $this->assertNotNull($product->imported_at);
    }

    public function test_updates_last_run_at_and_last_status_after_success(): void
    {
        $config = $this->makeConfig();
        $importer = $this->makeImporter($this->fakeFeedXml());

        $importer->run($config);

        $config->refresh();
        $this->assertNotNull($config->last_run_at);
        $this->assertSame(FeedConfig::STATUS_SUCCESS, $config->last_status);
    }

    public function test_default_b2b_allowed_false_imports_products_blocked_for_b2b(): void
    {
        $config = $this->makeConfig(['default_b2b_allowed' => false]);
        $importer = $this->makeImporter($this->fakeFeedXml());

        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertNotNull($product);
        $this->assertFalse($product->is_b2b_allowed);
    }

    public function test_default_b2b_allowed_true_imports_products_open_for_b2b(): void
    {
        $config = $this->makeConfig(['default_b2b_allowed' => true]);
        $importer = $this->makeImporter($this->fakeFeedXml());

        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertTrue($product->is_b2b_allowed);
    }

    public function test_default_b2b_allowed_does_not_overwrite_existing_product(): void
    {
        $config = $this->makeConfig(['default_b2b_allowed' => false]);

        // Pre-existing product, admin already approved it for B2B.
        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => 'SKU-1',
            'name' => 'existing',
            'is_b2b_allowed' => true,
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertTrue($product->is_b2b_allowed, 'existing approval must survive re-import');
    }

    private function makeConfig(array $overrides = []): FeedConfig
    {
        $supplier = Supplier::query()->create([
            'name' => 'Test Supplier',
            'slug' => 'test-supplier',
        ]);

        return FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Test Feed',
            'source_url' => 'https://example.com/feed.xml',
            'format' => FeedConfig::FORMAT_HEUREKA,
            ...$overrides,
        ]);
    }

    private function fakeFeedXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo product 1</PRODUCTNAME>
    <PRICE>82.6446</PRICE>
    <PRICE_VAT>99.9999</PRICE_VAT>
    <CURRENCY>CZK</CURRENCY>
  </SHOPITEM>
  <SHOPITEM>
    <ITEM_ID>SKU-2</ITEM_ID>
    <PRODUCTNAME>Demo product 2</PRODUCTNAME>
    <PRICE>123.0000</PRICE>
    <PRICE_VAT>148.83</PRICE_VAT>
  </SHOPITEM>
</SHOP>
XML;
    }

    private function makeImporter(string $payload, bool $throwOnDownload = false): FeedImporter
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
                    throw new RuntimeException('simulated network failure');
                }
                return $this->payload;
            }
        };

        return new FeedImporter($downloader, new FeedParserFactory(), new RuleEngine(), new CategoryMappingService());
    }
}
