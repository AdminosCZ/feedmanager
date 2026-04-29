<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $shoptet_id
 * @property int|null $parent_shoptet_id
 * @property string|null $guid
 * @property string $title
 * @property string|null $link_text
 * @property int $priority
 * @property bool $visible
 * @property string|null $full_path
 * @property int $depth
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property bool $is_orphaned
 *
 * @api
 */
final class ShoptetCategory extends Model
{
    protected $table = 'feedmanager_shoptet_categories';

    protected $guarded = ['id'];

    protected $attributes = [
        'is_orphaned' => false,
    ];

    protected $casts = [
        'shoptet_id' => 'integer',
        'parent_shoptet_id' => 'integer',
        'priority' => 'integer',
        'visible' => 'boolean',
        'depth' => 'integer',
        'synced_at' => 'datetime',
        'is_orphaned' => 'boolean',
    ];

    /**
     * @return HasMany<self, self>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_shoptet_id', 'shoptet_id');
    }
}
