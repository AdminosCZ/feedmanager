<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigs\Schemas;

use Adminos\Modules\Feedmanager\Models\ExportConfig;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class ExportConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('feedmanager::feedmanager.export_configs.sections.identity'))
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label(__('feedmanager::feedmanager.fields.export_config_name'))
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
                        ->helperText(__('feedmanager::feedmanager.helpers.export_slug')),
                    Select::make('format')
                        ->label(__('feedmanager::feedmanager.fields.format'))
                        ->options([
                            ExportConfig::FORMAT_SHOPTET => __('feedmanager::feedmanager.formats.shoptet_out'),
                            ExportConfig::FORMAT_HEUREKA => __('feedmanager::feedmanager.formats.heureka_out'),
                            ExportConfig::FORMAT_GLAMI => __('feedmanager::feedmanager.formats.glami_out'),
                            ExportConfig::FORMAT_ZBOZI => __('feedmanager::feedmanager.formats.zbozi_out'),
                        ])
                        ->required()
                        ->default(ExportConfig::FORMAT_SHOPTET)
                        ->native(false),
                    Toggle::make('is_active')
                        ->label(__('feedmanager::feedmanager.fields.is_active'))
                        ->default(true),
                ]),

            Section::make(__('feedmanager::feedmanager.export_configs.sections.access'))
                ->columns(1)
                ->components([
                    TextInput::make('access_hash')
                        ->label(__('feedmanager::feedmanager.fields.access_hash'))
                        ->helperText(__('feedmanager::feedmanager.helpers.access_hash'))
                        ->disabled()
                        ->dehydrated(false)
                        ->default(fn (): string => ExportConfig::generateHash())
                        ->columnSpanFull(),
                ]),

            Section::make(__('feedmanager::feedmanager.export_configs.sections.policy'))
                ->columns(3)
                ->components([
                    Select::make('price_mode')
                        ->label(__('feedmanager::feedmanager.fields.price_mode'))
                        ->options([
                            ExportConfig::PRICE_WITH_VAT => __('feedmanager::feedmanager.options.price_with_vat'),
                            ExportConfig::PRICE_WITHOUT_VAT => __('feedmanager::feedmanager.options.price_without_vat'),
                        ])
                        ->default(ExportConfig::PRICE_WITH_VAT)
                        ->native(false),
                    Select::make('category_mode')
                        ->label(__('feedmanager::feedmanager.fields.category_mode'))
                        ->options([
                            ExportConfig::CATEGORY_FULL_PATH => __('feedmanager::feedmanager.options.category_full_path'),
                            ExportConfig::CATEGORY_LAST_LEAF => __('feedmanager::feedmanager.options.category_last_leaf'),
                        ])
                        ->default(ExportConfig::CATEGORY_FULL_PATH)
                        ->native(false),
                    Select::make('excluded_mode')
                        ->label(__('feedmanager::feedmanager.fields.excluded_mode'))
                        ->options([
                            ExportConfig::EXCLUDED_SKIP => __('feedmanager::feedmanager.options.excluded_skip'),
                            ExportConfig::EXCLUDED_HIDDEN => __('feedmanager::feedmanager.options.excluded_hidden'),
                        ])
                        ->default(ExportConfig::EXCLUDED_SKIP)
                        ->native(false),
                ]),

            Section::make(__('feedmanager::feedmanager.export_configs.sections.scope'))
                ->columns(1)
                ->components([
                    Select::make('supplier_filter')
                        ->label(__('feedmanager::feedmanager.fields.supplier_filter'))
                        ->helperText(__('feedmanager::feedmanager.helpers.supplier_filter'))
                        ->multiple()
                        ->relationship(name: null, titleAttribute: 'name')
                        ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id'))
                        ->preload()
                        ->searchable(),
                    TagsInput::make('field_whitelist')
                        ->label(__('feedmanager::feedmanager.fields.field_whitelist'))
                        ->helperText(__('feedmanager::feedmanager.helpers.field_whitelist'))
                        ->placeholder('NAME, PRICE_VAT, EAN'),
                    KeyValue::make('extra_flags')
                        ->label(__('feedmanager::feedmanager.fields.extra_flags'))
                        ->helperText(__('feedmanager::feedmanager.helpers.extra_flags'))
                        ->keyLabel(__('feedmanager::feedmanager.fields.flag_name'))
                        ->valueLabel(__('feedmanager::feedmanager.fields.flag_value'))
                        ->reorderable(),
                ]),

            Section::make(__('feedmanager::feedmanager.export_configs.sections.notes'))
                ->collapsed()
                ->components([
                    Textarea::make('notes')
                        ->label(__('feedmanager::feedmanager.fields.notes'))
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
