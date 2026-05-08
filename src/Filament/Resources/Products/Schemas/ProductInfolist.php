<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\Schemas;

use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Services\B2bInclusion\B2bInclusionReason;
use Adminos\Modules\Feedmanager\Services\B2bInclusion\B2bInclusionResolver;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Number;

final class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // ── HERO: gallery (left) + identity (right) ───────────────
            Section::make()
                ->components([
                    Grid::make(2)->components([
                        // LEFT — full gallery (primary + alts) with lightbox
                        ViewEntry::make('gallery')
                            ->hiddenLabel()
                            ->view('feedmanager::infolists.product-gallery')
                            ->state(fn (Product $record): array => self::allImageUrls($record))
                            ->columnSpan(1),

                        // RIGHT — identity + status (no name; heading shows it)
                        Grid::make(1)
                            ->columnSpan(1)
                            ->components([
                                Grid::make(3)->components([
                                    TextEntry::make('status')
                                        ->label(__('feedmanager::feedmanager.fields.status'))
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            Product::STATUS_APPROVED => 'success',
                                            Product::STATUS_REJECTED => 'danger',
                                            default => 'warning',
                                        })
                                        ->formatStateUsing(fn (string $state): string => __('feedmanager::feedmanager.products.status.' . $state)),
                                    TextEntry::make('is_b2b_allowed')
                                        ->label(__('feedmanager::feedmanager.fields.is_b2b_allowed'))
                                        ->badge()
                                        ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                                        ->formatStateUsing(fn (bool $state): string => $state
                                            ? __('feedmanager::feedmanager.products.b2b.yes')
                                            : __('feedmanager::feedmanager.products.b2b.no')),
                                    TextEntry::make('is_excluded')
                                        ->label(__('feedmanager::feedmanager.fields.is_excluded'))
                                        ->badge()
                                        ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
                                        ->formatStateUsing(fn (bool $state): string => $state
                                            ? __('feedmanager::feedmanager.products.excluded.yes')
                                            : __('feedmanager::feedmanager.products.excluded.no')),
                                ]),

                                Grid::make(2)->components([
                                    TextEntry::make('code')
                                        ->label(__('feedmanager::feedmanager.fields.code'))
                                        ->fontFamily('mono')
                                        ->copyable(),
                                    TextEntry::make('ean')
                                        ->label(__('feedmanager::feedmanager.fields.ean'))
                                        ->fontFamily('mono')
                                        ->copyable()
                                        ->placeholder('—'),
                                    TextEntry::make('manufacturer')
                                        ->label(__('feedmanager::feedmanager.fields.manufacturer'))
                                        ->placeholder('—'),
                                    TextEntry::make('supplier.name')
                                        ->label(__('feedmanager::feedmanager.fields.supplier'))
                                        ->placeholder('—'),
                                ]),

                                // Pricing condensed into the right column for compact hero.
                                Grid::make(2)->components([
                                    TextEntry::make('effective_price_vat')
                                        ->label(__('feedmanager::feedmanager.fields.price_vat'))
                                        ->state(fn (Product $record): string => self::formatPrice($record->effectivePriceVat()) . ' ' . $record->currency)
                                        ->size(TextSize::Large)
                                        ->weight('bold'),
                                    TextEntry::make('price')
                                        ->label(__('feedmanager::feedmanager.fields.price'))
                                        ->state(fn (Product $record): ?string => $record->price !== null
                                            ? self::formatPrice((string) $record->price) . ' ' . $record->currency
                                            : null)
                                        ->placeholder('—'),
                                    TextEntry::make('old_price_vat')
                                        ->label(__('feedmanager::feedmanager.fields.old_price_vat'))
                                        ->state(fn (Product $record): ?string => $record->old_price_vat !== null
                                            ? self::formatPrice((string) $record->old_price_vat) . ' ' . $record->currency
                                            : null)
                                        ->color('gray')
                                        ->placeholder('—'),
                                    TextEntry::make('discount_percent')
                                        ->label(__('feedmanager::feedmanager.fields.discount'))
                                        ->state(fn (Product $record): ?string => self::discountLabel($record))
                                        ->badge()
                                        ->color('success')
                                        ->placeholder('—'),
                                ]),

                                Grid::make(3)->components([
                                    TextEntry::make('stock_quantity')
                                        ->label(__('feedmanager::feedmanager.fields.stock_quantity'))
                                        ->size(TextSize::Large)
                                        ->weight('bold')
                                        ->color(fn (?int $state): string => match (true) {
                                            $state === null => 'gray',
                                            $state === 0 => 'danger',
                                            $state <= 5 => 'warning',
                                            default => 'success',
                                        })
                                        ->placeholder('—'),
                                    TextEntry::make('availability')
                                        ->label(__('feedmanager::feedmanager.fields.availability'))
                                        ->placeholder('—'),
                                    TextEntry::make('complete_path')
                                        ->label(__('feedmanager::feedmanager.fields.category_text'))
                                        ->placeholder(fn (Product $record): ?string => $record->category_text ?? '—'),
                                ]),
                            ]),
                    ]),
                ]),

            // ── DESCRIPTIONS + PARAMETERS side-by-side ─────────────────
            Grid::make(2)->components([
                Section::make(__('feedmanager::feedmanager.products.sections.descriptions'))
                    ->icon('heroicon-o-document-text')
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('short_description')
                            ->label(__('feedmanager::feedmanager.fields.short_description'))
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->label(__('feedmanager::feedmanager.fields.description'))
                            ->state(fn (Product $record): ?string => $record->effectiveDescription())
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('feedmanager::feedmanager.products.parameters'))
                    ->icon('heroicon-o-list-bullet')
                    ->columnSpan(1)
                    ->components([
                        KeyValueEntry::make('parameter_pairs')
                            ->hiddenLabel()
                            ->state(fn (Product $record): array => $record->parameters()
                                ->orderBy('position')
                                ->pluck('value', 'name')
                                ->toArray())
                            ->keyLabel(__('feedmanager::feedmanager.fields.parameter_name'))
                            ->valueLabel(__('feedmanager::feedmanager.fields.parameter_value')),
                    ]),
            ]),

            // ── B2B STATUS (audit trail s důvodem) ──────────────────────
            Section::make(__('feedmanager::feedmanager.products.sections.b2b_status'))
                ->icon('heroicon-o-shield-check')
                ->components([
                    TextEntry::make('b2b_status')
                        ->label('')
                        ->state(fn (Product $record): string => self::resolveB2bStatusKey($record))
                        ->badge()
                        ->color(fn (string $state): string => str_starts_with($state, 'included_')
                            ? 'success'
                            : 'danger')
                        ->formatStateUsing(fn (string $state): string => __(
                            'feedmanager::feedmanager.products.b2b_status.'.$state,
                        )),
                    TextEntry::make('b2b_status_detail')
                        ->label('')
                        ->state(fn (Product $record): string => self::resolveB2bStatusDetail($record))
                        ->color('gray'),
                ]),

            // ── IMPORT META (collapsed, full width) ─────────────────────
            Section::make(__('feedmanager::feedmanager.products.sections.import_info'))
                ->icon('heroicon-o-clock')
                ->collapsed()
                ->columns(4)
                ->components([
                    TextEntry::make('feedConfig.name')
                        ->label(__('feedmanager::feedmanager.fields.feed_config_name'))
                        ->placeholder('—'),
                    TextEntry::make('imported_at')
                        ->label(__('feedmanager::feedmanager.fields.imported_at'))
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('shoptet_id')
                        ->label(__('feedmanager::feedmanager.fields.shoptet_id'))
                        ->fontFamily('mono')
                        ->placeholder('—'),
                    TextEntry::make('product_number')
                        ->label(__('feedmanager::feedmanager.fields.product_number'))
                        ->fontFamily('mono')
                        ->placeholder('—'),
                ]),
        ]);
    }

    /**
     * Combine the primary image + ProductImage gallery rows into a single
     * ordered list for the lightbox component. Primary first, then gallery.
     *
     * @return array<int, string>
     */
    private static function allImageUrls(Product $record): array
    {
        $urls = [];

        $primary = $record->effectiveImageUrl();
        if ($primary !== null && $primary !== '') {
            $urls[] = $primary;
        }

        foreach ($record->images()->orderBy('position')->pluck('url') as $url) {
            if ($url !== null && $url !== '' && $url !== $primary) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Vyhodnotí B2B inclusion stav pro produkt přes resolver. Vrací string
     * key shodný s `B2bInclusionReason->value` — slouží jako lang klíč
     * (`products.b2b_status.<value>`) i jako podklad pro color closure.
     */
    private static function resolveB2bStatusKey(Product $record): string
    {
        return app(B2bInclusionResolver::class)->resolve($record)->reason->value;
    }

    /**
     * Human-readable detail důvodu — např. „Vyloučeno přes kategorii
     * Náhradní díly". Slouží jako audit trail pro admina.
     */
    private static function resolveB2bStatusDetail(Product $record): string
    {
        $result = app(B2bInclusionResolver::class)->resolve($record);

        if ($result->reason === B2bInclusionReason::EXCLUDED_CATEGORY
            && $result->excludingCategory !== null) {
            return __('feedmanager::feedmanager.products.b2b_status_detail.excluded_category', [
                'path' => $result->excludingCategory->full_path
                    ?? $result->excludingCategory->title,
            ]);
        }

        return __('feedmanager::feedmanager.products.b2b_status_detail.'.$result->reason->value);
    }

    private static function formatPrice(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return Number::format((float) $value, precision: 2, locale: 'cs');
    }

    private static function discountLabel(Product $record): ?string
    {
        $current = $record->effectivePriceVat();
        $old = $record->old_price_vat;

        if ($current === null || $current === '' || $old === null) {
            return null;
        }

        $currentFloat = (float) $current;
        $oldFloat = (float) $old;

        if ($oldFloat <= 0 || $currentFloat >= $oldFloat) {
            return null;
        }

        $percent = (int) round((1 - $currentFloat / $oldFloat) * 100);

        return $percent > 0 ? '−' . $percent . ' %' : null;
    }
}
