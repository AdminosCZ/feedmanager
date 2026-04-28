<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $export_config_id
 * @property int $status_code
 * @property int|null $product_count
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @api
 */
final class ExportLog extends Model
{
    protected $table = 'feedmanager_export_logs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'export_config_id' => 'integer',
        'status_code' => 'integer',
        'product_count' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ExportConfig, self>
     */
    public function exportConfig(): BelongsTo
    {
        return $this->belongsTo(ExportConfig::class);
    }
}
