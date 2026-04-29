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
            short_description: $this->trimmed($this->childText($node, 'SHORT_DESCRIPTION')),
            description: $this->trimmed($this->childText($node, 'DESCRIPTION')),
            manufacturer: $this->trimmed($this->childText($node, 'MANUFACTURER')),
            price: $this->floatOrNull($this->childText($node, 'PRICE')),
            price_vat: $this->floatOrNull($this->childText($node, 'PRICE_VAT')),
            old_price_vat: $this->floatOrNull($this->childText($node, 'OLD_PRICE_VAT')),
            currency: $this->trimmed($this->childText($node, 'CURRENCY')) ?? 'CZK',
            stock_quantity: $this->intOrNull($this->childText($node, 'STOCK_AMOUNT')),
            availability: $this->trimmed($this->childText($node, 'DELIVERY_DATE'))
                ?? $this->trimmed($this->childText($node, 'AVAILABILITY')),
            image_url: $this->firstImage($node),
            category_text: $this->lastCategorySegment($node),
            complete_path: $this->trimmed($this->childText($node, 'CATEGORYTEXT')),
            gallery_urls: $this->collectAdditionalImages($node),
            parameters: $this->collectParameters($node),
        );
    }

    /**
     * Heuréka uses the same `<PARAM><PARAM_NAME>X</PARAM_NAME><VAL>Y</VAL></PARAM>`
     * shape as Zboží.cz.
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
     * @return array<int, string>
     */
    private function collectAdditionalImages(\DOMElement $parent): array
    {
        $urls = [];
        $skippedFirstPrimary = false;

        foreach ($parent->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            // Heuréka may emit multiple <IMGURL> elements (alternates) plus
            // sometimes <IMGURL_ALTERNATIVE> for explicit alts. Treat the first
            // <IMGURL> as primary (already exposed via image_url) and collect
            // the rest.
            if ($child->localName === 'IMGURL') {
                if (! $skippedFirstPrimary) {
                    $skippedFirstPrimary = true;
                    continue;
                }
                $value = $this->trimmed($child->textContent);
                if ($value !== null) {
                    $urls[] = $value;
                }
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

    private function firstImage(\DOMElement $parent): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'IMGURL') {
                $value = $this->trimmed($child->textContent);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
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
