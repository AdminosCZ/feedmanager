<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services;

use Adminos\Modules\Feedmanager\Models\ExportConfig;
use Adminos\Modules\Feedmanager\Models\Product;
use Generator;
use InvalidArgumentException;
use XMLWriter;

/**
 * Renders the B2C marketplace feed.
 *
 * Output schema follows the Shoptet/Heuréka XML shape (both share the same
 * `<SHOP><SHOPITEM>...</SHOPITEM></SHOP>` structure with NAME, DESCRIPTION,
 * PRICE_VAT, EAN, IMGURL, CATEGORYTEXT, AVAILABILITY, etc.). Glami and
 * Zboží.cz follow next in PR 5.
 *
 * Honours per-config policy:
 *  - price_mode: with_vat or without_vat (controls PRICE/PRICE_VAT mix)
 *  - category_mode: full_path or last_leaf (controls CATEGORYTEXT shape)
 *  - excluded_mode: skip or hidden (with VISIBILITY=visibility_hidden flag)
 *  - supplier_filter: optional array of supplier ids to include
 *  - field_whitelist: optional array of field names to include
 *  - extra_flags: array of static FLAG_1/2/3 values appended verbatim
 *
 * Reads through Eloquent's `lazy()` cursor so memory stays flat for catalogs
 * of arbitrary size.
 *
 * @api
 */
final class B2cFeedExporter
{
    public function __construct(
        private readonly int $chunkSize = 500,
    ) {
    }

    /**
     * @return array{xml: string, count: int}
     */
    public function export(ExportConfig $config): array
    {
        if (! in_array($config->format, ExportConfig::FORMATS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown export format "%s". Must be one of: %s.',
                $config->format,
                implode(', ', ExportConfig::FORMATS),
            ));
        }

        // Shoptet, Heuréka, Glami and Zboží.cz all share the <SHOP><SHOPITEM>
        // schema. Per-format extras (e.g. Glami gender/size, Zboží.cz URL)
        // are emitted via the per-config `extra_flags` map until they earn
        // their own dedicated exporter strategy.

        $count = $this->countQuery($config)->count();

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('SHOP');
        $writer->writeAttribute('generated', now()->toIso8601String());
        $writer->writeAttribute('format', $config->format);
        $writer->writeAttribute('count', (string) $count);

        $emitted = 0;
        foreach ($this->productStream($config) as $product) {
            $this->writeShopItem($writer, $product, $config);
            ++$emitted;
        }

        $writer->endElement();
        $writer->endDocument();

