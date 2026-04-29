<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Generator;
use RuntimeException;
use XMLReader;

/**
 * Parses Google Merchant Center XML feeds.
 *
 * Schema:
 *   <rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
 *     <channel>
 *       <item>
 *         <g:id>SKU-1</g:id>
 *         <g:title>Demo</g:title>
 *         <g:description>...</g:description>
 *         <g:price>99.99 CZK</g:price>
 *         <g:availability>in stock</g:availability>
 *         <g:image_link>https://...</g:image_link>
 *         <g:brand>Acme</g:brand>
 *         <g:gtin>8590000000001</g:gtin>
 *         <g:mpn>PN-1</g:mpn>
 *         <g:product_type>Books > Fiction</g:product_type>
 *       </item>
 *
 * Reference: https://support.google.com/merchants/answer/7052112
 *
 * @api
 */
final class GoogleShoppingFeedParser implements FeedParser
{
    public function parse(string $payload): Generator
    {
        if (trim($payload) === '' || ! str_contains($payload, '<')) {
            throw new RuntimeException('Failed to open Google Shopping feed payload as XML.');
        }

        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        if (! $reader->XML($payload)) {
            libxml_use_internal_errors($previous);
            throw new RuntimeException('Failed to open Google Shopping feed payload as XML.');
        }

        try {
            $sawElement = false;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    $sawElement = true;
                }

                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'item') {
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
                throw new RuntimeException('Failed to open Google Shopping feed payload as XML.');
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

        $code = $this->trimmed($this->gChild($node, 'id'));
        $name = $this->trimmed($this->gChild($node, 'title'));

        if ($code === null || $name === null) {
            return null;
        }

        $priceField = $this->parsePrice($this->gChild($node, 'price'));
        $oldPriceField = $this->parsePrice($this->gChild($node, 'sale_price'));

        $primaryImage = $this->trimmed($this->gChild($node, 'image_link'));
        $galleryUrls = $this->collectAdditionalImages($node);

        return new ParsedProduct(
            code: $code,
            name: $name,
            ean: $this->trimmed($this->gChild($node, 'gtin')),
            product_number: $this->trimmed($this->gChild($node, 'mpn')),
            short_description: null,
            description: $this->trimmed($this->gChild($node, 'description')),
            manufacturer: $this->trimmed($this->gChild($node, 'brand')),
            price: null,
            price_vat: $priceField['amount'],
            old_price_vat: $oldPriceField['amount'],
            currency: $priceField['currency'] ?? 'CZK',
            stock_quantity: null,
            availability: $this->mapAvailability($this->trimmed($this->gChild($node, 'availability'))),
            image_url: $primaryImage,
            category_text: $this->lastSegment($this->gChild($node, 'product_type')),
            complete_path: $this->trimmed($this->gChild($node, 'product_type')),
            gallery_urls: $galleryUrls,
            parameters: $this->collectParameters($node),
        );
    }

    /**
     * Google Shopping parameters live inside `<g:product_detail>` blocks:
     *   <g:product_detail>
     *     <g:attribute_name>Color</g:attribute_name>
     *     <g:attribute_value>Red</g:attribute_value>
     *   </g:product_detail>
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
            if ($child->localName !== 'product_detail') {
                continue;
            }

            $name = null;
            $value = null;
            foreach ($child->childNodes as $sub) {
                if (! $sub instanceof \DOMElement) {
                    continue;
                }
                if ($sub->localName === 'attribute_name') {
                    $name = trim($sub->textContent);
                } elseif ($sub->localName === 'attribute_value') {
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
     * Google Shopping uses one `<g:image_link>` plus zero or more
     * `<g:additional_image_link>`. Collect the additional ones for the gallery
     * relation. The primary image is intentionally not duplicated here.
     *
     * @return array<int, string>
     */
    private function collectAdditionalImages(\DOMElement $parent): array
    {
        $urls = [];

        foreach ($parent->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'additional_image_link') {
                $value = $this->trimmed($child->textContent);
                if ($value !== null) {
                    $urls[] = $value;
                }
            }
        }

        return $urls;
    }

    private function gChild(\DOMElement $parent, string $localName): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child->textContent;
            }
        }

        return null;
    }

    /**
     * Google price field looks like "99.99 CZK" — split into amount + currency.
     *
     * @return array{amount: ?float, currency: ?string}
     */
    private function parsePrice(?string $value): array
    {
        $trimmed = $this->trimmed($value);

        if ($trimmed === null) {
            return ['amount' => null, 'currency' => null];
        }

        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*([A-Z]{3})?$/', $trimmed, $m)) {
            return [
                'amount' => (float) str_replace(',', '.', $m[1]),
                'currency' => isset($m[2]) && $m[2] !== '' ? $m[2] : null,
            ];
        }

        return ['amount' => null, 'currency' => null];
    }

    private function mapAvailability(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            'in stock', 'in_stock' => 'skladem',
            'out of stock', 'out_of_stock' => 'vyprodáno',
            'preorder' => 'na objednávku',
            'backorder' => 'na objednávku',
            default => $value,
        };
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
}
