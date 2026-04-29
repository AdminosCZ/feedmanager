<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshopResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateOwnEshop extends CreateRecord
{
    protected static string $resource = OwnEshopResource::class;
}
