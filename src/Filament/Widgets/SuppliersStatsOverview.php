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
use Illuminate\Database\Eloquent\Builder;

/**
 * Six-card overview that sits on top of the Dodavatelé list. Stats are scoped
 * to **external** suppliers only (`is_own=false`) — vlastní eshop má vlastní
 * sekci s vlastním widgetem. Each stat is a click-through to the relevant
 * filtered surface.
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
                (string) self::externalProducts()
                    ->where('status', Product::STATUS_APPROVED)->count(),
            )
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url(ProductResource::getUrl('index', ['tableFilters[status][value]' => Product::STATUS_APPROVED])),

            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.new_products'),
                (string) self::externalProducts()
                    ->where('imported_at', '>=', now()->subDays(7))
                    ->count(),
            )
                ->description(__('feedmanager::feedmanager.suppliers_overview.new_products_hint'))
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary')
                ->url(ProductResource::getUrl('index')),

            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.pending_products'),
                (string) self::externalProducts()
                    ->where('status', Product::STATUS_PENDING)->count(),
            )
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color('warning')
                ->url(ProductResource::getUrl('index', ['tableFilters[status][value]' => Product::STATUS_PENDING])),

            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.total_products'),
                (string) self::externalProducts()->count(),
            )
                ->descriptionIcon('heroicon-m-cube')
                ->color('gray')
                ->url(ProductResource::getUrl('index')),

            Stat::make(
                __('feedmanager::feedmanager.suppliers_overview.unmapped_categories'),
                (string) self::unmappedCount(),
            )
                ->descriptionIcon('heroicon-m-rectangle-group')
                ->color(self::unmappedCount() > 0 ? 'warning' : 'success')
                ->url(SupplierCategoryResource::getUrl('index', ['tableFilters[has_mapping][value]' => '0'])),

            self::lastSyncStat(),
        ];
    }

    private static function externalProducts(): Builder
    {
        return Product::query()->whereHas('supplier', fn (Builder $q) => $q->where('is_own', false));
    }

    private static function unmappedCount(): int
    {
        return SupplierCategory::query()
            ->whereDoesntHave('mapping')
            ->whereHas('supplier', fn (Builder $q) => $q->where('is_own', false))
            ->count();
    }

    private static function lastSyncStat(): Stat
    {
        $log = ImportLog::query()
            ->where('status', ImportLog::STATUS_SUCCESS)
            ->whereHas(
                'feedConfig.supplier',
                fn (Builder $q) => $q->where('is_own', false),
            )
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
