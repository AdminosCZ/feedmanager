<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigResource;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Services\FeedDownloader;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

final class EditFeedConfig extends EditRecord
{
    protected static string $resource = FeedConfigResource::class;

    /**
     * Save / Cancel patří do hlavičky vedle ostatních header actions —
     * jeden focal point místo bottom-form-bar mimo zorné pole.
     *
     * @return array<int, mixed>
     */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label(__('feedmanager::feedmanager.actions.save'))
                ->color('success')
                ->icon('heroicon-m-check'),
            Action::make('test_connection')
                ->label(__('feedmanager::feedmanager.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function (FeedDownloader $downloader): void {
                    /** @var FeedConfig $record */
                    $record = $this->record;

                    try {
                        $payload = $downloader->download($record);
                        $size = number_format(strlen($payload) / 1024, 1, '.', ' ');
                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.test_connection_ok_title'))
                            ->body(__('feedmanager::feedmanager.notifications.test_connection_ok_body', [
                                'size' => $size,
                            ]))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.test_connection_failed_title'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('run_import')
                ->label(__('feedmanager::feedmanager.actions.run_import'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('feedmanager::feedmanager.actions.run_import_confirm_heading'))
                ->action(function (FeedImporter $importer): void {
                    /** @var FeedConfig $record */
                    $record = $this->record;

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

                    $this->refreshFormData(['last_status', 'last_run_at']);
                }),
            $this->getCancelFormAction()
                ->label(__('feedmanager::feedmanager.actions.back'))
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray'),
            DeleteAction::make(),
        ];
    }
}
