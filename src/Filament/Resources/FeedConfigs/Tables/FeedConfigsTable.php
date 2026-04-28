<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Tables;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Throwable;

final class FeedConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('feedmanager::feedmanager.fields.feed_config_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label(__('feedmanager::feedmanager.fields.supplier'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('format')
                    ->label(__('feedmanager::feedmanager.fields.format'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('feedmanager::feedmanager.formats.' . $state)),
                IconColumn::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active'))
                    ->boolean(),
                IconColumn::make('auto_update')
                    ->label(__('feedmanager::feedmanager.fields.auto_update'))
                    ->boolean(),
                TextColumn::make('last_status')
                    ->label(__('feedmanager::feedmanager.fields.last_status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        FeedConfig::STATUS_SUCCESS => 'success',
                        FeedConfig::STATUS_FAILED => 'danger',
                        FeedConfig::STATUS_RUNNING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('last_run_at')
                    ->label(__('feedmanager::feedmanager.fields.last_run_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active')),
                TernaryFilter::make('auto_update')
                    ->label(__('feedmanager::feedmanager.fields.auto_update')),
                SelectFilter::make('format')
                    ->label(__('feedmanager::feedmanager.fields.format'))
                    ->options([
                        FeedConfig::FORMAT_HEUREKA => __('feedmanager::feedmanager.formats.heureka'),
                        FeedConfig::FORMAT_GOOGLE => __('feedmanager::feedmanager.formats.google'),
                        FeedConfig::FORMAT_SHOPTET => __('feedmanager::feedmanager.formats.shoptet'),
                        FeedConfig::FORMAT_ZBOZI => __('feedmanager::feedmanager.formats.zbozi'),
                        FeedConfig::FORMAT_CUSTOM => __('feedmanager::feedmanager.formats.custom'),
                    ]),
            ])
            ->recordActions([
                Action::make('run_import')
                    ->label(__('feedmanager::feedmanager.actions.run_import'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading(__('feedmanager::feedmanager.actions.run_import_confirm_heading'))
                    ->modalDescription(fn (FeedConfig $record): string => __(
                        'feedmanager::feedmanager.actions.run_import_confirm',
                        ['url' => $record->source_url],
                    ))
                    ->action(function (FeedConfig $record, FeedImporter $importer): void {
                        try {
                            $log = $importer->run($record, ImportLog::TRIGGER_MANUAL);
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title(__('feedmanager::feedmanager.notifications.import_failed_title'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($log->status === ImportLog::STATUS_SUCCESS) {
                            Notification::make()
                                ->title(__('feedmanager::feedmanager.notifications.import_done_title'))
                                ->body(__('feedmanager::feedmanager.notifications.import_done_body', [
                                    'found' => $log->products_found,
                                    'new' => $log->products_new,
                                    'updated' => $log->products_updated,
                                    'failed' => $log->products_failed,
                                ]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('feedmanager::feedmanager.notifications.import_failed_title'))
                                ->body((string) $log->message)
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
