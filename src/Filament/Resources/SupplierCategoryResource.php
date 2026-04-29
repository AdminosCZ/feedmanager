<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\SupplierCategories\Pages\ListSupplierCategories;
use Adminos\Modules\Feedmanager\Models\CategoryMapping;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use Adminos\Modules\Feedmanager\Models\SupplierCategory;
use Adminos\Modules\Feedmanager\Services\CategoryMappingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * @api
 */
final class SupplierCategoryResource extends Resource
{
    protected static ?string $model = SupplierCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $recordTitleAttribute = 'original_path';

    protected static ?int $navigationSort = 76;

    public static function getModelLabel(): string
    {
        return __('feedmanager::feedmanager.supplier_categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedmanager::feedmanager.supplier_categories.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.supplier_categories.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    /**
     * Mapping is for the supplier→shop translation only. Own-eshop products
     * carry the shop tree paths natively, so we hide their (rare, mostly
     * legacy) supplier_categories rows from this resource entirely.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->whereHas(
            'supplier',
            fn ($q) => $q->where('is_own', false),
        );
    }

    public static function form(Schema $schema): Schema
    {
        // Only the mapping is editable inline; the supplier_category itself
        // is auto-derived from imported products and shouldn't be hand-edited.
        return $schema->components([
            Select::make('mapping.shoptet_category_id')
                ->label(__('feedmanager::feedmanager.fields.shoptet_category'))
                ->options(fn (): array => ShoptetCategory::query()
                    ->orderBy('full_path')
                    ->pluck('full_path', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->placeholder(__('feedmanager::feedmanager.supplier_categories.no_mapping')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('original_path')
            ->columns([
                TextColumn::make('supplier.name')
                    ->label(__('feedmanager::feedmanager.fields.supplier'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('original_path')
                    ->label(__('feedmanager::feedmanager.fields.supplier_category_path'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('product_count')
                    ->label(__('feedmanager::feedmanager.fields.product_count'))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('mapping.shoptetCategory.full_path')
                    ->label(__('feedmanager::feedmanager.fields.shoptet_category'))
                    ->placeholder(__('feedmanager::feedmanager.supplier_categories.unmapped'))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label(__('feedmanager::feedmanager.fields.supplier'))
                    ->relationship('supplier', 'name', fn ($q) => $q->where('is_own', false)),
                TernaryFilter::make('has_mapping')
                    ->label(__('feedmanager::feedmanager.supplier_categories.filter_has_mapping'))
                    ->placeholder(__('feedmanager::feedmanager.supplier_categories.filter_all'))
                    ->trueLabel(__('feedmanager::feedmanager.supplier_categories.filter_mapped'))
                    ->falseLabel(__('feedmanager::feedmanager.supplier_categories.filter_unmapped'))
                    ->queries(
                        true: fn ($q) => $q->whereHas('mapping'),
                        false: fn ($q) => $q->whereDoesntHave('mapping'),
                        blank: fn ($q) => $q,
                    ),
            ])
            ->recordActions([
                Action::make('map')
                    ->label(__('feedmanager::feedmanager.actions.map_category'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->modalHeading(fn (SupplierCategory $record): string => __('feedmanager::feedmanager.actions.map_modal_heading', [
                        'path' => $record->original_path,
                    ]))
                    ->fillForm(fn (SupplierCategory $record): array => [
                        'shoptet_category_id' => $record->mapping?->shoptet_category_id,
                    ])
                    ->schema([
                        Select::make('shoptet_category_id')
                            ->label(__('feedmanager::feedmanager.fields.shoptet_category'))
                            ->options(fn (): array => ShoptetCategory::query()
                                ->orderBy('full_path')
                                ->pluck('full_path', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->placeholder(__('feedmanager::feedmanager.supplier_categories.no_mapping')),
                    ])
                    ->action(function (SupplierCategory $record, array $data, CategoryMappingService $service): void {
                        $shoptetCategoryId = $data['shoptet_category_id'] ?? null;

                        if ($shoptetCategoryId === null || $shoptetCategoryId === '') {
                            CategoryMapping::query()
                                ->where('supplier_category_id', $record->id)
                                ->delete();
                        } else {
                            CategoryMapping::query()->updateOrCreate(
                                ['supplier_category_id' => $record->id],
                                ['shoptet_category_id' => (int) $shoptetCategoryId],
                            );
                        }

                        $service->propagateMappings($record->supplier);

                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.mapping_saved'))
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('auto_map')
                    ->label(__('feedmanager::feedmanager.actions.auto_map'))
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription(__('feedmanager::feedmanager.actions.auto_map_confirm'))
                    ->schema([
                        Select::make('supplier_id')
                            ->label(__('feedmanager::feedmanager.fields.supplier'))
                            ->relationship('supplier', 'name', fn ($q) => $q->where('is_own', false))
                            ->required(),
                    ])
                    ->action(function (array $data, CategoryMappingService $service): void {
                        $supplier = \Adminos\Modules\Feedmanager\Models\Supplier::query()
                            ->find($data['supplier_id']);

                        if ($supplier === null) {
                            return;
                        }

                        $result = $service->autoMap($supplier);

                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.auto_map_done'))
                            ->body(__('feedmanager::feedmanager.notifications.auto_map_body', [
                                'matched' => $result['matched'],
                                'examined' => $result['examined'],
                            ]))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierCategories::route('/'),
        ];
    }
}
