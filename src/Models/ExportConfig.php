<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $access_hash
 * @property string $format
 * @property bool $is_active
 * @property string $price_mode
 * @property string $category_mode
 * @property string $excluded_mode
 * @property array<int, string>|null $field_whitelist
 * @property array<int, int>|null $supplier_filter
 * @property array<string, string>|null $extra_flags
 * @property string|null $notes
 *
 * @api
 */
final class ExportConfig extends Model
{
    public const FORMAT_SHOPTET = 'shoptet';

    public const FORMAT_HEUREKA = 'heureka';

    public const FORMAT_GLAMI = 'glami';

    public const FORMAT_ZBOZI = 'zbozi';

    /** @var array<int, string> */
    public const FORMATS = [
        self::FORMAT_SHOPTET,
        self::FORMAT_HEUREKA,
        self::FORMAT_GLAMI,
        self::FORMAT_ZBOZI,
    ];

    public const PRICE_WITH_VAT = 'with_vat';

    public const PRICE_WITHOUT_VAT = 'without_vat';

    public const CATEGORY_FULL_PATH = 'full_path';

    public const CATEGORY_LAST_LEAF = 'last_leaf';

    public const EXCLUDED_SKIP = 'skip';

    public const EXCLUDED_HIDDEN = 'hidden';

    protected $table = 'feedmanager_export_configs';

    protected $guarded = ['id'];

    protected $attributes = [
        'is_active' => true,
        'price_mode' => self::PRICE_WITH_VAT,
        'category_mode' => self::CATEGORY_FULL_PATH,
        'excluded_mode' => self::EXCLUDED_SKIP,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'field_whitelist' => 'array',
        'supplier_filter' => 'array',
        'extra_flags' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $config): void {
            if (empty($config->access_hash)) {
                $config->access_hash = self::generateHash();
            }
        });
    }

    public static function generateHash(): string
    {
        return Str::random(64);
    }

    public function regenerateHash(): self
    {
        $this->access_hash = self::generateHash();
        $this->save();

        return $this;
    }

    /**
     * @return HasMany<ExportLog, self>
     */
    public function exportLogs(): HasMany
    {
        return $this->hasMany(ExportLog::class);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
