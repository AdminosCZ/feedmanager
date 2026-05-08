<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use Adminos\Modules\Feedmanager\Services\B2bInclusion\B2bInclusionReason;
use Adminos\Modules\Feedmanager\Services\B2bInclusion\B2bInclusionResolver;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Each test isolates one branch of the resolver's precedence ladder so a
 * regression in one rule doesn't mask others. The order of tests mirrors the
 * order in `B2bInclusionResolver::resolve()`.
 */
final class B2bInclusionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_globally_excluded_product_wins_over_everything(): void
    {
        $product = Product::query()->create([
            'code' => 'A',
            'name' => 'A',
            'price_vat' => '99',
            'is_excluded' => true,
            'is_b2b_allowed' => true,
            'is_b2b_paused' => false,
            'b2b_inclusion_override' => Product::B2B_OVERRIDE_FORCE_ALLOWED,
        ]);

        $result = $this->resolver()->resolve($product);

        $this->assertFalse($result->isIncluded());
        $this->assertSame(B2bInclusionReason::EXCLUDED_GLOBALLY, $result->reason);
    }

    public function test_force_excluded_override_beats_master_and_category(): void
    {
        $product = Product::query()->create([
            'code' => 'B',
            'name' => 'B',
            'price_vat' => '99',
            'is_b2b_allowed' => true,
            'b2b_inclusion_override' => Product::B2B_OVERRIDE_FORCE_EXCLUDED,
        ]);

        $result = $this->resolver()->resolve($product);

        $this->assertSame(B2bInclusionReason::EXCLUDED_FORCE_EXCLUDED, $result->reason);
    }

    public function test_master_off_excludes(): void
    {
        $product = Product::query()->create([
            'code' => 'C',
            'name' => 'C',
            'price_vat' => '99',
            'is_b2b_allowed' => false,
        ]);

        $this->assertSame(
            B2bInclusionReason::EXCLUDED_MASTER_OFF,
            $this->resolver()->resolve($product)->reason,
        );
    }

    public function test_paused_excludes(): void
    {
        $product = Product::query()->create([
            'code' => 'D',
            'name' => 'D',
            'price_vat' => '99',
            'is_b2b_allowed' => true,
            'is_b2b_paused' => true,
        ]);

        $this->assertSame(
            B2bInclusionReason::EXCLUDED_PAUSED,
            $this->resolver()->resolve($product)->reason,
        );
    }

    public function test_force_allowed_override_beats_category_exclusion(): void
    {
        $cat = ShoptetCategory::query()->create([
            'shoptet_id' => 100,
            'title' => 'Náhradní díly',
            'exclude_from_b2b' => true,
        ]);
        $product = Product::query()->create([
            'code' => 'E',
            'name' => 'E',
            'price_vat' => '99',
            'is_b2b_allowed' => true,
            'shoptet_category_id' => $cat->id,
            'b2b_inclusion_override' => Product::B2B_OVERRIDE_FORCE_ALLOWED,
        ]);

        $result = $this->resolver()->resolve($product);

        $this->assertTrue($result->isIncluded());
        $this->assertSame(B2bInclusionReason::INCLUDED_FORCE_ALLOWED, $result->reason);
    }

    public function test_category_exclusion_works(): void
    {
        $cat = ShoptetCategory::query()->create([
            'shoptet_id' => 200,
            'title' => 'Bazar',
            'exclude_from_b2b' => true,
        ]);
        $product = Product::query()->create([
            'code' => 'F',
            'name' => 'F',
            'price_vat' => '99',
            'is_b2b_allowed' => true,
            'shoptet_category_id' => $cat->id,
        ]);

        $result = $this->resolver()->resolve($product);

        $this->assertFalse($result->isIncluded());
        $this->assertSame(B2bInclusionReason::EXCLUDED_CATEGORY, $result->reason);
        $this->assertSame($cat->id, $result->excludingCategory?->id);
    }

    public function test_category_exclusion_cascades_to_descendants(): void
    {
        $parent = ShoptetCategory::query()->create([
            'shoptet_id' => 300,
            'title' => 'Náhradní díly',
            'exclude_from_b2b' => true,
        ]);
        $child = ShoptetCategory::query()->create([
            'shoptet_id' => 301,
            'parent_shoptet_id' => 300,
            'title' => 'Filtry',
            'exclude_from_b2b' => false, // descendant nemá vlastní flag
        ]);
        $product = Product::query()->create([
            'code' => 'G',
            'name' => 'G',
            'price_vat' => '99',
            'is_b2b_allowed' => true,
            'shoptet_category_id' => $child->id,
        ]);

        $this->assertSame(
            B2bInclusionReason::EXCLUDED_CATEGORY,
            $this->resolver()->resolve($product)->reason,
        );
    }

    public function test_default_inclusion_when_nothing_blocks(): void
    {
        $product = Product::query()->create([
            'code' => 'H',
            'name' => 'H',
            'price_vat' => '99',
            'is_b2b_allowed' => true,
            'is_b2b_paused' => false,
        ]);

        $this->assertSame(
            B2bInclusionReason::INCLUDED_DEFAULT,
            $this->resolver()->resolve($product)->reason,
        );
    }

    public function test_constrain_to_included_query_filters_correctly(): void
    {
        $excludedCat = ShoptetCategory::query()->create([
            'shoptet_id' => 400,
            'title' => 'Excluded',
            'exclude_from_b2b' => true,
        ]);

        Product::query()->create([
            'code' => 'IN',
            'name' => 'IN',
            'price_vat' => '99',
            'is_b2b_allowed' => true,
        ]);
        Product::query()->create([
            'code' => 'OUT_MASTER',
            'name' => 'O',
            'price_vat' => '99',
            'is_b2b_allowed' => false,
        ]);
        Product::query()->create([
            'code' => 'OUT_CATEGORY',
            'name' => 'O',
            'price_vat' => '99',
            'is_b2b_allowed' => true,
            'shoptet_category_id' => $excludedCat->id,
        ]);
        Product::query()->create([
            'code' => 'IN_FORCE',
            'name' => 'I',
            'price_vat' => '99',
            'is_b2b_allowed' => false, // master off, ale force_allowed přebije
            'b2b_inclusion_override' => Product::B2B_OVERRIDE_FORCE_ALLOWED,
            'shoptet_category_id' => $excludedCat->id,
        ]);

        $codes = $this->resolver()
            ->constrainToIncluded(Product::query())
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $this->assertSame(['IN', 'IN_FORCE'], $codes);
    }

    private function resolver(): B2bInclusionResolver
    {
        return new B2bInclusionResolver();
    }
}
