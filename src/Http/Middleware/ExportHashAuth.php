<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Http\Middleware;

use Adminos\Modules\Feedmanager\Models\ExportConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves an ExportConfig from `{slug}` and validates the `{hash}` path
 * segment against `ExportConfig.access_hash` using a constant-time
 * comparison. Attaches the config to the request as
 * `feedmanager.export_config`.
 *
 * @api
 */
final class ExportHashAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('slug', '');
        $providedHash = (string) $request->route('hash', '');

        if ($slug === '') {
            return response()->json(['error' => 'Missing export slug.'], 404);
        }

        $config = ExportConfig::query()->where('slug', $slug)->first();

        if ($config === null) {
            return response()->json(['error' => 'Export not found.'], 404);
        }

        if (! $config->isActive()) {
            return response()->json(['error' => 'Export is disabled.'], 403);
        }

        if ($providedHash === '' || ! hash_equals($config->access_hash, $providedHash)) {
            return response()->json(['error' => 'Invalid hash.'], 403);
        }

        $request->attributes->set('feedmanager.export_config', $config);

        return $next($request);
    }
}
