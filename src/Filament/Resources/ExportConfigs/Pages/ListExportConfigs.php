<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListExportConfigs extends ListRecords
{
    protected static string $resource = ExportConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
