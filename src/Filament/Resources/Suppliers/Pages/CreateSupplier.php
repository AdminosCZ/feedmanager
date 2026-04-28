<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\SupplierResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;
}
