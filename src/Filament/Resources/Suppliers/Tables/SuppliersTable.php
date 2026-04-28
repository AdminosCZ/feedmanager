<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Own eshops appear at the top of the list, then suppliers
            // alphabetically — admin's own catalogue is the most-touched row.
            ->defaultSort('is_own', 'desc')
            ->columns([
                TextColumn::make('is_own')
                    ->label(__('feedmanager::feedmanager.fields.supplier_kind'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('feedmanager::feedmanager.suppliers.kind.own')
                        : __('feedmanager::feedmanager.suppliers.kind.external'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('name')
                    ->label(__('feedmanager::feedmanager.fields.supplier_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('feedmanager::feedmanager.fields.slug'))
                    ->fontFamily('mono')
                    ->copyable(),
                TextColumn::make('feed_configs_count')
                    ->label(__('feedmanager::feedmanager.fields.feed_configs_count'))
                    ->counts('feedConfigs')
                    ->alignEnd(),
                TextColumn::make('products_count')
                    ->label(__('feedmanager::feedmanager.fields.products_count'))
                    ->counts('products')
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('feedmanager::feedmanager.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_own')
                    ->label(__('feedmanager::feedmanager.fields.supplier_kind'))
                    ->options([
                        '1' => __('feedmanager::feedmanager.suppliers.kind.own'),
                        '0' => __('feedmanager::feedmanager.suppliers.kind.external'),
                    ]),
                TernaryFilter::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
