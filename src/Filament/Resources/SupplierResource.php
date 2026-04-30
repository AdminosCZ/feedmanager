<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages\CreateSupplier;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages\EditSupplier;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages\ListSuppliers;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages\ViewSupplier;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\RelationManagers\FeedConfigsRelationManager;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Schemas\SupplierForm;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Tables\SuppliersTable;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * @api
 */
final class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 50;

    public static function getModelLabel(): string
    {
        return __('feedmanager::feedmanager.suppliers.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedmanager::feedmanager.suppliers.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.suppliers.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FeedConfigsRelationManager::class,
        ];
    }

    /**
     * Sekce Dodavatelé je výhradně pro **externí** zdroje (re-prodávané
     * katalogy). Vlastní eshopy mají vlastní sekci „Můj e-shop". Plus
     * agregované counts pro tabulku.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_own', false)
            ->withCount([
                'products as approved_products_count' => fn (Builder $q) => $q->where('status', Product::STATUS_APPROVED),
                'products as pending_products_count' => fn (Builder $q) => $q->where('status', Product::STATUS_PENDING),
                'products as total_products_count',
                'feedConfigs as supplemental_feeds_count' => fn (Builder $q) => $q->where(
                    fn (Builder $q2) => $q2->where('import_parameters_only', true)
                        ->orWhere('update_only_mode', true),
                ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
