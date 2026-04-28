<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Tables;

use Adminos\Modules\Feedmanager\Models\ExportConfig;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class ExportConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('feedmanager::feedmanager.fields.export_config_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('feedmanager::feedmanager.fields.slug'))
                    ->fontFamily('mono')
                    ->copyable(),
                TextColumn::make('format')
                    ->label(__('feedmanager::feedmanager.fields.format'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('feedmanager::feedmanager.formats.' . $state . '_out')),
                IconColumn::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active'))
                    ->boolean(),
                TextColumn::make('access_hash')
                    ->label(__('feedmanager::feedmanager.fields.access_hash'))
                    ->fontFamily('mono')
                    ->limit(16)
                    ->copyable()
                    ->tooltip(fn (ExportConfig $record): string => $record->access_hash)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('export_logs_count')
                    ->label(__('feedmanager::feedmanager.fields.export_logs_count'))
                    ->counts('exportLogs')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('feedmanager::feedmanager.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active')),
                SelectFilter::make('format')
                    ->label(__('feedmanager::feedmanager.fields.format'))
                    ->options([
                        ExportConfig::FORMAT_SHOPTET => __('feedmanager::feedmanager.formats.shoptet_out'),
                        ExportConfig::FORMAT_HEUREKA => __('feedmanager::feedmanager.formats.heureka_out'),
                        ExportConfig::FORMAT_GLAMI => __('feedmanager::feedmanager.formats.glami_out'),
                        ExportConfig::FORMAT_ZBOZI => __('feedmanager::feedmanager.formats.zbozi_out'),
                    ]),
            ])
            ->recordActions([
                Action::make('regenerate_hash')
                    ->label(__('feedmanager::feedmanager.actions.regenerate_hash'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('feedmanager::feedmanager.actions.regenerate_hash_confirm_heading'))
                    ->modalDescription(__('feedmanager::feedmanager.actions.regenerate_hash_confirm'))
                    ->action(fn (ExportConfig $record) => $record->regenerateHash()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
