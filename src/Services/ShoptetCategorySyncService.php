<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services;

use Adminos\Modules\Feedmanager\Models\CategoryMapping;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use Adminos\Modules\Feedmanager\Services\Parsing\ParsedCategory;
use Adminos\Modules\Feedmanager\Services\Parsing\ShoptetCategoriesParser;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Syncs the Shoptet category tree into `feedmanager_shoptet_categories` from
 * a {@see FeedConfig} of format `shoptet_categories`.
 *
 * Pipeline:
 *
 *   1. Snapshot existing rows (shoptet_id → [title, parent_shoptet_id]) so
 *      change detection can run after the upsert phase finishes.
 *   2. Stream {@see ParsedCategory} rows from the parser, upsert each by
 *      shoptet_id, stamp `synced_at = startedAt`, clear `is_orphaned`.
 *   3. Recompute `full_path` + `depth` for the whole tree (cheap — Shoptet
 *      shops rarely have > a few hundred categories).
 *   4. Mark any row whose `synced_at` is older than this run as orphaned —
 *      these are categories that disappeared from Shoptet.
 *   5. Diff snapshot vs current state, find all changes that touch a paired
 *      supplier category, and emit Filament in-app notifications to
 *      developers/admins so they re-pair manually.
 *
 * Errors are caught and turned into a failed {@see ImportLog} — same contract
 * as {@see FeedImporter}, so the cron / UI shows the failure cleanly.
 *
 * @api
 */
