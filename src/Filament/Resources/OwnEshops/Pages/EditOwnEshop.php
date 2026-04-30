<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshopResource;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

final class EditOwnEshop extends EditRecord
{
    protected static string $resource = OwnEshopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_all_imports')
                ->label(__('feedmanager::feedmanager.actions.run_all_imports'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn (Supplier $record): bool => $record->feedConfigs()
                    ->where('is_active', true)
                    ->exists())
                ->requiresConfirmation()
                ->modalHeading(__('feedmanager::feedmanager.actions.run_all_imports_heading'))
                ->modalDescription(__('feedmanager::feedmanager.actions.run_all_imports_description'))
                ->action(function (Supplier $record, FeedImporter $importer): void {
                    self::runAllForSupplier($record, $importer);
                }),
            DeleteAction::make(),
        ];
    }

    public static function runAllForSupplier(Supplier $supplier, FeedImporter $importer): void
    {
        $configs = $supplier->feedConfigs()->where('is_active', true)->get();

        if ($configs->isEmpty()) {
            Notification::make()
                ->title(__('feedmanager::feedmanager.notifications.run_all_no_feeds'))
                ->warning()
                ->send();

            return;
        }

        $ok = 0;
        $failed = 0;
        $messages = [];

        foreach ($configs as $config) {
            try {
                $log = $importer->run($config, ImportLog::TRIGGER_MANUAL);

                if ($log->status === ImportLog::STATUS_SUCCESS) {
                    ++$ok;
                } else {
                    ++$failed;
                    $messages[] = sprintf('%s: %s', $config->name, $log->message ?? '?');
                }
            } catch (Throwable $e) {
                ++$failed;
                $messages[] = sprintf('%s: %s', $config->name, $e->getMessage());
            }
        }

        if ($failed === 0) {
            Notification::make()
                ->title(__('feedmanager::feedmanager.notifications.run_all_done', [
                    'ok' => $ok,
                    'total' => $configs->count(),
                ]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('feedmanager::feedmanager.notifications.run_all_partial', [
                    'ok' => $ok,
                    'failed' => $failed,
                    'total' => $configs->count(),
                ]))
                ->body(implode("\n", $messages))
                ->warning()
                ->send();
        }
    }
}
