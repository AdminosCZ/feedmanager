<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Services\Parsing\CustomFeedParser;
use Adminos\Modules\Feedmanager\Services\Parsing\FeedParserFactory;
use Adminos\Modules\Feedmanager\Services\Parsing\GoogleShoppingFeedParser;
use Adminos\Modules\Feedmanager\Services\Parsing\HeurekaFeedParser;
use Adminos\Modules\Feedmanager\Services\Parsing\ShoptetFeedParser;
use Adminos\Modules\Feedmanager\Services\Parsing\ZboziFeedParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FeedParserFactoryTest extends TestCase
{
    public function test_returns_heureka_parser_for_heureka_format(): void
    {
        $factory = new FeedParserFactory();
        $this->assertInstanceOf(HeurekaFeedParser::class, $factory->for(FeedConfig::FORMAT_HEUREKA));
    }

    public function test_returns_google_parser_for_google_format(): void
    {
        $factory = new FeedParserFactory();
        $this->assertInstanceOf(GoogleShoppingFeedParser::class, $factory->for(FeedConfig::FORMAT_GOOGLE));
    }

    public function test_returns_shoptet_parser_for_shoptet_format(): void
    {
        $factory = new FeedParserFactory();
        $this->assertInstanceOf(ShoptetFeedParser::class, $factory->for(FeedConfig::FORMAT_SHOPTET));
    }

    public function test_returns_zbozi_parser_for_zbozi_format(): void
    {
        $factory = new FeedParserFactory();
        $this->assertInstanceOf(ZboziFeedParser::class, $factory->for(FeedConfig::FORMAT_ZBOZI));
    }

    public function test_returns_custom_parser_for_custom_format(): void
    {
        $factory = new FeedParserFactory();
        $this->assertInstanceOf(CustomFeedParser::class, $factory->for(FeedConfig::FORMAT_CUSTOM));
    }

    public function test_throws_for_unknown_format(): void
    {
        $factory = new FeedParserFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown feed format');

        $factory->for('xyz');
    }
}
