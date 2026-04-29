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
            short_description: $this->trimmed($this->childText($node, 'SHORT_DESCRIPTION')),
            description: $this->trimmed($this->childText($node, 'DESCRIPTION'))
                ?? $this->trimmed($this->childText($node, 'SHORT_DESCRIPTION')),
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
            gallery_urls: $this->collectAdditionalImages($node),
            parameters: $this->collectParameters($node),
        );
    }

    /**
     * Shoptet supplier-export uses the same `<PARAM><PARAM_NAME>X</PARAM_NAME><VAL>Y</VAL></PARAM>`
     * shape as Heuréka and Zboží.cz.
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
     * Shoptet exports may emit multiple `<IMGURL>` per item — first is primary
     * (already in image_url), rest go into the gallery.
     *
     * @return array<int, string>
     */
    private function collectAdditionalImages(\DOMElement $parent): array
    {
        $urls = [];
        $skippedFirst = false;

        foreach ($parent->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName !== 'IMGURL') {
                continue;
            }
            if (! $skippedFirst) {
                $skippedFirst = true;
                continue;
            }
            $value = $this->trimmed($child->textContent);
            if ($value !== null) {
                $urls[] = $value;
            }
        }

        return $urls;
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
