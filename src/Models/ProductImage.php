<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $url
 * @property int $position
 *
 * @api
 */
final class ProductImage extends Model
{
    protected $table = 'feedmanager_product_images';

    protected $guarded = ['id'];

    protected $casts = [
        'product_id' => 'integer',
        'position' => 'integer',
    ];

    /**
     * @return BelongsTo<Product, self>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
