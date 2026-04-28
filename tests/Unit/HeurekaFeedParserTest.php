<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Services\Parsing\HeurekaFeedParser;
use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;
use PHPUnit\Framework\TestCase;

final class HeurekaFeedParserTest extends TestCase
{
    public function test_parses_basic_shopitem(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo product</PRODUCTNAME>
    <DESCRIPTION>Demo description</DESCRIPTION>
    <MANUFACTURER>Acme</MANUFACTURER>
    <PRICE>82.6446</PRICE>
    <PRICE_VAT>99.9999</PRICE_VAT>
    <CURRENCY>CZK</CURRENCY>
    <EAN>8590000000001</EAN>
    <PRODUCTNO>PN-1</PRODUCTNO>
    <STOCK_AMOUNT>5</STOCK_AMOUNT>
    <DELIVERY_DATE>skladem</DELIVERY_DATE>
    <IMGURL>https://example.com/a.jpg</IMGURL>
    <CATEGORYTEXT>Hlavní | Knihy | Beletrie</CATEGORYTEXT>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $p = $products[0];
        $this->assertSame('SKU-1', $p->code);
        $this->assertSame('Demo product', $p->name);
        $this->assertSame('Demo description', $p->description);
        $this->assertSame('Acme', $p->manufacturer);
        $this->assertSame(82.6446, $p->price);
        $this->assertSame(99.9999, $p->price_vat);
        $this->assertSame('CZK', $p->currency);
        $this->assertSame('8590000000001', $p->ean);
        $this->assertSame('PN-1', $p->product_number);
        $this->assertSame(5, $p->stock_quantity);
        $this->assertSame('skladem', $p->availability);
        $this->assertSame('https://example.com/a.jpg', $p->image_url);
        $this->assertSame('Beletrie', $p->category_text);
        $this->assertSame('Hlavní | Knihy | Beletrie', $p->complete_path);
    }

    public function test_parses_multiple_items(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM><ITEM_ID>A</ITEM_ID><PRODUCTNAME>Alpha</PRODUCTNAME></SHOPITEM>
  <SHOPITEM><ITEM_ID>B</ITEM_ID><PRODUCTNAME>Beta</PRODUCTNAME></SHOPITEM>
  <SHOPITEM><ITEM_ID>C</ITEM_ID><PRODUCTNAME>Gamma</PRODUCTNAME></SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertCount(3, $products);
        $this->assertSame(['A', 'B', 'C'], array_map(fn (ParsedProduct $p): string => $p->code, $products));
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], array_map(fn (ParsedProduct $p): string => $p->name, $products));
    }

    public function test_skips_items_without_code_or_name(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM><PRODUCTNAME>No code</PRODUCTNAME></SHOPITEM>
  <SHOPITEM><ITEM_ID>NO_NAME</ITEM_ID></SHOPITEM>
  <SHOPITEM><ITEM_ID>OK</ITEM_ID><PRODUCTNAME>Has both</PRODUCTNAME></SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $this->assertSame('OK', $products[0]->code);
    }

    public function test_falls_back_to_alternative_field_names(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <PRODUCT_ID>FALLBACK-1</PRODUCT_ID>
    <PRODUCT>Fallback name</PRODUCT>
    <AVAILABILITY>na objednanou</AVAILABILITY>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $this->assertSame('FALLBACK-1', $products[0]->code);
        $this->assertSame('Fallback name', $products[0]->name);
        $this->assertSame('na objednanou', $products[0]->availability);
    }

    public function test_handles_comma_decimal_separator(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>X</ITEM_ID>
    <PRODUCTNAME>X</PRODUCTNAME>
    <PRICE_VAT>99,50</PRICE_VAT>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertSame(99.5, $products[0]->price_vat);
    }

    public function test_returns_no_products_for_empty_shop(): void
    {
        $xml = '<?xml version="1.0"?><SHOP></SHOP>';
        $this->assertSame([], $this->parse($xml));
    }

    public function test_throws_on_invalid_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse('not xml at all <<<');
    }

    public function test_extracts_last_segment_for_category_text(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>X</ITEM_ID>
    <PRODUCTNAME>X</PRODUCTNAME>
    <CATEGORYTEXT>Top > Mid > Leaf</CATEGORYTEXT>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertSame('Leaf', $products[0]->category_text);
        $this->assertSame('Top > Mid > Leaf', $products[0]->complete_path);
    }

    /**
     * @return array<int, ParsedProduct>
     */
    private function parse(string $xml): array
    {
        return iterator_to_array((new HeurekaFeedParser())->parse($xml), false);
    }
}
