<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\B2bInclusion;

use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Centrální vyhodnocování B2B inclusion. Jeden zdroj pravdy pro:
 *   - {@see B2bFeedExporter} (hromadný dotaz pro export)
 *   - admin UI (per-product status card s důvodem)
 *   - testy (deterministická precedence)
 *
 * Precedence (vyhodnocuje se shora dolů, první match vyhraje):
 *
 *   1. is_excluded = true                      → EXCLUDED_GLOBALLY
 *   2. b2b_inclusion_override = force_excluded → EXCLUDED_FORCE_EXCLUDED
 *   3. is_b2b_allowed = false                  → EXCLUDED_MASTER_OFF
 *   4. is_b2b_paused = true                    → EXCLUDED_PAUSED
 *   5. b2b_inclusion_override = force_allowed  → INCLUDED_FORCE_ALLOWED
 *   6. shoptet_category_id ∈ excluded_tree     → EXCLUDED_CATEGORY
 *   7. otherwise                               → INCLUDED_DEFAULT
 *
 * `excluded_tree` se počítá z `shoptet_categories.exclude_from_b2b=true`,
 * walkujem strom dolů a zařazujeme všechny descendants. Cache per-instance
 * resolveru — invaliduje se voláním {@see flushCache()}.
 *
 * @api
 */
class B2bInclusionResolver
{
    /** @var Collection<int, int>|null  shoptet_category.id → cached set of excluded ids (parent + descendants) */
    private ?Collection $cachedExcludedCategoryIds = null;

    /** @var Collection<int, ShoptetCategory>|null  cache of all categories indexed by id */
    private ?Collection $cachedCategoriesById = null;

    /**
     * Vyhodnotí inclusion pro jeden produkt. Vrací rich result, ne jen bool —
     * volající (UI) ho používá k zobrazení důvodu.
     */
    public function resolve(Product $product): B2bInclusionResult
    {
        if ($product->is_excluded === true) {
            return new B2bInclusionResult(B2bInclusionReason::EXCLUDED_GLOBALLY);
        }

        if ($product->b2b_inclusion_override === Product::B2B_OVERRIDE_FORCE_EXCLUDED) {
            return new B2bInclusionResult(B2bInclusionReason::EXCLUDED_FORCE_EXCLUDED);
        }

        if ($product->is_b2b_allowed !== true) {
            return new B2bInclusionResult(B2bInclusionReason::EXCLUDED_MASTER_OFF);
        }

        if ($product->is_b2b_paused === true) {
            return new B2bInclusionResult(B2bInclusionReason::EXCLUDED_PAUSED);
        }

        if ($product->b2b_inclusion_override === Product::B2B_OVERRIDE_FORCE_ALLOWED) {
            return new B2bInclusionResult(B2bInclusionReason::INCLUDED_FORCE_ALLOWED);
        }

        if ($product->shoptet_category_id !== null) {
            $excluded = $this->excludedCategoryIds();
            if ($excluded->contains($product->shoptet_category_id)) {
                return new B2bInclusionResult(
                    B2bInclusionReason::EXCLUDED_CATEGORY,
                    excludingCategory: $this->categoriesById()->get($product->shoptet_category_id),
                );
            }
        }

        return new B2bInclusionResult(B2bInclusionReason::INCLUDED_DEFAULT);
    }

    /**
     * Builder modifier — připíchne na product query všechna pravidla, takže
     * `->whereIncludedInB2bFeed()` v exporteru je jediný důvěryhodný filtr.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function constrainToIncluded(Builder $query): Builder
    {
        $excluded = $this->excludedCategoryIds()->all();

        return $query
            ->where('is_excluded', false)
            ->where(function (Builder $q): void {
                $q->where('b2b_inclusion_override', '!=', Product::B2B_OVERRIDE_FORCE_EXCLUDED)
                    ->orWhereNull('b2b_inclusion_override');
            })
            ->where(function (Builder $q) use ($excluded): void {
                // force_allowed obchází kategoriální + master/paused checky
                $q->where('b2b_inclusion_override', Product::B2B_OVERRIDE_FORCE_ALLOWED)
                    ->orWhere(function (Builder $q2) use ($excluded): void {
                        $q2->where('is_b2b_allowed', true)
                            ->where('is_b2b_paused', false)
                            ->when(
                                $excluded !== [],
                                fn (Builder $q3) => $q3->where(function (Builder $q4) use ($excluded): void {
                                    $q4->whereNull('shoptet_category_id')
                                        ->orWhereNotIn('shoptet_category_id', $excluded);
                                }),
                            );
                    });
            });
    }

    /**
     * Plochá množina ID vyřazených kategorií (vlastní flag + všechny děti
     * rekurzivně). Cachováno per-instance resolveru.
     *
     * @return Collection<int, int>  set of shoptet_category.id values
     */
    public function excludedCategoryIds(): Collection
    {
        if ($this->cachedExcludedCategoryIds !== null) {
            return $this->cachedExcludedCategoryIds;
        }

        $byId = $this->categoriesById();
        if ($byId->isEmpty()) {
            return $this->cachedExcludedCategoryIds = collect();
        }

        // Roots = kategorie s explicit flag exclude_from_b2b=true.
        $roots = $byId->filter(fn (ShoptetCategory $c): bool => $c->exclude_from_b2b === true);

        if ($roots->isEmpty()) {
            return $this->cachedExcludedCategoryIds = collect();
        }

        // Children index: parent_shoptet_id → list<ShoptetCategory>
        $childrenByParentSid = $byId->groupBy(fn (ShoptetCategory $c): ?int => $c->parent_shoptet_id);

        $excludedIds = collect();
        foreach ($roots as $root) {
            $this->collectDescendants($root, $childrenByParentSid, $excludedIds);
        }

        return $this->cachedExcludedCategoryIds = $excludedIds->unique()->values();
    }

    /**
     * Map shoptet_category.id → ShoptetCategory pro detail v UI (např. název
     * vyřazené kategorie v B2bInclusionResult).
     *
     * @return Collection<int, ShoptetCategory>
     */
    public function categoriesById(): Collection
    {
        if ($this->cachedCategoriesById !== null) {
            return $this->cachedCategoriesById;
        }

        return $this->cachedCategoriesById = ShoptetCategory::query()
            ->get()
            ->keyBy('id');
    }

    /**
     * Reset cache — volat po změně exclude_from_b2b nebo po sync kategorie.
     * Container singleton resolver tak může bezpečně přežít napříč Livewire
     * requesty.
     */
    public function flushCache(): void
    {
        $this->cachedExcludedCategoryIds = null;
        $this->cachedCategoriesById = null;
    }

    /**
     * @param  Collection<int|string, Collection<int, ShoptetCategory>>  $childrenByParentSid
     * @param  Collection<int, int>  $accumulator
     */
    private function collectDescendants(
        ShoptetCategory $node,
        Collection $childrenByParentSid,
        Collection $accumulator,
    ): void {
        if ($accumulator->contains($node->id)) {
            return; // ochrana proti cyklu (shouldn't happen, ale defensive)
        }

        $accumulator->push($node->id);

        $children = $childrenByParentSid->get($node->shoptet_id, collect());
        foreach ($children as $child) {
            $this->collectDescendants($child, $childrenByParentSid, $accumulator);
        }
    }
}
