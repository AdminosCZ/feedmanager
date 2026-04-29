<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources;

use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages\CreateShoptetCategory;
use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages\EditShoptetCategory;
use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages\ListShoptetCategories;
use Adminos\Modules\Feedmanager\Models\ShoptetCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('shoptet_id')
                ->label(__('feedmanager::feedmanager.fields.shoptet_id'))
                ->numeric()
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('parent_shoptet_id')
                ->label(__('feedmanager::feedmanager.fields.parent_shoptet_id'))
                ->numeric(),
            TextInput::make('title')
                ->label(__('feedmanager::feedmanager.fields.shoptet_title'))
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),
            TextInput::make('full_path')
                ->label(__('feedmanager::feedmanager.fields.full_path'))
                ->maxLength(1000)
                ->columnSpanFull(),
            TextInput::make('depth')
                ->label(__('feedmanager::feedmanager.fields.depth'))
                ->numeric()
                ->default(0),
            Toggle::make('visible')
                ->label(__('feedmanager::feedmanager.fields.visible'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('full_path')
            ->columns([
                TextColumn::make('shoptet_id')
                    ->label(__('feedmanager::feedmanager.fields.shoptet_id'))
                    ->fontFamily('mono')
                    ->sortable(),
                TextColumn::make('full_path')
                    ->label(__('feedmanager::feedmanager.fields.full_path'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('depth')
                    ->label(__('feedmanager::feedmanager.fields.depth'))
                    ->alignEnd()
                    ->toggleable(),
                IconColumn::make('visible')
                    ->label(__('feedmanager::feedmanager.fields.visible'))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('is_orphaned')
                    ->label(__('feedmanager::feedmanager.fields.orphaned_short'))
                    ->badge()
                    ->state(fn (ShoptetCategory $r): string => $r->is_orphaned ? 'orphaned' : 'present')
                    ->color(fn (string $state): string => $state === 'orphaned' ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => __(
                        'feedmanager::feedmanager.shoptet_categories.orphan_state.'.$state,
                    ))
                    ->toggleable(),
                TextColumn::make('synced_at')
                    ->label(__('feedmanager::feedmanager.fields.synced_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_orphaned')
                    ->label(__('feedmanager::feedmanager.fields.orphaned_short')),
                TernaryFilter::make('visible')
                    ->label(__('feedmanager::feedmanager.fields.visible')),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShoptetCategories::route('/'),
            'create' => CreateShoptetCategory::route('/create'),
            'edit' => EditShoptetCategory::route('/{record}/edit'),
        ];
    }
}
