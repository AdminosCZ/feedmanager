<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $feed_config_id
 * @property int|null $supplier_id
 * @property string $name
 * @property string $field
 * @property string|null $condition_op
 * @property string|null $condition_value
 * @property string $action
 * @property string|null $action_value
 * @property int $priority
 * @property bool $is_active
 *
 * @api
 */
final class FeedRule extends Model
{
    public const COND_ALWAYS = 'always';

    public const COND_EQ = 'eq';

    public const COND_NEQ = 'neq';

    public const COND_CONTAINS = 'contains';

    public const COND_STARTS_WITH = 'starts_with';

    public const COND_ENDS_WITH = 'ends_with';

    public const COND_GT = 'gt';

    public const COND_LT = 'lt';

    public const COND_MATCHES = 'matches';

    /** @var array<int, string> */
    public const CONDITION_OPS = [
        self::COND_ALWAYS,
        self::COND_EQ,
        self::COND_NEQ,
        self::COND_CONTAINS,
        self::COND_STARTS_WITH,
        self::COND_ENDS_WITH,
        self::COND_GT,
        self::COND_LT,
        self::COND_MATCHES,
    ];

    public const ACTION_SET = 'set';

    public const ACTION_ADD = 'add';

    public const ACTION_SUBTRACT = 'subtract';

    public const ACTION_MULTIPLY = 'multiply';

    public const ACTION_DIVIDE = 'divide';

    public const ACTION_REPLACE = 'replace';

    public const ACTION_PREPEND = 'prepend';

    public const ACTION_APPEND = 'append';

    public const ACTION_ROUND = 'round';

    public const ACTION_REMOVE = 'remove';

    /** @var array<int, string> */
    public const ACTIONS = [
        self::ACTION_SET,
        self::ACTION_ADD,
        self::ACTION_SUBTRACT,
        self::ACTION_MULTIPLY,
        self::ACTION_DIVIDE,
        self::ACTION_REPLACE,
        self::ACTION_PREPEND,
        self::ACTION_APPEND,
        self::ACTION_ROUND,
        self::ACTION_REMOVE,
    ];

    protected $table = 'feedmanager_feed_rules';

    protected $guarded = ['id'];

    protected $attributes = [
        'is_active' => true,
        'priority' => 100,
        'condition_op' => self::COND_ALWAYS,
    ];

    protected $casts = [
        'feed_config_id' => 'integer',
        'supplier_id' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<FeedConfig, self>
     */
    public function feedConfig(): BelongsTo
    {
        return $this->belongsTo(FeedConfig::class);
    }

    /**
     * @return BelongsTo<Supplier, self>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
