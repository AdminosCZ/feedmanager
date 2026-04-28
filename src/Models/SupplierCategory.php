<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int|null $feed_config_id
 * @property string $original_name
 * @property string $original_path
 * @property int $product_count
 *
 * @api
 */
final class SupplierCategory extends Model
{
    protected $table = 'feedmanager_supplier_categories';

    protected $guarded = ['id'];

    protected $casts = [
        'supplier_id' => 'integer',
        'feed_config_id' => 'integer',
        'product_count' => 'integer',
    ];

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
     * @return HasOne<CategoryMapping, self>
     */
    public function mapping(): HasOne
    {
        return $this->hasOne(CategoryMapping::class);
    }
}
