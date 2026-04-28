<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Services\Parsing\GoogleShoppingFeedParser;
use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;
use PHPUnit\Framework\TestCase;

final class GoogleShoppingFeedParserTest extends TestCase
{
    public function test_parses_basic_item_with_g_namespace(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
  <channel>
    <item>
      <g:id>SKU-1</g:id>
      <g:title>Demo</g:title>
      <g:description>Body</g:description>
      <g:price>99.99 CZK</g:price>
      <g:availability>in stock</g:availability>
      <g:image_link>https://example.com/a.jpg</g:image_link>
      <g:brand>Acme</g:brand>
      <g:gtin>8590000000001</g:gtin>
      <g:mpn>PN-1</g:mpn>
      <g:product_type>Books > Fiction</g:product_type>
    </item>
  </channel>
</rss>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $p = $products[0];
        $this->assertSame('SKU-1', $p->code);
        $this->assertSame('Demo', $p->name);
        $this->assertSame('Body', $p->description);
        $this->assertSame(99.99, $p->price_vat);
        $this->assertSame('CZK', $p->currency);
        $this->assertSame('skladem', $p->availability);
        $this->assertSame('https://example.com/a.jpg', $p->image_url);
        $this->assertSame('Acme', $p->manufacturer);
        $this->assertSame('8590000000001', $p->ean);
        $this->assertSame('PN-1', $p->product_number);
        $this->assertSame('Fiction', $p->category_text);
        $this->assertSame('Books > Fiction', $p->complete_path);
    }

    public function test_skips_items_without_id_or_title(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
  <channel>
    <item><g:title>No id</g:title></item>
    <item><g:id>NO_TITLE</g:id></item>
    <item><g:id>OK</g:id><g:title>Has both</g:title></item>
  </channel>
</rss>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $this->assertSame('OK', $products[0]->code);
    }

    public function test_maps_availability_synonyms(): void
    {
        $cases = [
            ['in stock', 'skladem'],
            ['in_stock', 'skladem'],
            ['out of stock', 'vyprodáno'],
            ['out_of_stock', 'vyprodáno'],
            ['preorder', 'na objednávku'],
            ['backorder', 'na objednávku'],
            ['custom-string', 'custom-string'],
        ];

        foreach ($cases as [$input, $expected]) {
            $xml = sprintf(<<<'XML'
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
  <channel>
    <item><g:id>X</g:id><g:title>X</g:title><g:availability>%s</g:availability></item>
  </channel>
</rss>
XML
            , $input);

            $products = $this->parse($xml);
            $this->assertSame($expected, $products[0]->availability, "Mapping failed for '$input'");
        }
    }

    public function test_throws_on_invalid_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse('not xml at all');
    }

    /**
     * @return array<int, ParsedProduct>
     */
    private function parse(string $xml): array
    {
        return iterator_to_array((new GoogleShoppingFeedParser())->parse($xml), false);
    }
}
