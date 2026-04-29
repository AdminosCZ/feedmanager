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
 * Mapping is meaningful only for **external suppliers** — they ship products
 * with their own category names that need translation onto the client's
 * shop category tree. Own-eshop products (`Supplier::is_own = true`) come
 * with categories that *are* the shop tree, so {@see linkOwnEshopProducts()}
 * resolves them by direct path match instead of going through
 * supplier_categories at all.
 *
 * - {@see syncFromProducts()} re-derives `supplier_categories` from product
 *   `complete_path`/`category_text`, refreshes `product_count`, and links
 *   each product to its `supplier_category_id`. **No-op for own-eshop.**
 * - {@see propagateMappings()} pushes the active `category_mappings` rows
 *   onto product `shoptet_category_id` so the exporter can resolve the
 *   target category in a single column read.
 * - {@see linkOwnEshopProducts()} matches each own-eshop product's
 *   `complete_path` against `shoptet_categories.full_path` and writes the
 *   resolved id directly to `product.shoptet_category_id`.
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
        if ($supplier->is_own === true) {
            // Own-eshop categories aren't a translation target — they're
            // already the shop tree. Skip and let linkOwnEshopProducts()
            // handle the direct path match.
            return;
        }

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
     * Match each own-eshop product's `complete_path` against the live shop
     * category tree (`shoptet_categories.full_path`) and write the resolved
     * id directly onto the product. No supplier_categories indirection.
     *
     * Path normalisation tolerates Shoptet's `|` separator (custom XML's
     * `#COMPLETE_PATH_PIPE#`) vs the sync service's canonical `>`.
     */
    public function linkOwnEshopProducts(Supplier $supplier, ?int $feedConfigId = null): void
    {
        if ($supplier->is_own !== true) {
            return;
        }

        $pathMap = ShoptetCategory::query()
            ->whereNotNull('full_path')
            ->get(['id', 'full_path'])
            ->mapWithKeys(fn (ShoptetCategory $c): array => [
                $this->normalizePath((string) $c->full_path) => $c->id,
            ])
            ->all();

        if ($pathMap === []) {
            return;
        }

        Product::query()
            ->where('supplier_id', $supplier->id)
            ->when($feedConfigId !== null, fn ($q) => $q->where('feed_config_id', $feedConfigId))
            ->whereNotNull('complete_path')
            ->chunkById(500, function ($products) use ($pathMap): void {
                foreach ($products as $product) {
                    $key = $this->normalizePath((string) $product->complete_path);
                    $matchId = $pathMap[$key] ?? null;

                    if ($product->shoptet_category_id !== $matchId) {
                        $product->forceFill(['shoptet_category_id' => $matchId])->save();
                    }
                }
            });
    }

    /**
     * After a mapping is created/updated/deleted, push the change down to
     * every product that lives in that supplier category. No-op for
     * own-eshop suppliers — their products are linked via direct path.
     */
    public function propagateMappings(Supplier $supplier): void
    {
        if ($supplier->is_own === true) {
            return;
        }

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

    /**
     * Normalise a category path to a canonical form (`A > B > C`) so paths
     * that differ only in separator (Shoptet `|` vs sync service `>`) match.
     */
    private function normalizePath(string $path): string
    {
        $segments = preg_split('/\s*[|>]\s*/', $path) ?: [];
        $segments = array_values(array_filter(
            array_map('trim', $segments),
            fn (string $s): bool => $s !== '',
        ));

        return implode(' > ', $segments);
    }
}
