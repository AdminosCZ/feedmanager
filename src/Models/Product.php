<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $supplier_id
 * @property int|null $feed_config_id
 * @property string $code
 * @property string|null $shoptet_id
 * @property string|null $short_description
 * @property string $status
 * @property string|null $ean
 * @property string|null $product_number
 * @property string $name
 * @property string|null $description
 * @property string|null $manufacturer
 * @property string $price
 * @property string $price_vat
 * @property string|null $old_price_vat
 * @property string $currency
 * @property int $stock_quantity
 * @property string|null $availability
 * @property \Illuminate\Support\Carbon|null $delivery_date
 * @property string|null $image_url
 * @property string|null $category_text
 * @property string|null $complete_path
 * @property bool $is_b2b_allowed
 * @property bool $is_b2b_paused
 * @property string|null $b2b_inclusion_override
 * @property bool $is_excluded
 * @property string|null $override_name
 * @property string|null $override_description
 * @property string|null $override_price_vat
 * @property array<int, string>|null $locked_fields
 * @property \Illuminate\Support\Carbon|null $imported_at
 *
 * @api
 */
final class Product extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var array<int, string> */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    public const B2B_OVERRIDE_FORCE_ALLOWED = 'force_allowed';

    public const B2B_OVERRIDE_FORCE_EXCLUDED = 'force_excluded';

    /** @var array<int, string> */
    public const B2B_OVERRIDES = [
        self::B2B_OVERRIDE_FORCE_ALLOWED,
        self::B2B_OVERRIDE_FORCE_EXCLUDED,
    ];

    protected $table = 'feedmanager_products';

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'is_b2b_allowed' => true,
        'is_b2b_paused' => false,
        'is_excluded' => false,
        'currency' => 'CZK',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'price_vat' => 'decimal:4',
        'old_price_vat' => 'decimal:4',
        'override_price_vat' => 'decimal:4',
        'stock_quantity' => 'integer',
        'is_b2b_allowed' => 'boolean',
        'is_b2b_paused' => 'boolean',
        'is_excluded' => 'boolean',
        'b2b_low_stock_threshold' => 'integer',
        'delivery_date' => 'date',
        'imported_at' => 'datetime',
        'locked_fields' => 'array',
    ];

    /**
     * Whether this product is currently emitted into the B2B partner feed.
     * Both gates must be open: the master eligibility flag (set on the
     * "Vlastní katalog" tab) and the temporary pause flag (set on the "Pro
     * partnery" tab) must be in their respective feed-on positions.
     */
    public function isInB2bFeed(): bool
    {
        return $this->is_b2b_allowed === true
            && $this->is_b2b_paused === false
            && $this->is_excluded === false;
    }

    /**
     * Effective name — prefers manual override, falls back to imported value.
     */
    public function effectiveName(): string
    {
        return $this->override_name !== null && $this->override_name !== ''
            ? $this->override_name
            : $this->name;
    }

    /**
     * Effective description — prefers manual override, falls back to imported value.
     */
    public function effectiveDescription(): ?string
    {
        return $this->override_description !== null && $this->override_description !== ''
            ? $this->override_description
            : $this->description;
    }

    /**
     * Effective VAT-inclusive price — prefers manual override, falls back to imported value.
     */
    public function effectivePriceVat(): string
    {
        return $this->override_price_vat ?? $this->price_vat;
    }

    public function isFieldLocked(string $field): bool
    {
        return in_array($field, $this->locked_fields ?? [], true);
    }

    /**
     * @return BelongsTo<Supplier, self>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<FeedConfig, self>
     */
    public function feedConfig(): BelongsTo
    {
        return $this->belongsTo(FeedConfig::class);
    }

    /**
     * @return BelongsTo<SupplierCategory, self>
     */
    public function supplierCategory(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class);
    }

    /**
     * @return BelongsTo<ShoptetCategory, self>
     */
    public function shoptetCategory(): BelongsTo
    {
        return $this->belongsTo(ShoptetCategory::class);
    }

    /**
     * @return HasMany<ProductImage, self>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * @return HasMany<ProductParameter, self>
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(ProductParameter::class)->orderBy('position');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function effectiveImageUrl(): ?string
    {
        $first = $this->images->first();
        if ($first !== null) {
            return $first->url;
        }
        return $this->image_url;
    }
}
