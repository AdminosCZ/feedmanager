<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

final class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_is_created(): void
    {
        $this->assertTrue(Schema::hasTable('feedmanager_products'));
    }

    public function test_products_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'code', 'ean', 'product_number',
            'name', 'description', 'manufacturer',
            'price', 'price_vat', 'old_price_vat', 'currency',
            'stock_quantity', 'availability', 'delivery_date',
            'image_url', 'category_text', 'complete_path',
            'is_b2b_allowed', 'is_excluded',
            'override_name', 'override_description', 'override_price_vat',
            'locked_fields', 'imported_at',
            'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('feedmanager_products', $column),
                "Column [$column] missing from feedmanager_products."
            );
        }
    }

    public function test_can_create_and_retrieve_product(): void
    {
        $product = Product::query()->create([
            'code' => 'SKU-001',
            'name' => 'Demo product',
            'price' => '82.6446',
            'price_vat' => '99.9999',
            'currency' => 'CZK',
            'stock_quantity' => 5,
            'is_b2b_allowed' => true,
            'is_excluded' => false,
        ]);

        $fresh = Product::query()->find($product->id);

        $this->assertNotNull($fresh);
        $this->assertSame('SKU-001', $fresh->code);
        $this->assertSame('Demo product', $fresh->name);
        $this->assertSame('99.9999', $fresh->price_vat);
        $this->assertSame(5, $fresh->stock_quantity);
        $this->assertTrue($fresh->is_b2b_allowed);
        $this->assertFalse($fresh->is_excluded);
    }

    public function test_code_is_unique_per_supplier(): void
    {
        $supplier = \Adminos\Modules\Feedmanager\Models\Supplier::query()->create([
            'name' => 'Same supplier',
            'slug' => 'same',
        ]);

        Product::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'SKU-DUP',
            'name' => 'First',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Product::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'SKU-DUP',
            'name' => 'Second',
        ]);
    }

    public function test_same_code_allowed_across_different_suppliers(): void
    {
        $a = \Adminos\Modules\Feedmanager\Models\Supplier::query()->create([
            'name' => 'A', 'slug' => 'sup-a',
        ]);
        $b = \Adminos\Modules\Feedmanager\Models\Supplier::query()->create([
            'name' => 'B', 'slug' => 'sup-b',
        ]);

        $first = Product::query()->create([
            'supplier_id' => $a->id, 'code' => 'SKU-SHARED', 'name' => 'A',
        ]);
        $second = Product::query()->create([
            'supplier_id' => $b->id, 'code' => 'SKU-SHARED', 'name' => 'B',
        ]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Product::query()->where('code', 'SKU-SHARED')->count());
    }

    public function test_locked_fields_is_cast_to_array(): void
    {
        $product = Product::query()->create([
            'code' => 'SKU-002',
            'name' => 'With locks',
            'locked_fields' => ['name', 'price_vat'],
        ]);

        $fresh = Product::query()->find($product->id);

        $this->assertIsArray($fresh->locked_fields);
        $this->assertSame(['name', 'price_vat'], $fresh->locked_fields);
    }
}
