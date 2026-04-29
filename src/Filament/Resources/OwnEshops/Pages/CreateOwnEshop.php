<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\OwnEshops\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\OwnEshopResource;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Add new own-eshop in three steps:
 *
 *   1. Identifikace — name, slug, is_active.
 *   2. Primární katalog — feed URL + format. Vlastní eshop typicky má
 *      jeden hlavní produktový feed (custom XML / Heureka / Shoptet).
 *   3. Kategorie e-shopu — URL CSV/XML exportu kategoriíí. Vyplnění
 *      vytvoří FeedConfig se formátem `shoptet_categories`, sync admin
 *      spustí ručně z Kategorie e-shopu sekce.
 *
 * Steps 2 + 3 jsou volitelné — pokud URL pole prázdné, feed se nevytvoří.
 * Vše v jedné transakci.
 */
final class CreateOwnEshop extends CreateRecord
{
    use HasWizard;

    protected static string $resource = OwnEshopResource::class;

    public function getSteps(): array
    {
        return [
            Step::make(__('feedmanager::feedmanager.own_eshops.wizard.step_identity'))
                ->icon('heroicon-o-home')
                ->schema([
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
                        ->unique(Supplier::class, 'slug')
                        ->maxLength(64)
                        ->helperText(__('feedmanager::feedmanager.helpers.slug')),
                    Toggle::make('is_active')
                        ->label(__('feedmanager::feedmanager.fields.is_active'))
                        ->default(true),
                    Textarea::make('notes')
                        ->label(__('feedmanager::feedmanager.fields.notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Step::make(__('feedmanager::feedmanager.own_eshops.wizard.step_primary_feed'))
                ->icon('heroicon-o-cube')
                ->description(__('feedmanager::feedmanager.own_eshops.wizard.step_primary_feed_hint'))
                ->schema([
                    TextInput::make('primary_feed.source_url')
                        ->label(__('feedmanager::feedmanager.fields.source_url'))
                        ->url()
                        ->maxLength(2048)
                        ->columnSpanFull(),
                    Select::make('primary_feed.format')
                        ->label(__('feedmanager::feedmanager.fields.format'))
                        ->options([
                            FeedConfig::FORMAT_HEUREKA => __('feedmanager::feedmanager.formats.heureka'),
                            FeedConfig::FORMAT_SHOPTET => __('feedmanager::feedmanager.formats.shoptet'),
                            FeedConfig::FORMAT_CUSTOM => __('feedmanager::feedmanager.formats.custom'),
                        ])
                        ->default(FeedConfig::FORMAT_HEUREKA)
                        ->native(false),
                ]),

            Step::make(__('feedmanager::feedmanager.own_eshops.wizard.step_categories_feed'))
                ->icon('heroicon-o-folder-open')
                ->description(__('feedmanager::feedmanager.own_eshops.wizard.step_categories_feed_hint'))
                ->schema([
                    TextInput::make('categories_feed.source_url')
                        ->label(__('feedmanager::feedmanager.fields.source_url'))
                        ->url()
                        ->maxLength(2048)
                        ->placeholder('https://eshop.cz/export/categories.csv?...')
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Supplier {
            $primaryFeed = $data['primary_feed'] ?? [];
            $categoriesFeed = $data['categories_feed'] ?? [];
            unset($data['primary_feed'], $data['categories_feed']);

            // is_own forced via Hidden in the form, but defensive belt:
            $data['is_own'] = true;

            $supplier = Supplier::query()->create($data);

            $primaryUrl = trim((string) ($primaryFeed['source_url'] ?? ''));
            if ($primaryUrl !== '') {
                FeedConfig::query()->create([
                    'supplier_id' => $supplier->id,
                    'name' => __('feedmanager::feedmanager.suppliers.feed_kind.primary'),
                    'source_url' => $primaryUrl,
                    'format' => $primaryFeed['format'] ?? FeedConfig::FORMAT_HEUREKA,
                    'is_active' => true,
                ]);
            }

            $categoriesUrl = trim((string) ($categoriesFeed['source_url'] ?? ''));
            if ($categoriesUrl !== '') {
                FeedConfig::query()->create([
                    'supplier_id' => $supplier->id,
                    'name' => __('feedmanager::feedmanager.suppliers.feed_kind.categories'),
                    'source_url' => $categoriesUrl,
                    'format' => FeedConfig::FORMAT_SHOPTET_CATEGORIES,
                    'is_active' => true,
                ]);
            }

            return $supplier;
        });
    }
}
