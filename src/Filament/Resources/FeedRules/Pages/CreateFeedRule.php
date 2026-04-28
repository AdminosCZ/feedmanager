<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedRuleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateFeedRule extends CreateRecord
{
    protected static string $resource = FeedRuleResource::class;
}
