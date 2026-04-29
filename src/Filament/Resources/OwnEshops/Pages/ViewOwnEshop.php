<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshopResource;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Schemas\SupplierInfolist;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

final class ViewOwnEshop extends ViewRecord
{
    protected static string $resource = OwnEshopResource::class;

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
