<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class FeedRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('priority')
                    ->label(__('feedmanager::feedmanager.fields.feed_rule_priority'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('name')
                    ->label(__('feedmanager::feedmanager.fields.feed_rule_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('feedConfig.name')
                    ->label(__('feedmanager::feedmanager.fields.feed_config'))
                    ->placeholder(__('feedmanager::feedmanager.feed_rules.placeholders.any_feed'))
                    ->toggleable(),
                TextColumn::make('supplier.name')
                    ->label(__('feedmanager::feedmanager.fields.supplier'))
                    ->placeholder(__('feedmanager::feedmanager.feed_rules.placeholders.any_supplier'))
                    ->toggleable(),
                TextColumn::make('field')
                    ->label(__('feedmanager::feedmanager.fields.feed_rule_field'))
                    ->fontFamily('mono'),
                TextColumn::make('condition_op')
                    ->label(__('feedmanager::feedmanager.fields.feed_rule_condition_op'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('action')
                    ->label(__('feedmanager::feedmanager.fields.feed_rule_action'))
                    ->badge()
                    ->color('primary'),
                IconColumn::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('priority')
            ->filters([
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
