<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\CategoryMapping;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Models\SupplierCategory;
use Adminos\Modules\Feedmanager\Services\CategoryMappingService;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class CategoryMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_from_products_creates_supplier_categories_and_links_products(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test', 'slug' => 'test']);
        $config = FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Feed', 'source_url' => 'https://example.com',
            'format' => FeedConfig::FORMAT_HEUREKA,
        ]);

        $a = Product::query()->create([
            'supplier_id' => $supplier->id, 'feed_config_id' => $config->id,
            'code' => 'A', 'name' => 'A', 'complete_path' => 'Books > Fiction',
        ]);
        $b = Product::query()->create([
            'supplier_id' => $supplier->id, 'feed_config_id' => $config->id,
            'code' => 'B', 'name' => 'B', 'complete_path' => 'Books > Fiction',
        ]);
        $c = Product::query()->create([
            'supplier_id' => $supplier->id, 'feed_config_id' => $config->id,
            'code' => 'C', 'name' => 'C', 'complete_path' => 'Music > Vinyl',
        ]);

        (new CategoryMappingService())->syncFromProducts($supplier, $config->id);

        $cats = SupplierCategory::query()->where('supplier_id', $supplier->id)->get();
        $this->assertCount(2, $cats);

        $fiction = $cats->firstWhere('original_path', 'Books > Fiction');
        $vinyl = $cats->firstWhere('original_path', 'Music > Vinyl');
        $this->assertSame(2, $fiction->product_count);
        $this->assertSame(1, $vinyl->product_count);
        $this->assertSame('Fiction', $fiction->original_name);
        $this->assertSame('Vinyl', $vinyl->original_name);

        $this->assertSame($fiction->id, $a->fresh()->supplier_category_id);
        $this->assertSame($fiction->id, $b->fresh()->supplier_category_id);
        $this->assertSame($vinyl->id, $c->fresh()->supplier_category_id);
    }

    public function test_propagate_mappings_sets_product_shoptet_category_id(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test', 'slug' => 'test']);
        $sc = SupplierCategory::query()->create([
            'supplier_id' => $supplier->id,
            'original_name' => 'Fiction',
            'original_path' => 'Books > Fiction',
            'product_count' => 0,
        ]);
        $shoptet = ShoptetCategory::query()->create([
            'shoptet_id' => 100, 'title' => 'Beletrie', 'full_path' => 'Knihy > Beletrie',
        ]);
        CategoryMapping::query()->create([
            'supplier_category_id' => $sc->id,
            'shoptet_category_id' => $shoptet->id,
        ]);

        $product = Product::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_category_id' => $sc->id,
            'code' => 'X', 'name' => 'X', 'complete_path' => 'Books > Fiction',
        ]);

        (new CategoryMappingService())->propagateMappings($supplier);

        $this->assertSame($shoptet->id, $product->fresh()->shoptet_category_id);
    }

    public function test_propagate_clears_shoptet_category_id_when_mapping_removed(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test', 'slug' => 'test']);
        $sc = SupplierCategory::query()->create([
            'supplier_id' => $supplier->id,
            'original_name' => 'Books', 'original_path' => 'Books',
            'product_count' => 0,
        ]);
        $shoptet = ShoptetCategory::query()->create([
            'shoptet_id' => 1, 'title' => 'Knihy', 'full_path' => 'Knihy',
        ]);
        $product = Product::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_category_id' => $sc->id,
            'shoptet_category_id' => $shoptet->id,
            'code' => 'X', 'name' => 'X',
        ]);

        // No mapping exists for $sc → propagate must clear product.shoptet_category_id.
        (new CategoryMappingService())->propagateMappings($supplier);

        $this->assertNull($product->fresh()->shoptet_category_id);
    }

    public function test_auto_map_matches_exact_titles(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test', 'slug' => 'test']);
        SupplierCategory::query()->create([
            'supplier_id' => $supplier->id,
            'original_name' => 'Beletrie', 'original_path' => 'Knihy > Beletrie',
        ]);
        $target = ShoptetCategory::query()->create([
            'shoptet_id' => 100, 'title' => 'Beletrie', 'full_path' => 'Knihy > Beletrie',
        ]);

        $result = (new CategoryMappingService())->autoMap($supplier);

        $this->assertSame(1, $result['matched']);
        $this->assertSame(1, $result['examined']);
        $this->assertSame(
            $target->id,
            CategoryMapping::query()->first()->shoptet_category_id,
        );
    }

    public function test_auto_map_skips_already_mapped(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test', 'slug' => 'test']);
        $sc = SupplierCategory::query()->create([
            'supplier_id' => $supplier->id,
            'original_name' => 'Knihy', 'original_path' => 'Knihy',
        ]);
        $first = ShoptetCategory::query()->create([
            'shoptet_id' => 1, 'title' => 'Knihy', 'full_path' => 'Knihy',
        ]);
        ShoptetCategory::query()->create([
            'shoptet_id' => 2, 'title' => 'Knihy a literatura', 'full_path' => 'Knihy a literatura',
        ]);
        CategoryMapping::query()->create([
            'supplier_category_id' => $sc->id,
            'shoptet_category_id' => $first->id,
        ]);

        $result = (new CategoryMappingService())->autoMap($supplier);

        $this->assertSame(0, $result['matched']);
        $this->assertSame(0, $result['examined']);
    }

    public function test_auto_map_below_threshold_doesnt_match(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test', 'slug' => 'test']);
        SupplierCategory::query()->create([
            'supplier_id' => $supplier->id,
            'original_name' => 'XYZ Foo', 'original_path' => 'XYZ Foo',
        ]);
        ShoptetCategory::query()->create([
            'shoptet_id' => 1, 'title' => 'Cars', 'full_path' => 'Cars',
        ]);

        $result = (new CategoryMappingService())->autoMap($supplier);

        $this->assertSame(0, $result['matched']);
        $this->assertSame(1, $result['examined']);
    }
}
