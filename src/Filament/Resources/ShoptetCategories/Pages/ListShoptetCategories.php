<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListShoptetCategories extends ListRecords
{
    protected static string $resource = ShoptetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
