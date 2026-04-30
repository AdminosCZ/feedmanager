<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshopResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOwnEshops extends ListRecords
{
    protected static string $resource = OwnEshopResource::class;

    public function getTitle(): string
    {
        // Klient typicky má jediný vlastní eshop — jednotné číslo se
        // čte přirozeněji než „Moje e-shopy".
        return __('feedmanager::feedmanager.own_eshops.navigation_label');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
