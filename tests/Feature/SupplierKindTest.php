<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class SupplierKindTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_is_external_supplier(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Knihy s.r.o.',
            'slug' => 'knihy-sro',
        ]);

        $this->assertFalse($supplier->is_own);
        $this->assertTrue($supplier->is_active);
    }

    public function test_can_create_own_eshop(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Markstore',
            'slug' => 'markstore',
            'is_own' => true,
        ]);

        $this->assertTrue($supplier->is_own);
    }

    public function test_is_own_is_cast_to_boolean(): void
    {
        Supplier::query()->create([
            'name' => 'Markstore', 'slug' => 'markstore', 'is_own' => 1,
        ]);

        $supplier = Supplier::query()->where('slug', 'markstore')->first();
        $this->assertIsBool($supplier->is_own);
        $this->assertTrue($supplier->is_own);
    }
}
