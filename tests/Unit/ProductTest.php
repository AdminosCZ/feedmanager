<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Tests\TestCase;

final class ProductTest extends TestCase
{
    public function test_effective_name_returns_override_when_present(): void
    {
        $product = new Product([
            'name' => 'Imported name',
            'override_name' => 'Override name',
        ]);

        $this->assertSame('Override name', $product->effectiveName());
    }

    public function test_effective_name_falls_back_to_imported_when_override_blank(): void
    {
        $product = new Product([
            'name' => 'Imported name',
            'override_name' => '',
        ]);

        $this->assertSame('Imported name', $product->effectiveName());
    }

    public function test_effective_name_falls_back_to_imported_when_override_null(): void
    {
        $product = new Product([
            'name' => 'Imported name',
            'override_name' => null,
        ]);

        $this->assertSame('Imported name', $product->effectiveName());
    }

    public function test_effective_description_prefers_override(): void
    {
        $product = new Product([
            'description' => 'Imported desc',
            'override_description' => 'Override desc',
        ]);

        $this->assertSame('Override desc', $product->effectiveDescription());
    }

    public function test_effective_price_vat_prefers_override(): void
    {
        $product = new Product([
            'price_vat' => '99.0000',
            'override_price_vat' => '79.0000',
        ]);

        $this->assertSame('79.0000', $product->effectivePriceVat());
    }

    public function test_effective_price_vat_falls_back_to_imported(): void
    {
        $product = new Product([
            'price_vat' => '99.0000',
            'override_price_vat' => null,
        ]);

        $this->assertSame('99.0000', $product->effectivePriceVat());
    }

    public function test_is_field_locked_reads_locked_fields_array(): void
    {
        $product = new Product([
            'locked_fields' => ['name', 'price_vat'],
        ]);

        $this->assertTrue($product->isFieldLocked('name'));
        $this->assertTrue($product->isFieldLocked('price_vat'));
        $this->assertFalse($product->isFieldLocked('description'));
    }

    public function test_is_field_locked_returns_false_when_no_locks(): void
    {
        $product = new Product([
            'locked_fields' => null,
        ]);

        $this->assertFalse($product->isFieldLocked('name'));
    }
}
