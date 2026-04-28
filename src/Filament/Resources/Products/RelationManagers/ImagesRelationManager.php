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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('url')
                ->label(__('feedmanager::feedmanager.fields.image_url'))
                ->url()
                ->required()
                ->maxLength(2048)
                ->columnSpanFull(),
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
            ->heading(__('feedmanager::feedmanager.products.gallery'))
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('url')
                    ->label(__('feedmanager::feedmanager.fields.image_url'))
                    ->square()
                    ->size(64),
                TextColumn::make('url')
                    ->label('URL')
                    ->limit(60)
                    ->fontFamily('mono'),
                TextColumn::make('position')
                    ->label(__('feedmanager::feedmanager.fields.position'))
                    ->sortable()
                    ->alignEnd(),
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
