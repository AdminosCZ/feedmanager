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
        return $schema->components([
            Section::make(__('feedmanager::feedmanager.feed_configs.sections.identity'))
                ->columns(2)
                ->components([
                    Select::make('supplier_id')
                        ->label(__('feedmanager::feedmanager.fields.source'))
                        ->helperText(__('feedmanager::feedmanager.helpers.feed_config_source'))
                        ->relationship(
                            name: 'supplier',
                            titleAttribute: 'name',
                            // Own eshops first, then external suppliers alphabetically.
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
                ->columns(2)
                ->components([
                    TextInput::make('source_url')
                        ->label(__('feedmanager::feedmanager.fields.source_url'))
                        ->url()
                        ->required()
                        ->maxLength(2048)
                        ->columnSpanFull(),
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
                        // Don't pre-fill the encrypted password into the form on edit.
                        ->formatStateUsing(fn (): ?string => null)
                        // An empty submission must NOT overwrite the stored value.
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->maxLength(1024),
                ]),

            Section::make(__('feedmanager::feedmanager.feed_configs.sections.scheduling'))
                ->columns(3)
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
            FeedConfig::FORMAT_CUSTOM => __('feedmanager::feedmanager.formats.custom'),
        ];
    }
}
