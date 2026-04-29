<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $supplier_id
 * @property string $name
 * @property string $source_url
 * @property string $format
 * @property bool $is_active
 * @property bool $auto_update
 * @property string|null $http_username
 * @property string|null $http_password
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property string|null $last_status
 * @property string|null $last_message
 *
 * @api
 */
final class FeedConfig extends Model
{
    public const FORMAT_HEUREKA = 'heureka';

    public const FORMAT_GOOGLE = 'google';

    public const FORMAT_SHOPTET = 'shoptet';

    public const FORMAT_ZBOZI = 'zbozi';

    public const FORMAT_SHOPTET_STOCK_CSV = 'shoptet_stock_csv';

    public const FORMAT_CUSTOM = 'custom';

    /** @var array<int, string> */
    public const FORMATS = [
        self::FORMAT_HEUREKA,
        self::FORMAT_GOOGLE,
        self::FORMAT_SHOPTET,
        self::FORMAT_ZBOZI,
        self::FORMAT_SHOPTET_STOCK_CSV,
        self::FORMAT_CUSTOM,
    ];

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RUNNING = 'running';

    protected $table = 'feedmanager_feed_configs';

    protected $guarded = ['id'];

    protected $attributes = [
        'is_active' => true,
        'auto_update' => false,
        'default_b2b_allowed' => true,
        'update_only_mode' => false,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_update' => 'boolean',
        'default_b2b_allowed' => 'boolean',
        'update_only_mode' => 'boolean',
        'last_run_at' => 'datetime',
        // Laravel's `encrypted` cast uses APP_KEY for AES-256-CBC + HMAC.
        // Encrypted values are larger than plaintext — make sure the column
        // is at least TEXT or VARCHAR(1024).
        'http_password' => 'encrypted',
    ];

    /**
     * @return BelongsTo<Supplier, self>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<ImportLog, self>
     */
    public function importLogs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }

    /**
     * @return HasMany<Product, self>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
