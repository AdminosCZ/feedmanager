<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Fetches the raw feed payload over HTTP for a {@see FeedConfig}, honouring
 * optional basic-auth credentials stored on the config. Wrapped behind an
 * interface-shaped class so the importer can be unit-tested with a fake.
 *
 * @api
 */
class FeedDownloader
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly int $timeoutSeconds = 60,
    ) {
    }

    public function download(FeedConfig $config): string
    {
        $request = $this->http->timeout($this->timeoutSeconds)
            ->withHeaders([
                'User-Agent' => 'ADMINOS Feedmanager/0.1 (+https://adminos.cz)',
                'Accept' => 'application/xml, text/xml',
            ]);

        if ($config->http_username !== null && $config->http_username !== '') {
            $request = $request->withBasicAuth(
                $config->http_username,
                (string) $config->http_password,
            );
        }

        try {
            $response = $request->get($config->source_url);
        } catch (ConnectionException $e) {
            throw new RuntimeException(sprintf(
                'Connection to feed source failed: %s',
                $e->getMessage(),
            ), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Feed source returned HTTP %d for %s.',
                $response->status(),
                $config->source_url,
            ));
        }

        return (string) $response->body();
    }
}
