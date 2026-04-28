<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Pages\CreateFeedRule;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Pages\EditFeedRule;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Pages\ListFeedRules;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Schemas\FeedRuleForm;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Tables\FeedRulesTable;
use Adminos\Modules\Feedmanager\Models\FeedRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * @api
 */
final class FeedRuleResource extends Resource
{
    protected static ?string $model = FeedRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 65;

    public static function getModelLabel(): string
    {
        return __('feedmanager::feedmanager.feed_rules.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedmanager::feedmanager.feed_rules.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.feed_rules.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    public static function form(Schema $schema): Schema
    {
        return FeedRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeedRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedRules::route('/'),
            'create' => CreateFeedRule::route('/create'),
            'edit' => EditFeedRule::route('/{record}/edit'),
        ];
    }
}
