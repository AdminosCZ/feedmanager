<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\Tables;

use Adminos\Modules\Feedmanager\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->label(__('feedmanager::feedmanager.fields.image_url'))
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
                TextColumn::make('stock_quantity')
                    ->label(__('feedmanager::feedmanager.fields.stock_quantity'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('feedmanager::feedmanager.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Product::STATUS_APPROVED => 'success',
                        Product::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => __('feedmanager::feedmanager.products.status.' . $state)),
                IconColumn::make('is_b2b_allowed')
                    ->label(__('feedmanager::feedmanager.fields.is_b2b_allowed'))
                    ->boolean(),
                IconColumn::make('is_excluded')
                    ->label(__('feedmanager::feedmanager.fields.is_excluded'))
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
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
                Action::make('toggle_b2b')
                    ->label(fn (Product $record): string => $record->is_b2b_allowed
                        ? __('feedmanager::feedmanager.actions.remove_from_b2b')
                        : __('feedmanager::feedmanager.actions.add_to_b2b'))
                    ->icon(fn (Product $record): string => $record->is_b2b_allowed
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (Product $record): string => $record->is_b2b_allowed ? 'danger' : 'success')
                    ->action(fn (Product $record) => $record->update(['is_b2b_allowed' => ! $record->is_b2b_allowed])),
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
}
