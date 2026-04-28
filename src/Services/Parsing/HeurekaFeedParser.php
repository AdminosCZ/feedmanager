<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Generator;
use RuntimeException;
use XMLReader;

/**
 * Parses Heuréka-style XML feeds (`<SHOP><SHOPITEM>…</SHOPITEM></SHOP>`).
 *
 * Uses {@see XMLReader} for streaming so memory stays flat regardless of feed
 * size — common Heuréka feeds run into hundreds of megabytes.
 *
 * Reference: https://sluzby.heureka.cz/napoveda/xml-feed/
 *
 * @api
 */
final class HeurekaFeedParser implements FeedParser
{
    public function parse(string $payload): Generator
    {
        if (trim($payload) === '' || ! str_contains($payload, '<')) {
            throw new RuntimeException('Failed to open Heureka feed payload as XML.');
        }

        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        if (! $reader->XML($payload)) {
            libxml_use_internal_errors($previous);
            throw new RuntimeException('Failed to open Heureka feed payload as XML.');
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
                throw new RuntimeException('Failed to open Heureka feed payload as XML.');
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
            description: $this->trimmed($this->childText($node, 'DESCRIPTION')),
            manufacturer: $this->trimmed($this->childText($node, 'MANUFACTURER')),
            price: $this->floatOrNull($this->childText($node, 'PRICE')),
            price_vat: $this->floatOrNull($this->childText($node, 'PRICE_VAT')),
            old_price_vat: $this->floatOrNull($this->childText($node, 'OLD_PRICE_VAT')),
            currency: $this->trimmed($this->childText($node, 'CURRENCY')) ?? 'CZK',
            stock_quantity: $this->intOrNull($this->childText($node, 'STOCK_AMOUNT')),
            availability: $this->trimmed($this->childText($node, 'DELIVERY_DATE'))
                ?? $this->trimmed($this->childText($node, 'AVAILABILITY')),
            image_url: $this->trimmed($this->childText($node, 'IMGURL')),
            category_text: $this->lastCategorySegment($node),
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
