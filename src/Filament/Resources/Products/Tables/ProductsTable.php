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
    /**
     * Tier-level low-stock thresholds — used for the "Pro partnery" tab
     * preview where no single partner is selected. Mirrors
     * `Partner::TIER_DEFAULT_THRESHOLDS` in feedmanager-pro; kept here so
     * the base package doesn't take a hard dependency on PRO.
     */
    private const TIER_DEFAULT_THRESHOLDS = [
        'standard' => 5,
        'vip' => 2,
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label(__('feedmanager::feedmanager.fields.image_short'))
                    ->square()
                    ->size(48)
                    ->toggleable(),
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
                    ->alignEnd()
                    ->toggleable(),

                // Stock count column — labelled as "Stav skladu" on the
                // own catalogue tab and "Sklad dodavatele" on the supplier
                // tab. Same data, different framing for the admin reading
                // the table.
                TextColumn::make('stock_quantity')
                    ->label(fn ($livewire): string => self::stockColumnLabel($livewire))
                    ->placeholder('—')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                // E-shop availability (no B2B threshold) — what the client's
                // own front-end shows to its end customers. Hidden on the
                // "Pro partnery" tab where the per-tier preview takes over.
                TextColumn::make('availability_eshop')
                    ->label(__('feedmanager::feedmanager.fields.availability_short'))
                    ->state(fn (Product $record): string => self::eshopAvailabilityKey($record))
                    ->badge()
                    ->color(fn (string $state): string => self::availabilityColor($state))
                    ->formatStateUsing(fn (string $state): string => __(
                        'feedmanager::feedmanager.products.availability.'.$state,
                    ))
                    ->tooltip(fn (Product $record): string => self::eshopTooltip($record))
                    ->toggleable()
                    ->visible(fn ($livewire): bool => self::activeTab($livewire) !== 'partners'),

                // Standard partner preview — applies tier-default low-stock
                // threshold (5 ks) plus per-product floor.
                TextColumn::make('availability_standard')
                    ->label(__('feedmanager::feedmanager.partners.tier.standard'))
                    ->state(fn (Product $record): string => self::partnerAvailabilityKey($record, 'standard'))
                    ->badge()
                    ->color(fn (string $state): string => self::availabilityColor($state))
                    ->formatStateUsing(fn (string $state): string => __(
                        'feedmanager::feedmanager.products.availability.'.$state,
                    ))
                    ->tooltip(fn (Product $record): string => self::partnerTooltip($record, 'standard'))
                    ->toggleable()
                    ->visible(fn ($livewire): bool => self::activeTab($livewire) === 'partners'),

                // VIP partner preview — tier-default threshold is lower
                // (2 ks), so VIP can see smaller stocks as "Skladem".
                TextColumn::make('availability_vip')
                    ->label(__('feedmanager::feedmanager.partners.tier.vip'))
                    ->state(fn (Product $record): string => self::partnerAvailabilityKey($record, 'vip'))
                    ->badge()
                    ->color(fn (string $state): string => self::availabilityColor($state))
                    ->formatStateUsing(fn (string $state): string => __(
                        'feedmanager::feedmanager.products.availability.'.$state,
                    ))
                    ->tooltip(fn (Product $record): string => self::partnerTooltip($record, 'vip'))
                    ->toggleable()
                    ->visible(fn ($livewire): bool => self::activeTab($livewire) === 'partners'),

                // Approval status — irrelevant on the "Pro partnery" tab
                // where products are already curated for B2B export.
                TextColumn::make('status')
                    ->label(__('feedmanager::feedmanager.fields.status_short'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Product::STATUS_APPROVED => 'success',
                        Product::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => __('feedmanager::feedmanager.products.status.'.$state))
                    ->toggleable()
                    ->visible(fn ($livewire): bool => self::activeTab($livewire) !== 'partners'),

                ToggleColumn::make('is_b2b_allowed')
                    ->label(__('feedmanager::feedmanager.fields.is_b2b_allowed_short'))
                    ->onColor('success')
                    ->offColor('danger')
                    ->inline()
                    ->toggleable()
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

    private static function availabilityColor(string $state): string
    {
        return match ($state) {
            'in_stock' => 'success',
            'on_request' => 'warning',
            'out_of_stock' => 'danger',
            default => 'gray',
        };
    }

    /**
     * E-shop view: what the client's own front-end shows to its end
     * customers. NO B2B threshold applied.
     *
     *   stock = 0  → out_of_stock
     *   stock > 0  → in_stock
     *   stock null + availability text → in_stock (text-only feeds)
     *   stock null + no text           → unknown
     */
    private static function eshopAvailabilityKey(Product $record): string
    {
        $stock = $record->stock_quantity;

        if ($stock === null) {
            return ($record->availability !== null && $record->availability !== '')
                ? 'in_stock'
                : 'unknown';
        }

        return $stock > 0 ? 'in_stock' : 'out_of_stock';
    }

    /**
     * Partner-tier view: applies the tier's default low-stock threshold
     * plus the per-product floor (max of the two), mirrors the logic in
     * B2bFeedExporter::resolveStockVisibility().
     */
    private static function partnerAvailabilityKey(Product $record, string $tier): string
    {
        $stock = $record->stock_quantity;

        if ($stock === null) {
            return 'unknown';
        }

        if ($stock <= 0) {
            return 'out_of_stock';
        }

        $tierThreshold = self::TIER_DEFAULT_THRESHOLDS[$tier] ?? 0;
        $effective = max($tierThreshold, (int) ($record->b2b_low_stock_threshold ?? 0));

        if ($effective > 0 && $stock <= $effective) {
            return 'on_request';
        }

        return 'in_stock';
    }

    /**
     * Tooltip on the e-shop availability badge — discloses real source +
     * raw stock count (no threshold games on this tab).
     */
    private static function eshopTooltip(Product $record): string
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

        return $sourceLabel.' — '.$countLabel;
    }

    /**
     * Tooltip on the partner-tier badge — explains why the tier sees what
     * it sees (real count + applied threshold).
     */
    private static function partnerTooltip(Product $record, string $tier): string
    {
        $tierThreshold = self::TIER_DEFAULT_THRESHOLDS[$tier] ?? 0;
        $effective = max($tierThreshold, (int) ($record->b2b_low_stock_threshold ?? 0));

        $countLabel = $record->stock_quantity === null
            ? __('feedmanager::feedmanager.products.source.count_unknown')
            : __('feedmanager::feedmanager.products.source.count_pieces', ['count' => $record->stock_quantity]);

        return __('feedmanager::feedmanager.products.partner_tooltip', [
            'count' => $countLabel,
            'threshold' => $effective,
        ]);
    }
}
