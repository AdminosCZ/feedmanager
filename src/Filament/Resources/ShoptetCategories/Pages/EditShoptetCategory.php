<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditShoptetCategory extends EditRecord
{
    protected static string $resource = ShoptetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
