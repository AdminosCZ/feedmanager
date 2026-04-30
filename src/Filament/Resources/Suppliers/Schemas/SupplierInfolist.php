<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Schemas;

use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(4)->components([
                TextEntry::make('is_own')
                    ->label(__('feedmanager::feedmanager.fields.supplier_kind'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('feedmanager::feedmanager.suppliers.kind.own')
                        : __('feedmanager::feedmanager.suppliers.kind.external'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextEntry::make('approved_products_count')
                    ->label(__('feedmanager::feedmanager.fields.approved_products_count'))
                    ->state(fn (Supplier $record): int => $record->products()
                        ->where('status', Product::STATUS_APPROVED)->count()),
                TextEntry::make('pending_products_count')
                    ->label(__('feedmanager::feedmanager.fields.pending_products_count'))
                    ->state(fn (Supplier $record): int => $record->products()
                        ->where('status', Product::STATUS_PENDING)->count()),
                TextEntry::make('last_sync')
                    ->label(__('feedmanager::feedmanager.suppliers_overview.last_sync'))
                    ->state(function (Supplier $record): string {
                        $log = ImportLog::query()
                            ->whereIn('feed_config_id', $record->feedConfigs()->pluck('id'))
                            ->where('status', ImportLog::STATUS_SUCCESS)
                            ->latest('finished_at')
                            ->first();

                        return $log?->finished_at?->translatedFormat('d.m.y H:i')
                            ?? __('feedmanager::feedmanager.suppliers_overview.last_sync_never');
                    }),
            ]),

            Section::make(__('feedmanager::feedmanager.suppliers.sections.identity'))
                ->columns(2)
                ->components([
                    TextEntry::make('name')
                        ->label(__('feedmanager::feedmanager.fields.supplier_name')),
                    TextEntry::make('slug')
                        ->label(__('feedmanager::feedmanager.fields.slug'))
                        ->fontFamily('mono')
                        ->copyable(),
                    IconEntry::make('is_active')
                        ->label(__('feedmanager::feedmanager.fields.is_active'))
                        ->boolean(),
                    IconEntry::make('publish_to_shoptet')
                        ->label(__('feedmanager::feedmanager.fields.publish_to_shoptet'))
                        ->boolean(),
                ]),

            Section::make(__('feedmanager::feedmanager.suppliers.sections.notes'))
                ->collapsed()
                ->components([
                    TextEntry::make('notes')
                        ->label(__('feedmanager::feedmanager.fields.notes'))
                        ->columnSpanFull()
                        ->placeholder(__('feedmanager::feedmanager.suppliers.no_notes')),
                ])
                ->visible(fn (Supplier $record): bool => filled($record->notes)),
        ]);
    }
}
