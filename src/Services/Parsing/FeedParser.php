<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Generator;

/**
 * @api
 */
interface FeedParser
{
    /**
     * Parse a single feed payload and yield products lazily so the importer
     * can stream upserts without holding the whole catalog in memory.
     *
     * @return Generator<int, ParsedProduct>
     */
    public function parse(string $payload): Generator;
}
