<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Http\Controllers;

use Adminos\Modules\Feedmanager\Models\ExportConfig;
use Adminos\Modules\Feedmanager\Models\ExportLog;
use Adminos\Modules\Feedmanager\Services\B2cFeedExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @api
 */
final class B2cFeedController
{
    public function __construct(
        private readonly B2cFeedExporter $exporter,
    ) {
    }

    public function show(Request $request): Response
    {
        /** @var ExportConfig $config */
        $config = $request->attributes->get('feedmanager.export_config');

        $result = $this->exporter->export($config);

        ExportLog::query()->create([
            'export_config_id' => $config->id,
            'status_code' => 200,
            'product_count' => $result['count'],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1024),
        ]);

        return response($result['xml'], 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Feedmanager-Format' => $config->format,
            'X-Feedmanager-Count' => (string) $result['count'],
        ]);
    }
}
