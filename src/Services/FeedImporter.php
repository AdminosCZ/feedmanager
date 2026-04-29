<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\ProductImage;
use Adminos\Modules\Feedmanager\Models\ProductParameter;
use Adminos\Modules\Feedmanager\Services\CategoryMappingService;
use Adminos\Modules\Feedmanager\Services\Parsing\FeedParserFactory;
use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;
use Adminos\Modules\Feedmanager\Services\RuleEngine\RuleEngine;
use Throwable;

/**
 * Orchestrates the import pipeline for a single {@see FeedConfig}:
 *
 *   1. Mark the config as "running" so concurrent runs don't pile up.
 *   2. Download the feed payload via {@see FeedDownloader}.
 *   3. Parse it lazily via the format-specific {@see FeedParser}.
 *   4. Upsert each product into `feedmanager_products`, scoped by
 *      (supplier_id, code). Locked fields are honoured so manual overrides
 *      are never overwritten by an import.
 *   5. Write a single {@see ImportLog} row summarising the run.
 *
 * The importer never throws — it catches exceptions and captures them in the
 * log. The cron scheduler reads `last_status` to know whether to alert.
 *
 * @api
 */
class FeedImporter
{
    public function __construct(
        private readonly FeedDownloader $downloader,
        private readonly FeedParserFactory $parsers,
        private readonly RuleEngine $rules,
        private readonly CategoryMappingService $categoryMappings,
        private readonly ShoptetCategorySyncService $categorySync,
    ) {
    }

    public function run(FeedConfig $config, string $triggeredBy = ImportLog::TRIGGER_MANUAL): ImportLog
    {
        // Category-tree feeds run a completely different pipeline than product
        // feeds — different parser, different target table, different post-
        // processing. Delegate.
        if ($config->isCategoryFeed()) {
            return $this->categorySync->run($config, $triggeredBy);
        }

        $startedAt = now();

        $config->forceFill([
            'last_status' => FeedConfig::STATUS_RUNNING,
            'last_message' => null,
        ])->save();

        $found = 0;
        $new = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errorMessage = null;

        try {
            $payload = $this->downloader->download($config);
            $parser = $this->parsers->for($config->format);

            foreach ($parser->parse($payload) as $parsed) {
                ++$found;

                try {
                    $transformed = $this->rules->apply($parsed, $config);

                    if ($transformed === null) {
                        // Rule decided to remove this product — neither new nor updated.
                        continue;
                    }

                    $result = $this->upsert($config, $transformed);
                    match ($result) {
                        'created' => ++$new,
                        'updated' => ++$updated,
                        'skipped' => ++$skipped,
                    };
                } catch (Throwable $rowError) {
                    ++$failed;
                    report($rowError);
                }
            }

            // External suppliers: re-derive supplier_categories from imported
            // products and push pre-existing mappings down. Own eshop:
            // products carry the live shop tree paths, so we resolve
            // shoptet_category_id directly via path match — no
            // supplier_categories indirection needed.
            if ($config->supplier !== null) {
                if ($config->supplier->is_own === true) {
                    $this->categoryMappings->linkOwnEshopProducts($config->supplier, $config->id);
                } else {
                    $this->categoryMappings->syncFromProducts($config->supplier, $config->id);
                    $this->categoryMappings->propagateMappings($config->supplier);
                }
            }

            $status = ImportLog::STATUS_SUCCESS;
        } catch (Throwable $e) {
            $status = ImportLog::STATUS_FAILED;
            $errorMessage = $e->getMessage();
            report($e);
        }

        $finishedAt = now();

        $config->forceFill([
            'last_run_at' => $finishedAt,
            'last_status' => $status === ImportLog::STATUS_SUCCESS
                ? FeedConfig::STATUS_SUCCESS
                : FeedConfig::STATUS_FAILED,
            'last_message' => $errorMessage ?? sprintf(
                'OK — found %d, new %d, updated %d, skipped %d, failed %d.',
                $found,
                $new,
                $updated,
                $skipped,
                $failed,
            ),
        ])->save();

        return ImportLog::query()->create([
            'feed_config_id' => $config->id,
            'status' => $status,
            'triggered_by' => $triggeredBy,
            'products_found' => $found,
            'products_new' => $new,
            'products_updated' => $updated,
            'products_failed' => $failed,
            'message' => $errorMessage,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ]);
    }

    /**
     * Strip fields that the FeedConfig has opted out of importing. Lets the
     * client say "this feed only gives me prices/stock — keep my own
     * descriptions and image gallery untouched" without per-product locks.
     *
     * @return array<string, mixed>
     */
    private function applyImportFlags(FeedConfig $config, ParsedProduct $parsed): array
    {
        $attributes = $parsed->toAttributes();

        if (! $config->import_short_description) {
            unset($attributes['short_description']);
        }
        if (! $config->import_long_description) {
            unset($attributes['description']);
        }

        return $attributes;
    }

