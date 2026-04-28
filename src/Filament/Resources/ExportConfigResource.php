<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Pages\CreateExportConfig;
use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Pages\EditExportConfig;
use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Pages\ListExportConfigs;
use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Schemas\ExportConfigForm;
use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Tables\ExportConfigsTable;
use Adminos\Modules\Feedmanager\Models\ExportConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * @api
 */
final class ExportConfigResource extends Resource
{
    protected static ?string $model = ExportConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 70;

    public static function getModelLabel(): string
    {
        return __('feedmanager::feedmanager.export_configs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedmanager::feedmanager.export_configs.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.export_configs.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    public static function form(Schema $schema): Schema
    {
        return ExportConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExportConfigsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExportConfigs::route('/'),
            'create' => CreateExportConfig::route('/create'),
            'edit' => EditExportConfig::route('/{record}/edit'),
        ];
    }
}
