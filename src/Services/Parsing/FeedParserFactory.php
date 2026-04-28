<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use InvalidArgumentException;

/**
 * Resolves a {@see FeedParser} for the format declared on a {@see FeedConfig}.
 *
 * @api
 */
final class FeedParserFactory
{
    public function for(string $format): FeedParser
    {
        return match ($format) {
            FeedConfig::FORMAT_HEUREKA => new HeurekaFeedParser(),
            FeedConfig::FORMAT_GOOGLE => new GoogleShoppingFeedParser(),
            FeedConfig::FORMAT_SHOPTET => new ShoptetFeedParser(),
            FeedConfig::FORMAT_CUSTOM => new CustomFeedParser(),
            default => throw new InvalidArgumentException(sprintf(
                'Unknown feed format "%s". Must be one of: %s.',
                $format,
                implode(', ', FeedConfig::FORMATS),
            )),
        };
    }
}
