<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services;

use Adminos\Modules\Feedmanager\Models\CategoryMapping;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Models\SupplierCategory;

/**
 * Builds and maintains the supplier-category ↔ shoptet-category linkage.
 *
 * - {@see syncFromProducts()} re-derives `supplier_categories` from product
 *   `complete_path`/`category_text`, refreshes `product_count`, and links
 *   each product to its `supplier_category_id`.
 * - {@see propagateMappings()} pushes the active `category_mappings` rows
 *   onto product `shoptet_category_id` so the exporter can resolve the
 *   target category in a single column read.
 * - {@see autoMap()} fuzzy-matches unmapped supplier categories against
 *   shoptet categories using PHP's `similar_text` percent score.
 *
 * @api
 */
final class CategoryMappingService
{
    public function __construct(
        private readonly int $autoMapMinScore = 80,
    ) {
    }

    /**
     * Re-derive supplier_categories rows from product `complete_path` /
     * `category_text` for the given supplier (and optional feed scope).
     *
     * Idempotent — safe to run after every import.
     */
    public function syncFromProducts(Supplier $supplier, ?int $feedConfigId = null): void
    {
        $rows = Product::query()
            ->where('supplier_id', $supplier->id)
            ->when($feedConfigId !== null, fn ($q) => $q->where('feed_config_id', $feedConfigId))
            ->get(['id', 'feed_config_id', 'category_text', 'complete_path']);

        $byPath = [];
        foreach ($rows as $product) {
            $path = $product->complete_path ?: $product->category_text;
            if ($path === null || $path === '') {
                continue;
            }

            $name = $this->lastSegment($path);
            if (! isset($byPath[$path])) {
                $byPath[$path] = [
                    'name' => $name,
                    'feed_config_id' => $product->feed_config_id,
                    'product_ids' => [],
                ];
            }
            $byPath[$path]['product_ids'][] = $product->id;
        }

        foreach ($byPath as $path => $info) {
            /** @var SupplierCategory $category */
            $category = SupplierCategory::query()->updateOrCreate(
                ['supplier_id' => $supplier->id, 'original_path' => $path],
                [
                    'feed_config_id' => $info['feed_config_id'],
                    'original_name' => $info['name'],
                    'product_count' => count($info['product_ids']),
                ],
            );

            Product::query()
                ->whereIn('id', $info['product_ids'])
                ->update(['supplier_category_id' => $category->id]);
        }
    }

    /**
     * After a mapping is created/updated/deleted, push the change down to
     * every product that lives in that supplier category.
     */
    public function propagateMappings(Supplier $supplier): void
    {
        $supplierCategories = SupplierCategory::query()
            ->where('supplier_id', $supplier->id)
            ->with('mapping')
            ->get();

        foreach ($supplierCategories as $sc) {
            Product::query()
                ->where('supplier_category_id', $sc->id)
                ->update([
                    'shoptet_category_id' => $sc->mapping?->shoptet_category_id,
                ]);
        }
    }

    /**
     * @return array{matched: int, examined: int}
     */
    public function autoMap(Supplier $supplier): array
    {
        $unmappedQuery = SupplierCategory::query()
            ->where('supplier_id', $supplier->id)
            ->whereDoesntHave('mapping');

        $unmapped = $unmappedQuery->get();
        $shoptetCategories = ShoptetCategory::query()->get(['id', 'title', 'full_path']);

        $matched = 0;

        foreach ($unmapped as $supplierCategory) {
            $best = $this->bestMatch($supplierCategory->original_name, $shoptetCategories);

            if ($best !== null) {
                CategoryMapping::query()->create([
                    'supplier_category_id' => $supplierCategory->id,
                    'shoptet_category_id' => $best->id,
                ]);
                ++$matched;
            }
        }

        if ($matched > 0) {
            $this->propagateMappings($supplier);
        }

        return ['matched' => $matched, 'examined' => $unmapped->count()];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ShoptetCategory>  $candidates
     */
    private function bestMatch(string $needle, \Illuminate\Database\Eloquent\Collection $candidates): ?ShoptetCategory
    {
        $needleLower = mb_strtolower(trim($needle));
        $bestScore = 0.0;
        $best = null;

        foreach ($candidates as $candidate) {
            $candidateLower = mb_strtolower(trim($candidate->title));

            if ($candidateLower === $needleLower) {
                return $candidate;
            }

            similar_text($needleLower, $candidateLower, $percent);

            if ($percent > $bestScore && $percent >= $this->autoMapMinScore) {
                $bestScore = $percent;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function lastSegment(string $path): string
    {
        $segments = preg_split('/\s*[|>\/]\s*/', $path) ?: [];
        $segments = array_values(array_filter($segments, fn (string $s): bool => $s !== ''));
        return $segments === [] ? $path : (string) end($segments);
    }
}
