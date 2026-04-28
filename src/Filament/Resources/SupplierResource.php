<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages\CreateSupplier;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages\EditSupplier;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages\ListSuppliers;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Schemas\SupplierForm;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Tables\SuppliersTable;
use Adminos\Modules\Feedmanager\Models\Supplier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
