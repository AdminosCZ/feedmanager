<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Suppliers\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\SupplierResource;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\Supplier;
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
 * Create new external supplier in two steps:
 *
 *   1. Identifikace — name, slug, is_active, notes.
 *   2. První feed — feed name, source URL, format, optional Basic Auth.
 *      Submitting with the URL field empty creates only the supplier; admin
 *      can attach feeds later from the supplier detail.
 *
 * One transaction wraps the supplier + feed creation so a half-failed save
 * doesn't leave a phantom supplier behind.
 */
final class CreateSupplier extends CreateRecord
{
    use HasWizard;

    protected static string $resource = SupplierResource::class;

    public function getSteps(): array
    {
        return [
            Step::make(__('feedmanager::feedmanager.suppliers.wizard.step_identity'))
                ->icon('heroicon-o-identification')
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

            Step::make(__('feedmanager::feedmanager.suppliers.wizard.step_first_feed'))
                ->icon('heroicon-o-arrow-down-tray')
                ->description(__('feedmanager::feedmanager.suppliers.wizard.step_first_feed_hint'))
                ->schema([
                    TextInput::make('feed.source_url')
                        ->label(__('feedmanager::feedmanager.fields.source_url'))
                        ->url()
                        ->maxLength(2048)
                        ->columnSpanFull()
                        ->helperText(__('feedmanager::feedmanager.suppliers.wizard.feed_url_hint')),
                    TextInput::make('feed.name')
                        ->label(__('feedmanager::feedmanager.fields.feed_config_name'))
                        ->maxLength(255)
                        ->placeholder(__('feedmanager::feedmanager.suppliers.feed_kind.primary')),
                    \Filament\Forms\Components\Select::make('feed.format')
                        ->label(__('feedmanager::feedmanager.fields.format'))
                        ->options(self::feedFormatOptions())
                        ->default(FeedConfig::FORMAT_HEUREKA)
                        ->native(false),
                    TextInput::make('feed.http_username')
                        ->label(__('feedmanager::feedmanager.fields.http_username'))
                        ->maxLength(255),
                    TextInput::make('feed.http_password')
                        ->label(__('feedmanager::feedmanager.fields.http_password'))
                        ->password()
                        ->revealable()
                        ->maxLength(1024),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Supplier {
            $feedData = $data['feed'] ?? [];
            unset($data['feed']);

            $supplier = Supplier::query()->create($data);

            $sourceUrl = trim((string) ($feedData['source_url'] ?? ''));
            if ($sourceUrl !== '') {
                FeedConfig::query()->create([
                    'supplier_id' => $supplier->id,
                    'name' => trim((string) ($feedData['name'] ?? '')) !== ''
                        ? $feedData['name']
                        : __('feedmanager::feedmanager.suppliers.feed_kind.primary'),
                    'source_url' => $sourceUrl,
                    'format' => $feedData['format'] ?? FeedConfig::FORMAT_HEUREKA,
                    'http_username' => $feedData['http_username'] ?? null,
                    'http_password' => filled($feedData['http_password'] ?? null)
                        ? $feedData['http_password']
                        : null,
                    'is_active' => true,
                ]);
            }

            return $supplier;
        });
    }

    /**
     * @return array<string, string>
     */
    private static function feedFormatOptions(): array
    {
        // Subset of formats relevant for external supplier feeds (drop
        // shoptet_categories — that's an own-eshop concept).
        return [
            FeedConfig::FORMAT_HEUREKA => __('feedmanager::feedmanager.formats.heureka'),
            FeedConfig::FORMAT_GOOGLE => __('feedmanager::feedmanager.formats.google'),
            FeedConfig::FORMAT_SHOPTET => __('feedmanager::feedmanager.formats.shoptet'),
            FeedConfig::FORMAT_ZBOZI => __('feedmanager::feedmanager.formats.zbozi'),
            FeedConfig::FORMAT_SHOPTET_STOCK_CSV => __('feedmanager::feedmanager.formats.shoptet_stock_csv'),
            FeedConfig::FORMAT_CUSTOM => __('feedmanager::feedmanager.formats.custom'),
        ];
    }
}
