<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\Schemas;

use Adminos\Modules\Feedmanager\Models\Product;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(4)->components([
                TextEntry::make('effective_price_vat')
                    ->label(__('feedmanager::feedmanager.fields.price_vat'))
                    ->state(fn (Product $record): string => $record->effectivePriceVat() . ' ' . $record->currency)
                    ->weight('bold'),
                TextEntry::make('price')
                    ->label(__('feedmanager::feedmanager.fields.price'))
                    ->placeholder('—')
                    ->suffix(fn (Product $record): string => ' ' . $record->currency),
                TextEntry::make('stock_quantity')
                    ->label(__('feedmanager::feedmanager.fields.stock_quantity'))
                    ->placeholder('—'),
                TextEntry::make('is_b2b_allowed')
                    ->label(__('feedmanager::feedmanager.fields.is_b2b_allowed'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('feedmanager::feedmanager.products.b2b.yes')
                        : __('feedmanager::feedmanager.products.b2b.no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ]),

            Section::make(__('feedmanager::feedmanager.products.sections.preview'))
                ->components([
                    ImageEntry::make('image_url')
                        ->label(__('feedmanager::feedmanager.fields.image_url'))
                        ->state(fn (Product $record): ?string => $record->effectiveImageUrl())
                        ->square()
                        ->size(200),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.identity'))
                ->columns(5)
                ->components([
                    TextEntry::make('code')
                        ->label(__('feedmanager::feedmanager.fields.code'))
                        ->fontFamily('mono')
                        ->copyable(),
                    TextEntry::make('ean')
                        ->label(__('feedmanager::feedmanager.fields.ean'))
                        ->fontFamily('mono')
                        ->placeholder('—'),
                    TextEntry::make('product_number')
                        ->label(__('feedmanager::feedmanager.fields.product_number'))
                        ->fontFamily('mono')
                        ->placeholder('—'),
                    TextEntry::make('shoptet_id')
                        ->label(__('feedmanager::feedmanager.fields.shoptet_id'))
                        ->fontFamily('mono')
                        ->placeholder('—'),
                    TextEntry::make('availability')
                        ->label(__('feedmanager::feedmanager.fields.availability'))
                        ->placeholder('—'),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.classification'))
                ->columns(2)
                ->components([
                    TextEntry::make('manufacturer')
                        ->label(__('feedmanager::feedmanager.fields.manufacturer'))
                        ->placeholder('—'),
                    TextEntry::make('complete_path')
                        ->label(__('feedmanager::feedmanager.fields.category_text'))
                        ->placeholder(fn (Product $record): ?string => $record->category_text ?? '—'),
                    TextEntry::make('status')
                        ->label(__('feedmanager::feedmanager.fields.status'))
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            Product::STATUS_APPROVED => 'success',
                            Product::STATUS_REJECTED => 'danger',
                            default => 'warning',
                        })
                        ->formatStateUsing(fn (string $state): string => __('feedmanager::feedmanager.products.status.' . $state)),
                    TextEntry::make('is_excluded')
                        ->label(__('feedmanager::feedmanager.fields.is_excluded'))
                        ->badge()
                        ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
                        ->formatStateUsing(fn (bool $state): string => $state ? __('feedmanager::feedmanager.products.excluded.yes') : __('feedmanager::feedmanager.products.excluded.no')),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.descriptions'))
                ->collapsed(fn (Product $record): bool => empty($record->effectiveDescription()) && empty($record->short_description))
                ->components([
                    TextEntry::make('short_description')
                        ->label(__('feedmanager::feedmanager.fields.short_description'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('description')
                        ->label(__('feedmanager::feedmanager.fields.description'))
                        ->state(fn (Product $record): ?string => $record->effectiveDescription())
                        ->html()
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.import_info'))
                ->columns(3)
                ->collapsed()
                ->components([
                    TextEntry::make('supplier.name')
                        ->label(__('feedmanager::feedmanager.fields.supplier'))
                        ->placeholder('—'),
                    TextEntry::make('imported_at')
                        ->label(__('feedmanager::feedmanager.fields.imported_at'))
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('updated_at')
                        ->label(__('feedmanager::feedmanager.fields.updated_at'))
                        ->dateTime(),
                ]),
        ]);
    }
}
