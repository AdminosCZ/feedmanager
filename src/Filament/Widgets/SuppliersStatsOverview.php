<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Widgets;

use Adminos\Modules\Feedmanager\Filament\Resources\ProductResource;
use Adminos\Modules\Feedmanager\Filament\Resources\SupplierCategoryResource;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\SupplierCategory;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Six-card overview that sits on top of the Dodavatelé list (Napojse-style).
 * Each stat is a click-through to the relevant filtered surface so the admin
 * can drill down without context-switching through the menu.
 */
class SuppliersStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getColumns(): int
    {
        return 6;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.approved_products'),
                (string) Product::query()->where('status', Product::STATUS_APPROVED)->count(),
            )
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url(ProductResource::getUrl('index', ['tableFilters[status][value]' => Product::STATUS_APPROVED])),

            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.new_products'),
                (string) Product::query()
                    ->where('imported_at', '>=', now()->subDays(7))
                    ->count(),
            )
                ->description(__('feedmanager::feedmanager.suppliers_overview.new_products_hint'))
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary')
                ->url(ProductResource::getUrl('index')),

            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.pending_products'),
                (string) Product::query()->where('status', Product::STATUS_PENDING)->count(),
            )
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color('warning')
                ->url(ProductResource::getUrl('index', ['tableFilters[status][value]' => Product::STATUS_PENDING])),

            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.total_products'),
                (string) Product::query()->count(),
            )
                ->descriptionIcon('heroicon-m-cube')
                ->color('gray')
                ->url(ProductResource::getUrl('index')),

            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.unmapped_categories'),
                (string) SupplierCategory::query()
                    ->whereDoesntHave('mapping')
                    ->whereHas('supplier', fn ($q) => $q->where('is_own', false))
                    ->count(),
            )
                ->descriptionIcon('heroicon-m-rectangle-group')
                ->color(self::unmappedColor())
                ->url(SupplierCategoryResource::getUrl('index', ['tableFilters[has_mapping][value]' => '0'])),

            self::lastSyncStat(),
        ];
    }

    private static function unmappedColor(): string
    {
        $count = SupplierCategory::query()
            ->whereDoesntHave('mapping')
            ->whereHas('supplier', fn ($q) => $q->where('is_own', false))
            ->count();

        return $count > 0 ? 'warning' : 'success';
    }

    private static function lastSyncStat(): Stat
    {
        $log = ImportLog::query()
            ->where('status', ImportLog::STATUS_SUCCESS)
            ->latest('finished_at')
            ->first();

        $value = $log?->finished_at?->translatedFormat('d.m.y H:i')
            ?? __('feedmanager::feedmanager.suppliers_overview.last_sync_never');

        $stat = Stat::make(
            __('feedmanager::feedmanager.suppliers_overview.last_sync'),
            $value,
        )
            ->descriptionIcon('heroicon-m-arrow-path')
            ->color($log !== null ? 'success' : 'gray');

        if ($log !== null) {
            $stat->description($log->finished_at->diffForHumans());
        }

        return $stat;
    }
}
