<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages\EditOwnEshop;
use Adminos\Modules\Feedmanager\Filament\Resources\SupplierResource;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

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
                    EditOwnEshop::runAllForSupplier($record, $importer);
                }),
            DeleteAction::make(),
        ];
    }
}
