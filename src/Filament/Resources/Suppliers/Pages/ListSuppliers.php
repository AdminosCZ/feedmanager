<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