    /**
     * @return 'created'|'updated'|'skipped'
     */
    private function upsert(FeedConfig $config, ParsedProduct $parsed): string
    {
        $existing = $this->findExistingProduct($config->supplier_id, $parsed->code);

        // Parameters-only feeds (Shoptet Heureka feed used as supplement
        // to a custom XML feed that can't export parameters) skip the upsert
        // entirely. They only refresh ProductParameter rows for products
        // that already exist; unknown codes are quietly skipped.
        if ($config->import_parameters_only) {
            if ($existing === null) {
                return 'skipped';
            }

            $this->syncParameters($existing, $parsed);

            return 'updated';
        }

        if ($existing === null) {
            if ($config->update_only_mode) {
                // Stock-supplement / partial feeds intentionally don't grow
                // the catalogue — products that don't already exist are
                // skipped silently.
                return 'skipped';
            }

            // On create:
            //  - default_b2b_allowed honours per-feed config so re-seller
            //    catalogues land with B2B blocked by default.
            //  - status defaults to 'approved' for own-eshop products (admin
            //    sells them in their own Shoptet, no review needed) and
            //    'pending' for external suppliers (admin vets dropship).
            $supplier = $config->supplier;
            $isOwnEshop = $supplier !== null && $supplier->is_own === true;

            $product = Product::query()->create([
                'supplier_id' => $config->supplier_id,
                'feed_config_id' => $config->id,
                'code' => $parsed->code,
                'imported_at' => now(),
                'is_b2b_allowed' => $config->default_b2b_allowed,
                'status' => $isOwnEshop ? Product::STATUS_APPROVED : Product::STATUS_PENDING,
                ...$this->applyImportFlags($config, $parsed),
            ]);

            $this->syncGallery($product, $parsed, $config);
            $this->syncParameters($product, $parsed);

            return 'created';
        }

        $attributes = $this->applyImportFlags($config, $parsed);

        foreach ($existing->locked_fields ?? [] as $lockedField) {
            unset($attributes[$lockedField]);
        }

        $existing->forceFill([
            ...$attributes,
            // For update-only feeds (stock supplements) keep the original
            // feed_config_id so the catalogue's primary source is preserved.
            'feed_config_id' => $config->update_only_mode
                ? $existing->feed_config_id
                : $config->id,
            'imported_at' => now(),
        ])->save();

        $this->syncGallery($existing, $parsed, $config);
        $this->syncParameters($existing, $parsed);

        return 'updated';
    }

    /**
     * Look up an existing product by (supplier_id, code) with a fallback for
     * Shoptet code sanitization mismatch — XML feeds map `/` and ` ` to `_`,
     * stock CSV preserves the raw form. Try the sanitized variant before
     * giving up.
     */
    private function findExistingProduct(?int $supplierId, string $code): ?Product
    {
        $base = Product::query()->where('supplier_id', $supplierId);

        $exact = (clone $base)->where('code', $code)->first();
        if ($exact !== null) {
            return $exact;
        }

        $sanitized = str_replace(['/', ' '], '_', $code);
        if ($sanitized !== $code) {
            $alt = (clone $base)->where('code', $sanitized)->first();
            if ($alt !== null) {
                return $alt;
            }
        }

        return null;
    }

    /**
     * Replace the product's gallery with what the parser delivered. Primary
     * image (image_url) is left as-is on the Product itself; gallery rows
     * cover the remaining feed images.
     *
     * Skipped when the parser produced no gallery — avoids wiping manual
     * uploads when a feed format doesn't expose extra images. Also skipped
     * when the FeedConfig opted out via `import_all_images = false`.
     */
    private function syncGallery(Product $product, ParsedProduct $parsed, FeedConfig $config): void
    {
        if (! $config->import_all_images) {
            return;
        }

        if ($parsed->gallery_urls === []) {
            return;
        }

        ProductImage::query()->where('product_id', $product->id)->delete();

        $position = 1;
        foreach ($parsed->gallery_urls as $url) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'url' => $url,
                'position' => $position++,
            ]);
        }
    }

    /**
     * Same protective semantics as {@see syncGallery()} — empty parameter
     * list keeps existing rows so manual additions in admin survive
     * re-imports from feeds that don't expose parameters.
     */
    private function syncParameters(Product $product, ParsedProduct $parsed): void
    {
        if ($parsed->parameters === []) {
            return;
        }

        ProductParameter::query()->where('product_id', $product->id)->delete();

        $position = 1;
        foreach ($parsed->parameters as $param) {
            ProductParameter::query()->create([
                'product_id' => $product->id,
                'name' => $param['name'],
                'value' => $param['value'],
                'position' => $position++,
            ]);
        }
    }
}
