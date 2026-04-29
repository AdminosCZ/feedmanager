<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ViewColumn::make('identity')
                    ->label(__('feedmanager::feedmanager.fields.supplier_name'))
                    ->view('feedmanager::tables.supplier-identity')
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%"))
                    ->sortable(['is_own', 'name']),

                ViewColumn::make('thumbnails')
                    ->label(__('feedmanager::feedmanager.fields.product_preview'))
                    ->view('feedmanager::tables.supplier-thumbnails'),

                TextColumn::make('approved_products_count')
                    ->label(__('feedmanager::feedmanager.fields.approved_products_count'))
                    ->alignEnd()
                    ->sortable()
                    ->numeric(),

                TextColumn::make('pending_products_count')
                    ->label(__('feedmanager::feedmanager.fields.pending_products_count'))
                    ->alignEnd()
                    ->sortable()
                    ->numeric(),

                TextColumn::make('total_products_count')
                    ->label(__('feedmanager::feedmanager.fields.total_products_count'))
                    ->alignEnd()
                    ->sortable()
                    ->numeric(),

                TextColumn::make('supplemental_feeds_count')
                    ->label(__('feedmanager::feedmanager.fields.supplemental_feeds_count'))
                    ->alignEnd()
                    ->sortable()
                    ->numeric(),

                TextColumn::make('updated_at')
                    ->label(__('feedmanager::feedmanager.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('feedmanager::feedmanager.actions.details'))
                    ->icon('heroicon-m-eye'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
