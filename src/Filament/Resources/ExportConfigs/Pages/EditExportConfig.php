<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditExportConfig extends EditRecord
{
    protected static string $resource = ExportConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate_hash')
                ->label(__('feedmanager::feedmanager.actions.regenerate_hash'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('feedmanager::feedmanager.actions.regenerate_hash_confirm_heading'))
                ->modalDescription(__('feedmanager::feedmanager.actions.regenerate_hash_confirm'))
                ->action(function (): void {
                    $this->record->regenerateHash();
                    $this->refreshFormData(['access_hash']);
                }),
            DeleteAction::make(),
        ];
    }
}
