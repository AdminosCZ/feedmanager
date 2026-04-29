<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Schemas\SupplierInfolist;
use Adminos\Modules\Feedmanager\Filament\Resources\SupplierResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

final class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    public function infolist(Schema $schema): Schema
    {
        return SupplierInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
