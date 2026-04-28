<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateExportConfig extends CreateRecord
{
    protected static string $resource = ExportConfigResource::class;
}
