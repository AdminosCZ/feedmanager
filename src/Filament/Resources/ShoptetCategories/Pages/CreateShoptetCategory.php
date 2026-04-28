<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategoryResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateShoptetCategory extends CreateRecord
{
    protected static string $resource = ShoptetCategoryResource::class;
}
