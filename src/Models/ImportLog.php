<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $feed_config_id
 * @property string $status
 * @property string $triggered_by
 * @property int $products_found
 * @property int $products_new
 * @property int $products_updated
 * @property int $products_failed
 * @property string|null $message
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @api
 */
final class ImportLog extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_CRON = 'cron';

    public const TRIGGER_API = 'api';

    /** @var array<int, string> */
    public const TRIGGERS = [
        self::TRIGGER_MANUAL,
        self::TRIGGER_CRON,
        self::TRIGGER_API,
    ];

    protected $table = 'feedmanager_import_logs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'feed_config_id' => 'integer',
        'products_found' => 'integer',
        'products_new' => 'integer',
        'products_updated' => 'integer',
        'products_failed' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FeedConfig, self>
     */
    public function feedConfig(): BelongsTo
    {
        return $this->belongsTo(FeedConfig::class);
    }
}
