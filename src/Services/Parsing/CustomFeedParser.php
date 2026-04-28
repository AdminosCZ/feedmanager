<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Generator;
use RuntimeException;
use XMLReader;

/**
 * Heuristic parser for unknown XML feed shapes.
 *
 * Tries the most common product wrapper element names in order until it finds
 * one that yields data: SHOPITEM, item, product, PRODUCT, entry, row, ZBOZI.
 * Then maps the most common field names heuristically — short list of
 * synonyms for each catalog field.
 *
 * Use as a last resort when the source feed doesn't fit any of the named
 * formats. Less reliable than format-specific parsers, but lets us pull
 * something rather than nothing.
 *
 * @api
 */
final class CustomFeedParser implements FeedParser
{
    /** @var array<int, string> */
    private const ITEM_TAGS = ['SHOPITEM', 'item', 'product', 'PRODUCT', 'entry', 'row', 'ZBOZI'];

    /** @var array<string, array<int, string>> */
    private const FIELD_ALIASES = [
        'code' => ['CODE', 'ITEM_ID', 'PRODUCT_ID', 'id', 'ID', 'sku', 'SKU'],
        'name' => ['PRODUCT', 'PRODUCTNAME', 'NAME', 'name', 'title', 'TITLE'],
        'ean' => ['EAN', 'gtin', 'GTIN', 'barcode', 'BARCODE'],
        'product_number' => ['PRODUCTNO', 'mpn', 'MPN'],
        'description' => ['DESCRIPTION', 'description', 'SHORT_DESCRIPTION', 'desc'],
        'manufacturer' => ['MANUFACTURER', 'BRAND', 'brand'],
        'price' => ['PRICE', 'price'],
        'price_vat' => ['PRICE_VAT', 'price_vat'],
        'old_price_vat' => ['OLD_PRICE_VAT', 'old_price'],
        'currency' => ['CURRENCY', 'currency'],
        'stock_quantity' => ['STOCK_AMOUNT', 'STOCK', 'stock', 'quantity', 'QUANTITY'],
        'availability' => ['AVAILABILITY', 'availability', 'DELIVERY_DATE'],
        'image_url' => ['IMGURL', 'image_link', 'IMAGE', 'image'],
        'category' => ['CATEGORYTEXT', 'product_type', 'category', 'CATEGORY'],
    ];

    public function parse(string $payload): Generator
    {
        if (trim($payload) === '' || ! str_contains($payload, '<')) {
            throw new RuntimeException('Failed to open custom feed payload as XML.');
        }

        $itemTag = $this->detectItemTag($payload);

        if ($itemTag === null) {
            throw new RuntimeException(
                'Custom feed parser could not find any of the recognised wrapper elements (' .
                implode(', ', self::ITEM_TAGS) . ').',
            );
        }

        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        if (! $reader->XML($payload)) {
            libxml_use_internal_errors($previous);
            throw new RuntimeException('Failed to open custom feed payload as XML.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== $itemTag) {
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
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function detectItemTag(string $payload): ?string
    {
        foreach (self::ITEM_TAGS as $tag) {
            // Word-boundary match against an opening tag like `<SHOPITEM>` or `<SHOPITEM `.
            if (preg_match('/<' . preg_quote($tag, '/') . '[\s>]/', $payload) === 1) {
                return $tag;
            }
        }

        return null;
    }

    private function buildProduct(\DOMNode $node): ?ParsedProduct
    {
        if (! $node instanceof \DOMElement) {
            return null;
        }

        $code = $this->resolveField($node, self::FIELD_ALIASES['code']);
        $name = $this->resolveField($node, self::FIELD_ALIASES['name']);

        if ($code === null || $name === null) {
            return null;
        }

        $categoryText = $this->resolveField($node, self::FIELD_ALIASES['category']);

        return new ParsedProduct(
            code: $code,
            name: $name,
            ean: $this->resolveField($node, self::FIELD_ALIASES['ean']),
            product_number: $this->resolveField($node, self::FIELD_ALIASES['product_number']),
            description: $this->resolveField($node, self::FIELD_ALIASES['description']),
            manufacturer: $this->resolveField($node, self::FIELD_ALIASES['manufacturer']),
            price: $this->floatOrNull($this->resolveField($node, self::FIELD_ALIASES['price'])),
            price_vat: $this->floatOrNull($this->resolveField($node, self::FIELD_ALIASES['price_vat'])),
            old_price_vat: $this->floatOrNull($this->resolveField($node, self::FIELD_ALIASES['old_price_vat'])),
            currency: $this->resolveField($node, self::FIELD_ALIASES['currency']) ?? 'CZK',
            stock_quantity: $this->intOrNull($this->resolveField($node, self::FIELD_ALIASES['stock_quantity'])),
            availability: $this->resolveField($node, self::FIELD_ALIASES['availability']),
            image_url: $this->resolveField($node, self::FIELD_ALIASES['image_url']),
            category_text: $this->lastSegment($categoryText),
            complete_path: $categoryText,
        );
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function resolveField(\DOMElement $parent, array $aliases): ?string
    {
        foreach ($aliases as $name) {
            foreach ($parent->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->nodeName === $name) {
                    $value = trim($child->textContent);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function lastSegment(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $segments = preg_split('/\s*[|>\/]\s*/', $path) ?: [];
        $segments = array_values(array_filter($segments, fn (string $s): bool => $s !== ''));

        return $segments === [] ? $path : end($segments);
    }

    private function floatOrNull(?string $value): ?float
    {
        if ($value === null || ! is_numeric(str_replace(',', '.', $value))) {
            return null;
        }
        return (float) str_replace(',', '.', $value);
    }

    private function intOrNull(?string $value): ?int
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }
}
