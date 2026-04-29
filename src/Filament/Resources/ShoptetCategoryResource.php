<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages\ListShoptetCategories;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

/**
 * Read-only resource. Categories are a snapshot of the upstream Shoptet
 * eshop — any manual edit here would be silently overwritten on the next
 * sync, so neither the form nor the table is exposed. The single index
 * page renders the catalogue as a tree; the only mutation is the "Sync
 * categories" action in the page header.
 *
 * @api
 */
final class ShoptetCategoryResource extends Resource
{
    protected static ?string $model = ShoptetCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 75;

    public static function getModelLabel(): string
    {
        return __('feedmanager::feedmanager.shoptet_categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedmanager::feedmanager.shoptet_categories.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.shoptet_categories.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ShoptetCategory::query()->where('is_orphaned', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShoptetCategories::route('/'),
        ];
    }
}
