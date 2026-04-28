<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
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
}
