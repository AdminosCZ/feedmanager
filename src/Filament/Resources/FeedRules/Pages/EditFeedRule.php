<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditFeedRule extends EditRecord
{
    protected static string $resource = FeedRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
