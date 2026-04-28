<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\SupplierCategories\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\SupplierCategoryResource;
use Filament\Resources\Pages\ListRecords;

final class ListSupplierCategories extends ListRecords
{
    protected static string $resource = SupplierCategoryResource::class;
}
