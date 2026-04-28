<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\Schemas;

use Adminos\Modules\Feedmanager\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('feedmanager::feedmanager.products.sections.identity'))
                ->columns(4)
                ->components([
                    TextInput::make('code')
                        ->label(__('feedmanager::feedmanager.fields.code'))
                        ->required()
                        ->maxLength(64),
                    TextInput::make('ean')
                        ->label(__('feedmanager::feedmanager.fields.ean'))
                        ->maxLength(32),
                    TextInput::make('product_number')
                        ->label(__('feedmanager::feedmanager.fields.product_number'))
                        ->maxLength(64),
                    TextInput::make('shoptet_id')
                        ->label(__('feedmanager::feedmanager.fields.shoptet_id'))
                        ->maxLength(64),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.content'))
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label(__('feedmanager::feedmanager.fields.name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('manufacturer')
                        ->label(__('feedmanager::feedmanager.fields.manufacturer'))
                        ->maxLength(128),
                    TextInput::make('image_url')
                        ->label(__('feedmanager::feedmanager.fields.image_url'))
                        ->url()
                        ->maxLength(2048),
                    Textarea::make('short_description')
                        ->label(__('feedmanager::feedmanager.fields.short_description'))
                        ->rows(2)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label(__('feedmanager::feedmanager.fields.description'))
                        ->rows(5)
                        ->columnSpanFull(),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.pricing'))
                ->columns(3)
                ->components([
                    TextInput::make('price')
                        ->label(__('feedmanager::feedmanager.fields.price'))
                        ->numeric()
                        ->step('0.0001')
                        ->default(0)
                        ->required(),
                    TextInput::make('price_vat')
                        ->label(__('feedmanager::feedmanager.fields.price_vat'))
                        ->numeric()
                        ->step('0.0001')
                        ->default(0)
                        ->required(),
                    Select::make('currency')
                        ->label(__('feedmanager::feedmanager.fields.currency'))
                        ->options([
                            'CZK' => 'CZK',
                            'EUR' => 'EUR',
                            'USD' => 'USD',
                        ])
                        ->default('CZK')
                        ->native(false)
                        ->required(),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.stock'))
                ->columns(3)
                ->components([
                    TextInput::make('stock_quantity')
                        ->label(__('feedmanager::feedmanager.fields.stock_quantity'))
                        ->numeric()
                        ->default(0),
                    TextInput::make('availability')
                        ->label(__('feedmanager::feedmanager.fields.availability'))
                        ->placeholder(__('feedmanager::feedmanager.placeholders.availability'))
                        ->maxLength(32),
                    TextInput::make('category_text')
                        ->label(__('feedmanager::feedmanager.fields.category_text'))
                        ->maxLength(255),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.flags'))
                ->columns(3)
                ->components([
                    Select::make('status')
                        ->label(__('feedmanager::feedmanager.fields.status'))
                        ->options([
                            Product::STATUS_PENDING => __('feedmanager::feedmanager.products.status.pending'),
                            Product::STATUS_APPROVED => __('feedmanager::feedmanager.products.status.approved'),
                            Product::STATUS_REJECTED => __('feedmanager::feedmanager.products.status.rejected'),
                        ])
                        ->default(Product::STATUS_PENDING)
                        ->native(false)
                        ->required(),
                    Toggle::make('is_b2b_allowed')
                        ->label(__('feedmanager::feedmanager.fields.is_b2b_allowed'))
                        ->helperText(__('feedmanager::feedmanager.helpers.is_b2b_allowed'))
                        ->default(true),
                    Toggle::make('is_excluded')
                        ->label(__('feedmanager::feedmanager.fields.is_excluded'))
                        ->helperText(__('feedmanager::feedmanager.helpers.is_excluded'))
                        ->default(false),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.b2b_thresholds'))
                ->description(__('feedmanager::feedmanager.products.b2b_thresholds_help'))
                ->columns(2)
                ->collapsed()
                ->components([
                    TextInput::make('b2b_low_stock_threshold')
                        ->label(__('feedmanager::feedmanager.fields.b2b_low_stock_threshold'))
                        ->helperText(__('feedmanager::feedmanager.helpers.b2b_low_stock_threshold'))
                        ->numeric()
                        ->minValue(0)
                        ->placeholder(__('feedmanager::feedmanager.placeholders.b2b_low_stock_threshold')),
                    TextInput::make('b2b_low_stock_availability')
                        ->label(__('feedmanager::feedmanager.fields.b2b_low_stock_availability'))
                        ->helperText(__('feedmanager::feedmanager.helpers.b2b_low_stock_availability'))
                        ->maxLength(64)
                        ->placeholder(__('feedmanager::feedmanager.placeholders.b2b_low_stock_availability')),
                ]),

            Section::make(__('feedmanager::feedmanager.products.sections.overrides'))
                ->description(__('feedmanager::feedmanager.products.sections.overrides_help'))
                ->columns(2)
                ->collapsed()
                ->components([
                    TextInput::make('override_name')
                        ->label(__('feedmanager::feedmanager.fields.override_name'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('override_description')
                        ->label(__('feedmanager::feedmanager.fields.override_description'))
                        ->rows(4)
                        ->columnSpanFull(),
                    TextInput::make('override_price_vat')
                        ->label(__('feedmanager::feedmanager.fields.override_price_vat'))
                        ->numeric()
                        ->step('0.0001'),
                    TagsInput::make('locked_fields')
                        ->label(__('feedmanager::feedmanager.fields.locked_fields'))
                        ->helperText(__('feedmanager::feedmanager.helpers.locked_fields'))
                        ->placeholder(__('feedmanager::feedmanager.placeholders.locked_fields')),
                ]),
        ]);
    }
}
