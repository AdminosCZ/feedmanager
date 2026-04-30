<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Schemas;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class FeedConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        // Napojse-style aside layout: každá sekce má heading + popis vlevo,
        // pole vpravo. Sekce stackují vertikálně, žádné 2x2 packování.
        return $schema->components([
            Section::make(__('feedmanager::feedmanager.feed_configs.sections.identity'))
                ->description(__('feedmanager::feedmanager.feed_configs.section_descriptions.identity'))
                ->aside()
                ->components([
                    Select::make('supplier_id')
                        ->label(__('feedmanager::feedmanager.fields.source'))
                        ->relationship(
                            name: 'supplier',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query
                                ->orderBy('is_own', 'desc')
                                ->orderBy('name'),
                        )
                        ->required()
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')->required(),
                            TextInput::make('slug')->required()->unique(Supplier::class, 'slug'),
                        ]),
                    TextInput::make('name')
                        ->label(__('feedmanager::feedmanager.fields.feed_config_name'))
                        ->required()
                        ->maxLength(255),
                ]),

            Section::make(__('feedmanager::feedmanager.feed_configs.sections.source'))
                ->description(__('feedmanager::feedmanager.feed_configs.section_descriptions.source'))
                ->aside()
                ->components([
                    TextInput::make('source_url')
                        ->label(__('feedmanager::feedmanager.fields.source_url'))
                        ->url()
                        ->required()
                        ->maxLength(2048),
                    Select::make('format')
                        ->label(__('feedmanager::feedmanager.fields.format'))
                        ->options(self::formatOptions())
                        ->required()
                        ->default(FeedConfig::FORMAT_HEUREKA)
                        ->native(false),
                    TextInput::make('http_username')
                        ->label(__('feedmanager::feedmanager.fields.http_username'))
                        ->maxLength(255),
                    TextInput::make('http_password')
                        ->label(__('feedmanager::feedmanager.fields.http_password'))
                        ->password()
                        ->revealable()
                        ->helperText(__('feedmanager::feedmanager.helpers.http_password'))
                        ->formatStateUsing(fn (): ?string => null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->maxLength(1024),
                ]),

            Section::make(__('feedmanager::feedmanager.feed_configs.sections.scheduling'))
                ->description(__('feedmanager::feedmanager.feed_configs.section_descriptions.scheduling'))
                ->aside()
                ->components([
                    Toggle::make('is_active')
                        ->label(__('feedmanager::feedmanager.fields.is_active'))
                        ->default(true)
                        ->helperText(__('feedmanager::feedmanager.helpers.feed_config_is_active')),
                    Toggle::make('auto_update')
                        ->label(__('feedmanager::feedmanager.fields.auto_update'))
                        ->default(false)
                        ->helperText(__('feedmanager::feedmanager.helpers.auto_update')),
                    Toggle::make('default_b2b_allowed')
                        ->label(__('feedmanager::feedmanager.fields.default_b2b_allowed'))
                        ->default(true)
                        ->helperText(__('feedmanager::feedmanager.helpers.default_b2b_allowed')),
                    Toggle::make('update_only_mode')
                        ->label(__('feedmanager::feedmanager.fields.update_only_mode'))
                        ->default(false)
                        ->helperText(__('feedmanager::feedmanager.helpers.update_only_mode')),
                ]),

            Section::make(__('feedmanager::feedmanager.feed_configs.sections.import_scope'))
                ->description(__('feedmanager::feedmanager.feed_configs.section_descriptions.import_scope'))
                ->aside()
                ->components([
                    Toggle::make('import_all_images')
                        ->label(__('feedmanager::feedmanager.fields.import_all_images'))
                        ->default(true)
                        ->helperText(__('feedmanager::feedmanager.helpers.import_all_images')),
                    Toggle::make('import_short_description')
                        ->label(__('feedmanager::feedmanager.fields.import_short_description'))
                        ->default(true)
                        ->helperText(__('feedmanager::feedmanager.helpers.import_short_description')),
                    Toggle::make('import_long_description')
                        ->label(__('feedmanager::feedmanager.fields.import_long_description'))
                        ->default(true)
                        ->helperText(__('feedmanager::feedmanager.helpers.import_long_description')),
                    Toggle::make('import_parameters_only')
                        ->label(__('feedmanager::feedmanager.fields.import_parameters_only'))
                        ->default(false)
                        ->helperText(__('feedmanager::feedmanager.helpers.import_parameters_only')),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function formatOptions(): array
    {
        return [
            FeedConfig::FORMAT_HEUREKA => __('feedmanager::feedmanager.formats.heureka'),
            FeedConfig::FORMAT_GOOGLE => __('feedmanager::feedmanager.formats.google'),
            FeedConfig::FORMAT_SHOPTET => __('feedmanager::feedmanager.formats.shoptet'),
            FeedConfig::FORMAT_ZBOZI => __('feedmanager::feedmanager.formats.zbozi'),
            FeedConfig::FORMAT_SHOPTET_STOCK_CSV => __('feedmanager::feedmanager.formats.shoptet_stock_csv'),
            FeedConfig::FORMAT_SHOPTET_CATEGORIES => __('feedmanager::feedmanager.formats.shoptet_categories'),
            FeedConfig::FORMAT_CUSTOM => __('feedmanager::feedmanager.formats.custom'),
        ];
    }
}
