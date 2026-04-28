<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_own
 * @property bool $publish_to_shoptet
 * @property bool $is_active
 * @property string|null $notes
 *
 * @api
 */
final class Supplier extends Model
{
    protected $table = 'feedmanager_suppliers';

    protected $guarded = ['id'];

    protected $attributes = [
        'is_own' => false,
        'publish_to_shoptet' => true,
        'is_active' => true,
    ];

    protected $casts = [
        'is_own' => 'boolean',
        'publish_to_shoptet' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<FeedConfig, self>
     */
    public function feedConfigs(): HasMany
    {
        return $this->hasMany(FeedConfig::class);
    }

    /**
     * @return HasMany<Product, self>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
