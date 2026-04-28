<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ParametersRelationManager extends RelationManager
{
    protected static string $relationship = 'parameters';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('feedmanager::feedmanager.fields.parameter_name'))
                ->required()
                ->maxLength(128),
            TextInput::make('value')
                ->label(__('feedmanager::feedmanager.fields.parameter_value'))
                ->required()
                ->maxLength(1024),
            TextInput::make('position')
                ->label(__('feedmanager::feedmanager.fields.position'))
                ->numeric()
                ->minValue(0)
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('feedmanager::feedmanager.products.parameters'))
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')
                    ->label(__('feedmanager::feedmanager.fields.parameter_name'))
                    ->searchable(),
                TextColumn::make('value')
                    ->label(__('feedmanager::feedmanager.fields.parameter_value'))
                    ->searchable(),
                TextColumn::make('position')
                    ->label(__('feedmanager::feedmanager.fields.position'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
