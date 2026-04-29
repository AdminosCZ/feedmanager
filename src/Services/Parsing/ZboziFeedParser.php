<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Generator;
use RuntimeException;
use XMLReader;

/**
 * Parses Zboží.cz / Shoptet `seznam/export/products.xml` style feeds.
 *
 * Wrapper is the same `<SHOP><SHOPITEM>` shell as Heuréka, but with two key
 * differences:
 *  - The root `<SHOP>` declares default namespace
 *    `xmlns="http://www.zbozi.cz/ns/offer/1.0"`. We match elements by
 *    `localName` so namespaced and unnamespaced sources both work.
 *  - Identifier lives in `<ITEM_ID>`, not `<CODE>` (which Shoptet's other
 *    feeds use). Stock count is NOT exported by this feed shape — Zboží.cz
 *    `<SHOP_DEPOTS>` is a depot ID, not a quantity. For B2B threshold logic
 *    you need a stock-bearing feed (Shoptet API / internal export).
 *
 * Reference: https://napoveda.zbozi.cz/specifikace-xml-pro-zbozi-cz/
 *
 * @api
 */
final class ZboziFeedParser implements FeedParser
{
    public function parse(string $payload): Generator
    {
        if (trim($payload) === '' || ! str_contains($payload, '<')) {
            throw new RuntimeException('Failed to open Zbozi feed payload as XML.');
        }

        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        if (! $reader->XML($payload)) {
            libxml_use_internal_errors($previous);
            throw new RuntimeException('Failed to open Zbozi feed payload as XML.');
        }

        try {
            $sawElement = false;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    $sawElement = true;
                }

                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'SHOPITEM') {
                    continue;
                }

                $node = $reader->expand();

                if ($node === false) {
                    continue;
                }

                $product = $this->buildProduct($node);

                if ($product !== null) {
                    yield $product;
                }
            }

            if (! $sawElement) {
                throw new RuntimeException('Failed to open Zbozi feed payload as XML.');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function buildProduct(\DOMNode $node): ?ParsedProduct
    {
        if (! $node instanceof \DOMElement) {
            return null;
        }

        $code = $this->trimmed($this->childText($node, 'ITEM_ID'))
            ?? $this->trimmed($this->childText($node, 'PRODUCT_ID'))
            ?? $this->trimmed($this->childText($node, 'CODE'));

        $name = $this->trimmed($this->childText($node, 'PRODUCTNAME'))
            ?? $this->trimmed($this->childText($node, 'PRODUCT'));

        if ($code === null || $name === null) {
            return null;
        }

        return new ParsedProduct(
            code: $code,
            name: $name,
            ean: $this->trimmed($this->childText($node, 'EAN')),
            product_number: $this->trimmed($this->childText($node, 'PRODUCTNO')),
            short_description: $this->trimmed($this->childText($node, 'SHORT_DESCRIPTION')),
            description: $this->trimmed($this->childText($node, 'DESCRIPTION')),
            manufacturer: $this->trimmed($this->childText($node, 'MANUFACTURER')),
            price: $this->floatOrNull($this->childText($node, 'PRICE')),
            price_vat: $this->floatOrNull($this->childText($node, 'PRICE_VAT')),
            old_price_vat: $this->floatOrNull($this->childText($node, 'OLD_PRICE_VAT')),
            currency: $this->trimmed($this->childText($node, 'CURRENCY')) ?? 'CZK',
            // Zboží.cz <SHOP_DEPOTS> is a depot ID, not a stock count, so it
            // is intentionally not used here. Some Shoptet eshops add a
            // <STOCK_AMOUNT> override; honour it when present.
            stock_quantity: $this->intOrNull($this->childText($node, 'STOCK_AMOUNT')),
            availability: $this->trimmed($this->childText($node, 'DELIVERY_DATE'))
                ?? $this->trimmed($this->childText($node, 'AVAILABILITY')),
            image_url: $this->trimmed($this->childText($node, 'IMGURL')),
            category_text: $this->lastCategorySegment($node),
            complete_path: $this->trimmed($this->childText($node, 'CATEGORYTEXT')),
            gallery_urls: $this->collectAlternativeImages($node),
            parameters: $this->collectParameters($node),
        );
    }

    /**
     * Zboží.cz / Heuréka shape:
     *   <PARAM>
     *     <PARAM_NAME>Color</PARAM_NAME>
     *     <VAL>Red</VAL>
     *   </PARAM>
     *
     * @return array<int, array{name: string, value: string}>
     */
    private function collectParameters(\DOMElement $parent): array
    {
        $params = [];

        foreach ($parent->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName !== 'PARAM') {
                continue;
            }

            $name = null;
            $value = null;
            foreach ($child->childNodes as $sub) {
                if (! $sub instanceof \DOMElement) {
                    continue;
                }
                if ($sub->localName === 'PARAM_NAME') {
                    $name = trim($sub->textContent);
                } elseif ($sub->localName === 'VAL') {
                    $value = trim($sub->textContent);
                }
            }

            if ($name !== null && $name !== '' && $value !== null && $value !== '') {
                $params[] = ['name' => $name, 'value' => $value];
            }
        }

        return $params;
    }

    /**
     * Zboží.cz / Shoptet seznam feed has one `<IMGURL>` (primary, already used
     * as image_url) plus zero or more `<IMGURL_ALTERNATIVE>`. Collect the
     * alternatives for the gallery relation.
     *
     * @return array<int, string>
     */
    private function collectAlternativeImages(\DOMElement $parent): array
    {
        $urls = [];

        foreach ($parent->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'IMGURL_ALTERNATIVE') {
                $value = $this->trimmed($child->textContent);
                if ($value !== null) {
                    $urls[] = $value;
                }
            }
        }

        return $urls;
    }

    private function childText(\DOMElement $parent, string $childName): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $childName) {
                return $child->textContent;
            }
        }

        return null;
    }

    private function lastCategorySegment(\DOMElement $node): ?string
    {
        $path = $this->trimmed($this->childText($node, 'CATEGORYTEXT'));

        if ($path === null) {
            return null;
        }

        $segments = preg_split('/\s*[|>\/]\s*/', $path) ?: [];
        $segments = array_values(array_filter($segments, fn (string $s): bool => $s !== ''));

        return $segments === [] ? $path : end($segments);
    }

    private function trimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function floatOrNull(?string $value): ?float
    {
        $trimmed = $this->trimmed($value);

        if ($trimmed === null || ! is_numeric(str_replace(',', '.', $trimmed))) {
            return null;
        }

        return (float) str_replace(',', '.', $trimmed);
    }

    private function intOrNull(?string $value): ?int
    {
        $trimmed = $this->trimmed($value);

        if ($trimmed === null || ! is_numeric($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }
}
