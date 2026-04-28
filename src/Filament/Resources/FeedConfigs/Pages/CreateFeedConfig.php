<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateFeedConfig extends CreateRecord
{
    protected static string $resource = FeedConfigResource::class;
}
