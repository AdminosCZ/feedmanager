<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\Product;
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
    ) {
    }

    public function run(FeedConfig $config, string $triggeredBy = ImportLog::TRIGGER_MANUAL): ImportLog
    {
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

            // Re-derive supplier categories from imported products and push
            // any pre-existing mappings down to product.shoptet_category_id.
            if ($config->supplier !== null) {
                $this->categoryMappings->syncFromProducts($config->supplier, $config->id);
                $this->categoryMappings->propagateMappings($config->supplier);
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
     * @return 'created'|'updated'|'skipped'
     */
    private function upsert(FeedConfig $config, ParsedProduct $parsed): string
    {
        $existing = $this->findExistingProduct($config->supplier_id, $parsed->code);

        if ($existing === null) {
            if ($config->update_only_mode) {
                // Stock-supplement / partial feeds intentionally don't grow
                // the catalogue — products that don't already exist are
                // skipped silently.
                return 'skipped';
            }

            // On create, honour the FeedConfig's `default_b2b_allowed` flag so
            // re-seller catalogues can land with B2B blocked by default. On
            // update, the existing value is preserved (admin may have flipped
            // it manually).
            Product::query()->create([
                'supplier_id' => $config->supplier_id,
                'feed_config_id' => $config->id,
                'code' => $parsed->code,
                'imported_at' => now(),
                'is_b2b_allowed' => $config->default_b2b_allowed,
                ...$parsed->toAttributes(),
            ]);

            return 'created';
        }

        $attributes = $parsed->toAttributes();

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

        return 'updated';
    }

    private function findExistingProduct(?int $supplierId, string $code): ?Product
    {
        $base = Product::query()->where('supplier_id', $supplierId);

        // 1. Exact match on the code as parsed.
        $exact = (clone $base)->where('code', $code)->first();
        if ($exact !== null) {
            return $exact;
        }

        // 2. Stock CSV may carry the raw user-entered code (`121/XS`, `Foo Bar`)
        //    while the existing catalogue row was imported via Shoptet's XML
        //    feeds, which sanitize `/` and ` ` to `_`. Try the sanitized
        //    variant before declaring no match.
        $sanitized = str_replace(['/', ' '], '_', $code);
        if ($sanitized !== $code) {
            $alt = (clone $base)->where('code', $sanitized)->first();
            if ($alt !== null) {
                return $alt;
            }
        }

        return null;
    }
}