class ShoptetCategorySyncService
{
    public function __construct(
        private readonly FeedDownloader $downloader,
        private readonly ShoptetCategoriesParser $parser,
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
        $failed = 0;
        $orphaned = 0;
        $errorMessage = null;
        $changeNotices = [];

        try {
            $payload = $this->downloader->download($config);

            // Snapshot before upsert so we can diff after.
            $snapshot = $this->snapshotExisting();

            // Track every shoptet_id we see in this run; used after the loop
            // to flag rows that *weren't* in the import as orphaned. More
            // robust than time comparison (synced_at < startedAt), which
            // breaks down when two runs land in the same wall-clock second.
            $touchedIds = [];

            foreach ($this->parser->parse($payload) as $parsed) {
                ++$found;
                $touchedIds[] = $parsed->shoptet_id;

                try {
                    $result = $this->upsert($parsed, $startedAt);
                    match ($result) {
                        'created' => ++$new,
                        'updated' => ++$updated,
                        'noop' => null,
                    };
                } catch (Throwable $rowError) {
                    ++$failed;
                    report($rowError);
                }
            }

            $this->rebuildPathsAndDepth();

            $orphaned = $this->markOrphaned($touchedIds);

            // Compare snapshot vs current state — only paired categories need
            // admin attention; orphan / rename / move on un-paired categories
            // are noise.
            $changeNotices = $this->detectImpactfulChanges($snapshot);
            $this->dispatchNotifications($changeNotices);

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
                'OK — found %d, new %d, updated %d, orphaned %d, failed %d, mapping changes %d.',
                $found,
                $new,
                $updated,
                $orphaned,
                $failed,
                count($changeNotices),
            ),
        ])->save();

        // ImportLog columns are products_*; we reuse them as generic counters
        // so the "Importy / Logy" UI doesn't need a separate model. The
        // `message` field disambiguates.
        return ImportLog::query()->create([
            'feed_config_id' => $config->id,
            'status' => $status,
            'triggered_by' => $triggeredBy,
            'products_found' => $found,
            'products_new' => $new,
            'products_updated' => $updated + $orphaned,
            'products_failed' => $failed,
            'message' => $errorMessage ?? $config->last_message,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ]);
    }

    /**
     * @return array<int, array{title: string, parent_shoptet_id: ?int, full_path: ?string, is_orphaned: bool}>
     */
    private function snapshotExisting(): array
    {
        $snapshot = [];

        ShoptetCategory::query()
            ->select(['shoptet_id', 'title', 'parent_shoptet_id', 'full_path', 'is_orphaned'])
            ->orderBy('shoptet_id')
            ->each(function (ShoptetCategory $row) use (&$snapshot): void {
                $snapshot[$row->shoptet_id] = [
                    'title' => $row->title,
                    'parent_shoptet_id' => $row->parent_shoptet_id,
                    'full_path' => $row->full_path,
                    'is_orphaned' => $row->is_orphaned,
                ];
            });

        return $snapshot;
    }

    /**
     * @return 'created'|'updated'|'noop'
     */
    private function upsert(ParsedCategory $parsed, Carbon $startedAt): string
    {
        $existing = ShoptetCategory::query()
            ->where('shoptet_id', $parsed->shoptet_id)
            ->first();

        $attributes = [
            'parent_shoptet_id' => $parsed->parent_shoptet_id,
            'guid' => $parsed->guid,
            'title' => $parsed->title,
            'link_text' => $parsed->link_text,
            'priority' => $parsed->priority,
            'visible' => $parsed->visible,
            'synced_at' => $startedAt,
            'is_orphaned' => false,
        ];

        if ($existing === null) {
            ShoptetCategory::query()->create([
                'shoptet_id' => $parsed->shoptet_id,
                ...$attributes,
            ]);

            return 'created';
        }

        $changed = $existing->title !== $parsed->title
            || $existing->parent_shoptet_id !== $parsed->parent_shoptet_id
            || (bool) $existing->visible !== $parsed->visible
            || $existing->is_orphaned === true;

        $existing->forceFill($attributes)->save();

        return $changed ? 'updated' : 'noop';
    }

    /**
     * Walk every row, recompute `full_path` ("A > B > C") and `depth` from
     * the parent chain. Cheap relative to the upsert because Shoptet shops
     * rarely exceed a few hundred categories — no point streaming.
     */
    private function rebuildPathsAndDepth(): void
    {
        /** @var array<int, ShoptetCategory> $byId */
        $byId = ShoptetCategory::query()->get()->keyBy('shoptet_id')->all();

        foreach ($byId as $row) {
            [$path, $depth] = $this->resolvePath($row, $byId);

            if ($row->full_path !== $path || $row->depth !== $depth) {
                $row->forceFill(['full_path' => $path, 'depth' => $depth])->save();
            }
        }
    }

    /**
     * @param  array<int, ShoptetCategory>  $byId
     * @return array{0: string, 1: int}
     */
    private function resolvePath(ShoptetCategory $row, array $byId): array
    {
        $titles = [$row->title];
        $depth = 0;
        $cursorId = $row->parent_shoptet_id;
        $guard = 0;

        while ($cursorId !== null && isset($byId[$cursorId]) && $guard < 100) {
            $titles[] = $byId[$cursorId]->title;
            $cursorId = $byId[$cursorId]->parent_shoptet_id;
            ++$depth;
            ++$guard;
        }

        return [implode(' > ', array_reverse($titles)), $depth];
    }

    /**
     * Flag every row whose shoptet_id wasn't seen in this run as orphaned.
     * Soft-keep semantics — the row stays so any paired supplier categories
     * can be remapped manually before the row is removed.
     *
     * @param  list<int>  $touchedIds
     */
    private function markOrphaned(array $touchedIds): int
    {
        $query = ShoptetCategory::query()->where('is_orphaned', false);

        if ($touchedIds !== []) {
            $query->whereNotIn('shoptet_id', $touchedIds);
        }

        return $query->update(['is_orphaned' => true]);
    }

    /**
     * @param  array<int, array{title: string, parent_shoptet_id: ?int, full_path: ?string, is_orphaned: bool}>  $snapshot
     * @return list<array{type: string, shoptet_id: int, title: string, supplier_categories: list<string>, detail: ?string}>
     */
    private function detectImpactfulChanges(array $snapshot): array
    {
        // Find shoptet_ids that have at least one paired supplier category —
        // those are the only ones admin needs to act on.
        $pairedIds = CategoryMapping::query()
            ->whereIn('shoptet_category_id', ShoptetCategory::query()->select('id'))
            ->pluck('shoptet_category_id')
            ->all();

        if ($pairedIds === []) {
            return [];
        }

        /** @var array<int, ShoptetCategory> $current */
        $current = ShoptetCategory::query()
            ->whereIn('id', $pairedIds)
            ->get()
            ->keyBy('shoptet_id')
            ->all();

        $notices = [];

        foreach ($current as $shoptetId => $row) {
            $previous = $snapshot[$shoptetId] ?? null;

            // No previous snapshot — the row is brand new; the mapping must
            // also be brand new (created in the same transaction). No notice.
            if ($previous === null) {
                continue;
            }

            $type = null;
            $detail = null;

            if ($row->is_orphaned && $previous['is_orphaned'] === false) {
                $type = 'orphaned';
                $detail = $previous['full_path'] ?? $previous['title'];
            } elseif ($previous['title'] !== $row->title) {
                $type = 'renamed';
                $detail = sprintf('„%s" → „%s"', $previous['title'], $row->title);
            } elseif ($previous['parent_shoptet_id'] !== $row->parent_shoptet_id) {
                $type = 'moved';
                $detail = sprintf(
                    '%s → %s',
                    $previous['full_path'] ?? $previous['title'],
                    $row->full_path ?? $row->title,
                );
            }

            if ($type === null) {
                continue;
            }

            $supplierCategoryNames = CategoryMapping::query()
                ->where('shoptet_category_id', $row->id)
                ->with('supplierCategory')
                ->get()
                ->map(fn (CategoryMapping $m): string => $m->supplierCategory?->original_path ?? '?')
                ->values()
                ->all();

            $notices[] = [
                'type' => $type,
                'shoptet_id' => $shoptetId,
                'title' => $row->title,
                'supplier_categories' => $supplierCategoryNames,
                'detail' => $detail,
            ];
        }

        return $notices;
    }

    /**
     * @param  list<array{type: string, shoptet_id: int, title: string, supplier_categories: list<string>, detail: ?string}>  $notices
     */
    private function dispatchNotifications(array $notices): void
    {
        if ($notices === []) {
            return;
        }

        $recipients = $this->resolveRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($notices as $notice) {
            $title = match ($notice['type']) {
                'renamed' => __('feedmanager::feedmanager.shoptet_categories.notify.renamed_title', [
                    'title' => $notice['title'],
                ]),
                'moved' => __('feedmanager::feedmanager.shoptet_categories.notify.moved_title', [
                    'title' => $notice['title'],
                ]),
                'orphaned' => __('feedmanager::feedmanager.shoptet_categories.notify.orphaned_title', [
                    'title' => $notice['title'],
                ]),
                default => __('feedmanager::feedmanager.shoptet_categories.notify.changed_title', [
                    'title' => $notice['title'],
                ]),
            };

            $body = __('feedmanager::feedmanager.shoptet_categories.notify.body', [
                'detail' => $notice['detail'] ?? '—',
                'suppliers' => implode(', ', $notice['supplier_categories']),
            ]);

            $color = $notice['type'] === 'orphaned' ? 'danger' : 'warning';

            Notification::make()
                ->title($title)
                ->body($body)
                ->color($color)
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor($color)
                ->sendToDatabase($recipients);
        }
    }

    /**
     * Notifications target every authenticated user that can act on category
     * mappings — Admin and Developer roles. Returns an empty collection if no
     * user table is present (e.g. tests without skeleton).
     */
    private function resolveRecipients()
    {
        $userClass = config('auth.providers.users.model');

        if (! is_string($userClass) || ! class_exists($userClass)) {
            return collect();
        }

        try {
            /** @var Model $model */
            $model = new $userClass();

            return $userClass::query()
                ->when(
                    DB::getSchemaBuilder()->hasColumn($model->getTable(), 'role'),
                    fn ($q) => $q->whereIn('role', ['admin', 'developer']),
                )
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }
}
