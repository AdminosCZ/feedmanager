<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListFeedConfigs extends ListRecords
{
    protected static string $resource = FeedConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
