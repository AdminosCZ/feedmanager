<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Pages\CreateFeedConfig;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Pages\EditFeedConfig;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Pages\ListFeedConfigs;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Schemas\FeedConfigForm;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Tables\FeedConfigsTable;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * @api
 */
final class FeedConfigResource extends Resource
{
    protected static ?string $model = FeedConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 60;

    /**
     * Feedy patří pod konkrétního dodavatele/eshop — admin se k nim
     * dostane přes detail (seznam feedů + sync action). Sekce „Vstupní
     * feedy" jako samostatný top-level item v navigaci jenom mate.
     * Resource zůstává registrovaný (kvůli URL pro Edit/Create akce
     * volané z jiných ploch), jen ho schováme z navigace.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('feedmanager::feedmanager.feed_configs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedmanager::feedmanager.feed_configs.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.feed_configs.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    public static function form(Schema $schema): Schema
    {
        return FeedConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeedConfigsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedConfigs::route('/'),
            'create' => CreateFeedConfig::route('/create'),
            'edit' => EditFeedConfig::route('/{record}/edit'),
        ];
    }
}