        return [
            'xml' => $writer->outputMemory(true),
            'count' => $emitted,
        ];
    }

    /**
     * @return Generator<int, Product>
     */
    private function productStream(ExportConfig $config): Generator
    {
        yield from $this->baseQuery($config)
            ->orderBy('id')
            ->lazy($this->chunkSize);
    }

    private function countQuery(ExportConfig $config): \Illuminate\Database\Eloquent\Builder
    {
        return $this->baseQuery($config);
    }

    private function baseQuery(ExportConfig $config): \Illuminate\Database\Eloquent\Builder
    {
        $query = Product::query();

        if ($config->excluded_mode === ExportConfig::EXCLUDED_SKIP) {
            $query->where('is_excluded', false);
        }

        $supplierFilter = $config->supplier_filter;
        if (is_array($supplierFilter) && $supplierFilter !== []) {
            $query->whereIn('supplier_id', $supplierFilter);
        }

        return $query;
    }

    private function writeShopItem(XMLWriter $writer, Product $product, ExportConfig $config): void
    {
        $writer->startElement('SHOPITEM');

        $this->writeIfWhitelisted($writer, $config, 'CODE', $product->code);
        $this->writeIfWhitelisted($writer, $config, 'NAME', $product->effectiveName());

        $description = $product->effectiveDescription();
        if ($description !== null && $description !== '' && $this->isFieldAllowed($config, 'DESCRIPTION')) {
            $writer->startElement('DESCRIPTION');
            $writer->writeCData($description);
            $writer->endElement();
        }

        if ($product->manufacturer !== null && $product->manufacturer !== '') {
            $this->writeIfWhitelisted($writer, $config, 'MANUFACTURER', $product->manufacturer);
        }

        $effectivePriceVat = $product->effectivePriceVat();

        if ($config->price_mode === ExportConfig::PRICE_WITHOUT_VAT) {
            $this->writeIfWhitelisted($writer, $config, 'PRICE', (string) $product->price);
        } else {
            $this->writeIfWhitelisted($writer, $config, 'PRICE_VAT', (string) $effectivePriceVat);
        }

        if ($product->old_price_vat !== null) {
            $this->writeIfWhitelisted($writer, $config, 'OLD_PRICE_VAT', (string) $product->old_price_vat);
        }

        $this->writeIfWhitelisted($writer, $config, 'CURRENCY', $product->currency);

        if ($product->ean !== null && $product->ean !== '') {
            $this->writeIfWhitelisted($writer, $config, 'EAN', $product->ean);
        }

        if ($product->product_number !== null && $product->product_number !== '') {
            $this->writeIfWhitelisted($writer, $config, 'PRODUCTNO', $product->product_number);
        }

        if ($product->image_url !== null && $product->image_url !== '') {
            $this->writeIfWhitelisted($writer, $config, 'IMGURL', $product->image_url);
        }

        $categoryText = $this->resolveCategoryText($product, $config);

        if ($categoryText !== null && $categoryText !== '') {
            $this->writeIfWhitelisted($writer, $config, 'CATEGORYTEXT', $categoryText);
        }

        if ($product->availability !== null && $product->availability !== '') {
            $this->writeIfWhitelisted($writer, $config, 'AVAILABILITY', $product->availability);
        }

        $this->writeIfWhitelisted($writer, $config, 'STOCK', (string) $product->stock_quantity);

        if ($product->is_excluded && $config->excluded_mode === ExportConfig::EXCLUDED_HIDDEN) {
            $writer->writeElement('VISIBILITY', 'visibility_hidden');
        }

        if (is_array($config->extra_flags)) {
            foreach ($config->extra_flags as $flagName => $flagValue) {
                $element = strtoupper((string) $flagName);
                if ($flagValue !== null && $flagValue !== '') {
                    $writer->writeElement($element, (string) $flagValue);
                }
            }
        }

        $writer->endElement();
    }

    /**
     * Category text resolution priority:
     *   1. Mapped shoptet category (full_path) when category_mode = full_path,
     *      or its title when last_leaf — wins because admin actively chose
     *      where to land this product.
     *   2. Fallback to product.complete_path / category_text from the
     *      original feed.
     */
    private function resolveCategoryText(Product $product, ExportConfig $config): ?string
    {
        $shoptetCategory = $product->shoptetCategory;

        if ($shoptetCategory !== null) {
            if ($config->category_mode === ExportConfig::CATEGORY_FULL_PATH) {
                return $shoptetCategory->full_path ?? $shoptetCategory->title;
            }
            return $shoptetCategory->title;
        }

        return $config->category_mode === ExportConfig::CATEGORY_FULL_PATH
            ? ($product->complete_path ?? $product->category_text)
            : ($product->category_text ?? $product->complete_path);
    }

    private function writeIfWhitelisted(XMLWriter $writer, ExportConfig $config, string $element, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! $this->isFieldAllowed($config, $element)) {
            return;
        }

        $writer->writeElement($element, $value);
    }

    private function isFieldAllowed(ExportConfig $config, string $element): bool
    {
        $whitelist = $config->field_whitelist;

        if (! is_array($whitelist) || $whitelist === []) {
            return true;
        }

        return in_array($element, $whitelist, true);
    }
}
