<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

/**
 * Format-agnostic product representation produced by parsers and consumed by
 * {@see \Adminos\Modules\Feedmanager\Services\FeedImporter}. Fields map 1:1 to
 * the columns parsers actually fill in; nullable fields are absent in the
 * source feed.
 *
 * @api
 */
final class ParsedProduct
{
    /**
     * @param  array<int, string>  $gallery_urls  Additional images beyond the primary `image_url`. Populates {@see \Adminos\Modules\Feedmanager\Models\ProductImage}.
     * @param  array<int, array{name: string, value: string}>  $parameters  Custom product parameters (e.g. color, size). Populates {@see \Adminos\Modules\Feedmanager\Models\ProductParameter}.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $ean = null,
        public readonly ?string $product_number = null,
        public readonly ?string $short_description = null,
        public readonly ?string $description = null,
        public readonly ?string $manufacturer = null,
        public readonly ?float $price = null,
        public readonly ?float $price_vat = null,
        public readonly ?float $old_price_vat = null,
        public readonly string $currency = 'CZK',
        public readonly ?int $stock_quantity = null,
        public readonly ?string $availability = null,
        public readonly ?string $image_url = null,
        public readonly ?string $category_text = null,
        public readonly ?string $complete_path = null,
        public readonly array $gallery_urls = [],
        public readonly array $parameters = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * Returns only non-null fields so that null values don't get written into
     * NOT-NULL columns with DB-level defaults (e.g. `price`, `stock_quantity`).
     */
    public function toAttributes(): array
    {
        $candidates = [
            'name' => $this->name,
            'ean' => $this->ean,
            'product_number' => $this->product_number,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'manufacturer' => $this->manufacturer,
            'price' => $this->price,
            'price_vat' => $this->price_vat,
            'old_price_vat' => $this->old_price_vat,
            'currency' => $this->currency,
            'stock_quantity' => $this->stock_quantity,
            'availability' => $this->availability,
            'image_url' => $this->image_url,
            'category_text' => $this->category_text,
            'complete_path' => $this->complete_path,
        ];

        return array_filter($candidates, fn ($value): bool => $value !== null);
    }
}
