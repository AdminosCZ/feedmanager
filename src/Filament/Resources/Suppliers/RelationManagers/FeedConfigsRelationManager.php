<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\RelationManagers;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Schemas\FeedConfigForm;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

/**
 * Inline management feedů na detailu dodavatele / vlastního eshopu.
 * Admin tady přidá nový feed, edituje URL/format, spustí import nebo
 * smaže — bez nutnosti opouštět kontext supplieru.
 */
final class FeedConfigsRelationManager extends RelationManager
{
    protected static string $relationship = 'feedConfigs';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('feedmanager::feedmanager.feed_configs.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return FeedConfigForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('feedmanager::feedmanager.fields.feed_config_name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('format')
                    ->label(__('feedmanager::feedmanager.fields.format'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('feedmanager::feedmanager.formats.'.$state)),
                IconColumn::make('is_active')
                    ->label(__('feedmanager::feedmanager.fields.is_active'))
                    ->boolean(),
                IconColumn::make('auto_update')
                    ->label(__('feedmanager::feedmanager.fields.auto_update_short'))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('last_status')
                    ->label(__('feedmanager::feedmanager.fields.last_status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        FeedConfig::STATUS_SUCCESS => 'success',
                        FeedConfig::STATUS_FAILED => 'danger',
                        FeedConfig::STATUS_RUNNING => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('last_run_at')
                    ->label(__('feedmanager::feedmanager.fields.last_run_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('feedmanager::feedmanager.actions.add_feed'))
                    ->modalWidth('5xl'),
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
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('filament-actions::edit.single.label'))
                    ->color('gray')
                    ->modalWidth('5xl'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('filament-actions::delete.single.label')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
