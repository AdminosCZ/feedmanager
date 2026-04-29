<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ProductResource;
use Adminos\Modules\Feedmanager\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

final class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    /**
     * Product list has wide tables (code, name, supplier, price, stock,
     * category, status badges, …). Default 7xl is too narrow on most
     * monitors. Match the screen width while keeping responsive padding.
     */
    public function getMaxContentWidth(): Width | string | null
    {
        return Width::ScreenTwoExtraLarge;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Split the product list into "own catalogue" vs "from suppliers" vs
     * "everything". The two flows have different lifecycles (own = ready
     * for B2B partner export, supplier = curated for client's Shoptet
     * import) and mixing them makes the table unwieldy at scale.
     */
    public function getTabs(): array
    {
        return [
            'own' => Tab::make()
                ->label(__('feedmanager::feedmanager.products.tabs.own'))
                ->icon('heroicon-o-home')
                ->badge(fn (): int => $this->countByOwnership(true))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'supplier',
                    fn (Builder $q) => $q->where('is_own', true),
                )),

            'external' => Tab::make()
                ->label(__('feedmanager::feedmanager.products.tabs.external'))
                ->icon('heroicon-o-cube')
                ->badge(fn (): int => $this->countByOwnership(false))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'supplier',
                    fn (Builder $q) => $q->where('is_own', false),
                )),

            'partners' => Tab::make()
                ->label(__('feedmanager::feedmanager.products.tabs.partners'))
                ->icon('heroicon-o-users')
                ->badge(fn (): int => $this->countForPartners())
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('is_b2b_allowed', true)
                    ->where('is_excluded', false)),

            'all' => Tab::make()
                ->label(__('feedmanager::feedmanager.products.tabs.all'))
                ->icon('heroicon-o-rectangle-stack')
                ->badge(fn (): int => Product::query()->count()),
        ];
    }

    /**
     * Count products whose supplier matches the requested ownership flag.
     * Cached lookups would be premature optimisation; counts run on each
     * tab render which is acceptable for typical eshop volumes (< 100k).
     */
    private function countByOwnership(bool $isOwn): int
    {
        return Product::query()
            ->whereHas('supplier', fn (Builder $q) => $q->where('is_own', $isOwn))
            ->count();
    }

    /**
     * Mirror B2bFeedExporter::exportableQuery() — what actually flows to
     * B2B partners. Lets the admin preview the partner-bound catalogue
     * regardless of which supplier produced each product.
     */
    private function countForPartners(): int
    {
        return Product::query()
            ->where('is_b2b_allowed', true)
            ->where('is_excluded', false)
            ->count();
    }
}
