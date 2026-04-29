<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\Tables;

use Adminos\Modules\Feedmanager\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

final class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label(__('feedmanager::feedmanager.fields.image_short'))
                    ->square()
                    ->size(48),
                TextColumn::make('code')
                    ->label(__('feedmanager::feedmanager.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),
                TextColumn::make('name')
                    ->label(__('feedmanager::feedmanager.fields.name'))
                    ->searchable()
                    ->limit(40)
                    ->formatStateUsing(fn (Product $record): string => $record->effectiveName()),
                TextColumn::make('price_vat')
                    ->label(__('feedmanager::feedmanager.fields.price_vat'))
                    ->money(fn ($record): string => $record->currency)
                    ->sortable()
                    ->alignEnd(),

                // Stock count column — labelled as "Stav skladu" on the
                // own catalogue tab and "Sklad dodavatele" on the supplier
                // tab. Same data, different framing for the admin reading
                // the table.
                TextColumn::make('stock_quantity')
                    ->label(fn ($livewire): string => self::stockColumnLabel($livewire))
                    ->placeholder('—')
                    ->sortable()
                    ->alignEnd(),

                // Computed availability badge — what a typical (standard)
                // partner would see for this product based on the stock
                // count + default low-stock threshold (5). VIP / per-product
                // overrides happen at export time, this column is the
                // at-a-glance approximation. Tooltip on hover discloses
                // the actual source (Markstore vs Dodavatel X) + raw count
                // so the admin knows where the "Skladem" came from.
                TextColumn::make('availability_badge')
                    ->label(__('feedmanager::feedmanager.fields.availability_short'))
                    ->state(fn (Product $record): string => self::availabilityKey($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in_stock' => 'success',
                        'on_request' => 'warning',
                        'out_of_stock' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __(
                        'feedmanager::feedmanager.products.availability.' . $state,
                    ))
                    ->tooltip(fn (Product $record): string => self::availabilityTooltip($record)),

                TextColumn::make('status')
                    ->label(__('feedmanager::feedmanager.fields.status_short'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Product::STATUS_APPROVED => 'success',
                        Product::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => __('feedmanager::feedmanager.products.status.' . $state)),

                ToggleColumn::make('is_b2b_allowed')
                    ->label(__('feedmanager::feedmanager.fields.is_b2b_allowed_short'))
                    ->onColor('success')
                    ->offColor('danger')
                    ->inline()
                    // B2B toggle is only meaningful when the catalogue is
                    // headed for B2B partner export. The "Od dodavatelů"
                    // tab is about Shoptet auto-import curation, where B2B
                    // is a downstream concern.
                    ->visible(fn ($livewire): bool => self::activeTab($livewire) !== 'external'),

                TextColumn::make('updated_at')
                    ->label(__('feedmanager::feedmanager.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('feedmanager::feedmanager.fields.status'))
                    ->options([
                        Product::STATUS_PENDING => __('feedmanager::feedmanager.products.status.pending'),
                        Product::STATUS_APPROVED => __('feedmanager::feedmanager.products.status.approved'),
                        Product::STATUS_REJECTED => __('feedmanager::feedmanager.products.status.rejected'),
                    ]),
                TernaryFilter::make('is_b2b_allowed')
                    ->label(__('feedmanager::feedmanager.fields.is_b2b_allowed')),
                TernaryFilter::make('is_excluded')
                    ->label(__('feedmanager::feedmanager.fields.is_excluded')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('add_to_b2b')
                        ->label(__('feedmanager::feedmanager.actions.bulk_add_to_b2b'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->each(fn (Product $r) => $r->update(['is_b2b_allowed' => true]));
                            Notification::make()
                                ->title(__('feedmanager::feedmanager.notifications.bulk_b2b_added', ['count' => $records->count()]))
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('remove_from_b2b')
                        ->label(__('feedmanager::feedmanager.actions.bulk_remove_from_b2b'))
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(function (Collection $records): void {
                            $records->each(fn (Product $r) => $r->update(['is_b2b_allowed' => false]));
                            Notification::make()
                                ->title(__('feedmanager::feedmanager.notifications.bulk_b2b_removed', ['count' => $records->count()]))
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('approve')
                        ->label(__('feedmanager::feedmanager.actions.bulk_approve'))
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn (Product $r) => $r->update(['status' => Product::STATUS_APPROVED]));
                            Notification::make()
                                ->title(__('feedmanager::feedmanager.notifications.bulk_approved', ['count' => $records->count()]))
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('reject')
                        ->label(__('feedmanager::feedmanager.actions.bulk_reject'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn (Product $r) => $r->update(['status' => Product::STATUS_REJECTED]));
                            Notification::make()
                                ->title(__('feedmanager::feedmanager.notifications.bulk_rejected', ['count' => $records->count()]))
                                ->warning()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Resolve the active tab from the Livewire component (ListProducts).
     * Resource pages without tabs (Edit, View) won't have it set; default
     * to "all" so the column renders normally outside the list page.
     */
    private static function activeTab(?object $livewire): string
    {
        if ($livewire === null || ! property_exists($livewire, 'activeTab')) {
            return 'all';
        }

        return $livewire->activeTab ?? 'all';
    }

    private static function stockColumnLabel(?object $livewire): string
    {
        return match (self::activeTab($livewire)) {
            'external' => __('feedmanager::feedmanager.fields.supplier_stock'),
            default => __('feedmanager::feedmanager.fields.stock_status'),
        };
    }

    /**
     * Map a product to one of the partner-visible availability buckets.
     * Mirrors the default logic in B2bFeedExporter (PR 9):
     *   stock = 0           → out_of_stock
     *   stock <= 5          → on_request   (low-stock threshold default)
     *   stock > 5           → in_stock
     *   stock = null + text → unknown      (TODO: PR C — supplier mapping)
     */
    private static function availabilityKey(Product $record): string
    {
        $stock = $record->stock_quantity;

        if ($stock === null) {
            return 'unknown';
        }

        if ($stock === 0) {
            return 'out_of_stock';
        }

        if ($stock <= 5) {
            return 'on_request';
        }

        return 'in_stock';
    }

    /**
     * Disclose the source behind the partner-visible badge:
     *   "u Markstore (10 ks)"               own eshop, count known
     *   "u Velkoobchod XYZ (5 ks)"          external supplier, count known
     *   "u Velkoobchod XYZ (počet neznámý)" external, count unknown
     *
     * The supplier name itself comes from Supplier::name; is_own decides
     * whether to label it as own catalogue or external.
     */
    private static function availabilityTooltip(Product $record): string
    {
        $supplier = $record->supplier;

        $sourceLabel = $supplier === null
            ? __('feedmanager::feedmanager.products.source.unknown')
            : ($supplier->is_own
                ? __('feedmanager::feedmanager.products.source.own', ['name' => $supplier->name])
                : __('feedmanager::feedmanager.products.source.supplier', ['name' => $supplier->name]));

        $countLabel = $record->stock_quantity === null
            ? __('feedmanager::feedmanager.products.source.count_unknown')
            : __('feedmanager::feedmanager.products.source.count_pieces', ['count' => $record->stock_quantity]);

        return $sourceLabel . ' — ' . $countLabel;
    }
}
