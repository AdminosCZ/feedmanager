<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshopResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOwnEshops extends ListRecords
{
    protected static string $resource = OwnEshopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
