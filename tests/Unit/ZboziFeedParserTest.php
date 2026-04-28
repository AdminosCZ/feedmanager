<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;
use Adminos\Modules\Feedmanager\Services\Parsing\ZboziFeedParser;
use PHPUnit\Framework\TestCase;

final class ZboziFeedParserTest extends TestCase
{
    public function test_parses_basic_shopitem_with_namespace(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">
  <SHOPITEM>
    <ITEM_ID>ZUUM-125-14-12</ITEM_ID>
    <PRODUCTNAME>Pitbike Markstore Zuumav</PRODUCTNAME>
    <DESCRIPTION>Dětský motocykl 125ccm</DESCRIPTION>
    <URL>https://www.markstore.cz/pitbike-zuumav/</URL>
    <PRICE_VAT>22990,00</PRICE_VAT>
    <DELIVERY_DATE>0</DELIVERY_DATE>
    <SHOP_DEPOTS>120</SHOP_DEPOTS>
    <IMGURL>https://example.com/main.jpg</IMGURL>
    <MANUFACTURER>ZUUMAV</MANUFACTURER>
    <CATEGORYTEXT>Vozidla | Motocykly | Pitbike</CATEGORYTEXT>
    <EAN>8590000000001</EAN>
    <PRODUCTNO>PN-ZUU</PRODUCTNO>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $p = $products[0];
        $this->assertSame('ZUUM-125-14-12', $p->code);
        $this->assertSame('Pitbike Markstore Zuumav', $p->name);
        $this->assertSame('Dětský motocykl 125ccm', $p->description);
        $this->assertSame('ZUUMAV', $p->manufacturer);
        $this->assertSame(22990.0, $p->price_vat);
        $this->assertSame('CZK', $p->currency);
        $this->assertSame('8590000000001', $p->ean);
        $this->assertSame('PN-ZUU', $p->product_number);
        // SHOP_DEPOTS is a Zboží.cz depot ID, not a stock count — parser
        // ignores it on purpose.
        $this->assertNull($p->stock_quantity);
        $this->assertSame('0', $p->availability);
        $this->assertSame('https://example.com/main.jpg', $p->image_url);
        $this->assertSame('Pitbike', $p->category_text);
        $this->assertSame('Vozidla | Motocykly | Pitbike', $p->complete_path);
    }

    public function test_does_not_use_shop_depots_for_stock_count(): void
    {
        // <SHOP_DEPOTS> in Zboží.cz spec is a depot/warehouse ID, not a
        // quantity. Markstore's feed has the same depot ID 120569 across
        // 1338+ products — using it as stock would silently corrupt B2B
        // threshold logic.
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">
  <SHOPITEM>
    <ITEM_ID>NO-STOCK</ITEM_ID>
    <PRODUCTNAME>No stock from depot id</PRODUCTNAME>
    <SHOP_DEPOTS>120569</SHOP_DEPOTS>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertNull($products[0]->stock_quantity);
    }

    public function test_uses_stock_amount_when_present(): void
    {
        // Some Shoptet eshops add an explicit <STOCK_AMOUNT> field to the
        // seznam feed — honour it when the eshop provides it.
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">
  <SHOPITEM>
    <ITEM_ID>EXPLICIT-STOCK</ITEM_ID>
    <PRODUCTNAME>Explicit stock</PRODUCTNAME>
    <STOCK_AMOUNT>7</STOCK_AMOUNT>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertSame(7, $products[0]->stock_quantity);
    }

    public function test_handles_comma_decimal_in_price_vat(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">
  <SHOPITEM>
    <ITEM_ID>PRICE-1</ITEM_ID>
    <PRODUCTNAME>Price test</PRODUCTNAME>
    <PRICE_VAT>1299,99</PRICE_VAT>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertSame(1299.99, $products[0]->price_vat);
    }

    public function test_skips_items_without_code_or_name(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">
  <SHOPITEM><PRODUCTNAME>No id</PRODUCTNAME></SHOPITEM>
  <SHOPITEM><ITEM_ID>NO_NAME</ITEM_ID></SHOPITEM>
  <SHOPITEM><ITEM_ID>OK</ITEM_ID><PRODUCTNAME>Has both</PRODUCTNAME></SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $this->assertSame('OK', $products[0]->code);
    }

    public function test_parses_multiple_items(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">
  <SHOPITEM><ITEM_ID>A</ITEM_ID><PRODUCTNAME>Alpha</PRODUCTNAME></SHOPITEM>
  <SHOPITEM><ITEM_ID>B</ITEM_ID><PRODUCTNAME>Beta</PRODUCTNAME></SHOPITEM>
  <SHOPITEM><ITEM_ID>C</ITEM_ID><PRODUCTNAME>Gamma</PRODUCTNAME></SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertCount(3, $products);
        $this->assertSame(['A', 'B', 'C'], array_map(fn (ParsedProduct $p): string => $p->code, $products));
    }

    public function test_parses_without_namespace(): void
    {
        // Some Shoptet eshops generate seznam exports without the xmlns
        // declaration. Parser must still work.
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>NO-NS</ITEM_ID>
    <PRODUCTNAME>No namespace</PRODUCTNAME>
    <STOCK_AMOUNT>3</STOCK_AMOUNT>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $this->assertSame('NO-NS', $products[0]->code);
        $this->assertSame(3, $products[0]->stock_quantity);
    }

    public function test_handles_cdata_description(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">
  <SHOPITEM>
    <ITEM_ID>CDATA-1</ITEM_ID>
    <PRODUCTNAME>CDATA test</PRODUCTNAME>
    <DESCRIPTION><![CDATA[Multi-line
description with <html>tags</html> & special chars.]]></DESCRIPTION>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);

        $this->assertStringContainsString('Multi-line', $products[0]->description);
        $this->assertStringContainsString('<html>tags</html>', $products[0]->description);
    }

    public function test_returns_no_products_for_empty_shop(): void
    {
        $xml = '<?xml version="1.0"?><SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0"></SHOP>';
        $this->assertSame([], $this->parse($xml));
    }

    public function test_throws_on_invalid_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse('not xml <<<');
    }

    public function test_extracts_last_category_segment(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">
  <SHOPITEM>
    <ITEM_ID>CAT-1</ITEM_ID>
    <PRODUCTNAME>Cat test</PRODUCTNAME>
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
        return iterator_to_array((new ZboziFeedParser())->parse($xml), false);
    }
}
