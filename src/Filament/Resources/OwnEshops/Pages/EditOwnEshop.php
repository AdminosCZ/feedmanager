<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshopResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditOwnEshop extends EditRecord
{
    protected static string $resource = OwnEshopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
