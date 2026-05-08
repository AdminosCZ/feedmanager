<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategoryResource;
use Adminos\Modules\Feedmanager\Models\CategoryMapping;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use Adminos\Modules\Feedmanager\Services\B2bInclusion\B2bInclusionResolver;
use Adminos\Modules\Feedmanager\Services\ShoptetCategorySyncService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Tree-based browser for the Shoptet category catalogue. Builds the whole
 * hierarchy in memory (a typical eshop has hundreds, not thousands of
 * categories — no point streaming) and renders it recursively via blade,
 * with native `<details>` for collapse and Alpine for full-text filter.
 *
 * The header sync action triggers {@see ShoptetCategorySyncService} for the
 * selected `FORMAT_SHOPTET_CATEGORIES` feed config — same UX as before.
 */
final class ListShoptetCategories extends Page
{
    protected static string $resource = ShoptetCategoryResource::class;

    protected string $view = 'feedmanager::filament.pages.shoptet-categories-tree';

    public function getTitle(): string
    {
        return ShoptetCategoryResource::getPluralModelLabel();
    }

    /**
     * @return Collection<int, ShoptetCategory>
     */
    public function getRoots(): Collection
    {
        $all = ShoptetCategory::query()
            ->orderBy('priority')
            ->orderBy('title')
            ->get();

        // Build a set of every shoptet_id we have. Anything whose parent
        // doesn't appear in the set is treated as a root — Shoptet ships
        // category data with parent = 1 (the shop itself) for top-level
        // categories, but row #1 is never exported, so we can't rely on
        // null parent_shoptet_id alone.
        $existing = $all->pluck('shoptet_id')->flip();

        $byParent = $all->groupBy(
            fn (ShoptetCategory $c): string => $c->parent_shoptet_id !== null
                ? (string) $c->parent_shoptet_id
                : 'root',
        );

        foreach ($all as $cat) {
            $cat->setRelation(
                'children',
                $byParent->get((string) $cat->shoptet_id, collect()),
            );
        }

        return $all
            ->filter(fn (ShoptetCategory $c): bool => $c->parent_shoptet_id === null
                || ! $existing->has($c->parent_shoptet_id))
            ->values();
    }

    /**
     * Map of `shoptet_category_id` → number of paired supplier categories.
     * Rendered as a small badge next to each tree node so admin sees at a
     * glance "this category has 3 supplier sources mapped to it".
     *
     * @return array<int, int>
     */
    public function getMappingCounts(): array
    {
        return CategoryMapping::query()
            ->selectRaw('shoptet_category_id, count(*) as c')
            ->groupBy('shoptet_category_id')
            ->pluck('c', 'shoptet_category_id')
            ->all();
    }

    public function getOrphanCount(): int
    {
        return ShoptetCategory::query()->where('is_orphaned', true)->count();
    }

    /**
     * Plochá množina shoptet_category.id v B2B exclusion stromu (vlastní
     * flag + cascaded přes parent). Blade používá pro per-node vizuální
     * stav „explicit vs cascaded".
     *
     * @return array<int, true>  set jako asociativní pole pro O(1) lookup
     */
    public function getB2bExcludedIds(): array
    {
        return app(B2bInclusionResolver::class)
            ->excludedCategoryIds()
            ->mapWithKeys(fn (int $id): array => [$id => true])
            ->all();
    }

    /**
     * Toggle akce volaná z tree-node přes wire:click. Sklopí
     * `exclude_from_b2b` na dané kategorii a flushne resolver cache,
     * takže další render zohlední změnu (i pro descendants).
     */
    public function toggleB2bExclusion(int $categoryId): void
    {
        $category = ShoptetCategory::query()->find($categoryId);
        if ($category === null) {
            return;
        }

        $category->update(['exclude_from_b2b' => ! $category->exclude_from_b2b]);

        // Singleton resolver má cachované IDs — bez flushe by descendants
        // nereagovali na změnu rootu.
        app(B2bInclusionResolver::class)->flushCache();

        Notification::make()
            ->title(__($category->exclude_from_b2b
                ? 'feedmanager::feedmanager.shoptet_categories.tree.b2b_excluded_now'
                : 'feedmanager::feedmanager.shoptet_categories.tree.b2b_included_now',
                ['title' => $category->title],
            ))
            ->success()
            ->send();
    }

    /**
     * @return list<array{id: int, sid: int, parent: ?int, title: string, path: string}>
     */
    public function getSearchIndex(): array
    {
        return ShoptetCategory::query()
            ->orderBy('shoptet_id')
            ->get()
            ->map(fn (ShoptetCategory $c): array => [
                'id' => $c->id,
                'sid' => $c->shoptet_id,
                'parent' => $c->parent_shoptet_id,
                'title' => $c->title,
                'path' => $c->full_path ?? $c->title,
            ])
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_now')
                ->label(__('feedmanager::feedmanager.actions.sync_categories'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn (): bool => self::categoryFeedConfigsQuery()->exists())
                ->schema(fn () => self::syncFormSchema())
                ->modalHeading(__('feedmanager::feedmanager.actions.sync_categories_heading'))
                ->modalDescription(__('feedmanager::feedmanager.actions.sync_categories_description'))
                ->action(function (array $data): void {
                    /** @var FeedConfig|null $config */
                    $config = FeedConfig::query()->find($data['feed_config_id'] ?? null);

                    if ($config === null || ! $config->isCategoryFeed()) {
                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.sync_categories_no_config'))
                            ->danger()
                            ->send();

                        return;
                    }

                    /** @var ShoptetCategorySyncService $service */
                    $service = app(ShoptetCategorySyncService::class);
                    $log = $service->run($config, ImportLog::TRIGGER_MANUAL);

                    if ($log->status === ImportLog::STATUS_SUCCESS) {
                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.sync_categories_done'))
                            ->body($config->last_message)
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.sync_categories_failed'))
                            ->body($log->message)
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private static function syncFormSchema(): array
    {
        $configs = self::categoryFeedConfigsQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return [
            Select::make('feed_config_id')
                ->label(__('feedmanager::feedmanager.fields.feed_config'))
                ->options($configs)
                ->default(array_key_first($configs))
                ->required()
                ->native(false),
        ];
    }

    private static function categoryFeedConfigsQuery()
    {
        return FeedConfig::query()
            ->where('format', FeedConfig::FORMAT_SHOPTET_CATEGORIES)
            ->where('is_active', true);
    }
}
