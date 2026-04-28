<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_category_id
 * @property int $shoptet_category_id
 *
 * @api
 */
final class CategoryMapping extends Model
{
    protected $table = 'feedmanager_category_mappings';

    protected $guarded = ['id'];

    protected $casts = [
        'supplier_category_id' => 'integer',
        'shoptet_category_id' => 'integer',
    ];

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
}
