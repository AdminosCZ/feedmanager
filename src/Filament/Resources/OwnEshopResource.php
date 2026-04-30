<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages\CreateOwnEshop;
use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages\EditOwnEshop;
use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages\ListOwnEshops;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\RelationManagers\FeedConfigsRelationManager;
use Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Tables\SuppliersTable;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\Supplier;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Own e-shop section — same `Supplier` model as `SupplierResource` but
 * scoped to `is_own=true`. Vlastní eshop konceptuálně není dodavatel
 * (klient ho neprodává, vlastní ho), takže má vlastní top-level
 * navigaci, jiný copy a v Create/Edit formu je `is_own` vždy true.
 *
 * @api
 */
final class OwnEshopResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 45;

    public static function getModelLabel(): string
    {
        return __('feedmanager::feedmanager.own_eshops.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedmanager::feedmanager.own_eshops.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.own_eshops.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    public static function form(Schema $schema): Schema
    {
        // Inline a trimmed-down form: no `is_own` toggle (forced via Hidden),
        // no `publish_to_shoptet` (irrelevant for an own eshop).
        return $schema->components([
            Hidden::make('is_own')->default(true),

            TextInput::make('name')
                ->label(__('feedmanager::feedmanager.fields.supplier_name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                    if (! filled($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->label(__('feedmanager::feedmanager.fields.slug'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(64)
                ->helperText(__('feedmanager::feedmanager.helpers.slug')),
            Toggle::make('is_active')
                ->label(__('feedmanager::feedmanager.fields.is_active'))
                ->default(true),
            Textarea::make('notes')
                ->label(__('feedmanager::feedmanager.fields.notes'))
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    /**
     * Same aggregations as SupplierResource but scoped to own-eshops.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_own', true)
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

    public static function getRelations(): array
    {
        return [
            FeedConfigsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        // Edit zabírá `/admin/own-eshops/{record}` — žádná samostatná
        // mezi-stránka „view". Edit page má form + feed RelationManager +
        // header akce „Spustit všechny importy" → jeden focal point.
        return [
            'index' => ListOwnEshops::route('/'),
            'create' => CreateOwnEshop::route('/create'),
            'edit' => EditOwnEshop::route('/{record}'),
        ];
    }
}
