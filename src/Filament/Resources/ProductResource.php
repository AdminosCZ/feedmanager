<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\Products\Pages\CreateProduct;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\Pages\EditProduct;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\Pages\ListProducts;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\Pages\ViewProduct;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\RelationManagers\ParametersRelationManager;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\Schemas\ProductForm;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\Tables\ProductsTable;
use Adminos\Modules\Feedmanager\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * @api
 */
final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('feedmanager::feedmanager.products.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedmanager::feedmanager.products.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.products.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ImagesRelationManager::class,
            ParametersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
