<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Generator;
use RuntimeException;
use XMLReader;

/**
 * Parses Shoptet supplier-export XML feeds.
 *
 * Schema is the same `<SHOP><SHOPITEM>` shell as Heuréka, but Shoptet uses
 * different field names: `<CODE>` instead of `<ITEM_ID>`, `<PRODUCT>` instead
 * of `<PRODUCTNAME>`, etc. Plus extras like `<SHORT_DESCRIPTION>`, multiple
 * `<IMGURL>` elements, and a `<PARAM>` block for custom parameters.
 *
 * @api
 */
final class ShoptetFeedParser implements FeedParser
{
    public function parse(string $payload): Generator
    {
        if (trim($payload) === '' || ! str_contains($payload, '<')) {
            throw new RuntimeException('Failed to open Shoptet feed payload as XML.');
        }

        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        if (! $reader->XML($payload)) {
            libxml_use_internal_errors($previous);
            throw new RuntimeException('Failed to open Shoptet feed payload as XML.');
        }

        try {
            $sawElement = false;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    $sawElement = true;
                }

                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'SHOPITEM') {
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
                throw new RuntimeException('Failed to open Shoptet feed payload as XML.');
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

        $code = $this->trimmed($this->childText($node, 'CODE'))
            ?? $this->trimmed($this->childText($node, 'ITEM_ID'));

        $name = $this->trimmed($this->childText($node, 'PRODUCT'))
            ?? $this->trimmed($this->childText($node, 'PRODUCTNAME'))
            ?? $this->trimmed($this->childText($node, 'NAME'));

        if ($code === null || $name === null) {
            return null;
        }

        return new ParsedProduct(
            code: $code,
            name: $name,
            ean: $this->trimmed($this->childText($node, 'EAN')),
            product_number: $this->trimmed($this->childText($node, 'PRODUCTNO')),
            description: $this->trimmed(
                $this->childText($node, 'DESCRIPTION') ?? $this->childText($node, 'SHORT_DESCRIPTION'),
            ),
            manufacturer: $this->trimmed($this->childText($node, 'MANUFACTURER')),
            price: $this->floatOrNull($this->childText($node, 'PRICE')),
            price_vat: $this->floatOrNull($this->childText($node, 'PRICE_VAT')),
            old_price_vat: $this->floatOrNull($this->childText($node, 'OLD_PRICE_VAT')),
            currency: $this->trimmed($this->childText($node, 'CURRENCY')) ?? 'CZK',
            stock_quantity: $this->intOrNull(
                $this->childText($node, 'STOCK_AMOUNT') ?? $this->childText($node, 'STOCK'),
            ),
            availability: $this->trimmed($this->childText($node, 'AVAILABILITY'))
                ?? $this->trimmed($this->childText($node, 'DELIVERY_DATE')),
            image_url: $this->firstImage($node),
            category_text: $this->lastSegment($this->childText($node, 'CATEGORYTEXT')),
            complete_path: $this->trimmed($this->childText($node, 'CATEGORYTEXT')),
        );
    }

    private function childText(\DOMElement $parent, string $childName): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === $childName) {
                return $child->textContent;
            }
        }

        return null;
    }

    private function firstImage(\DOMElement $parent): ?string
    {
        // Shoptet exports may have multiple <IMGURL>; first one is the primary.
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === 'IMGURL') {
                $value = $this->trimmed($child->textContent);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function lastSegment(?string $path): ?string
    {
        $trimmed = $this->trimmed($path);
        if ($trimmed === null) {
            return null;
        }

        $segments = preg_split('/\s*[|>\/]\s*/', $trimmed) ?: [];
        $segments = array_values(array_filter($segments, fn (string $s): bool => $s !== ''));

        return $segments === [] ? $trimmed : end($segments);
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
