<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;
use Adminos\Modules\Feedmanager\Services\Parsing\ShoptetFeedParser;
use PHPUnit\Framework\TestCase;

final class ShoptetFeedParserTest extends TestCase
{
    public function test_parses_shoptet_supplier_export(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<SHOP>
  <SHOPITEM>
    <CODE>SKU-1</CODE>
    <PRODUCT>Demo product</PRODUCT>
    <DESCRIPTION>Body</DESCRIPTION>
    <SHORT_DESCRIPTION>Short</SHORT_DESCRIPTION>
    <MANUFACTURER>Acme</MANUFACTURER>
    <PRICE>82.6446</PRICE>
    <PRICE_VAT>99.9999</PRICE_VAT>
    <CURRENCY>CZK</CURRENCY>
    <EAN>8590000000001</EAN>
    <PRODUCTNO>PN-1</PRODUCTNO>
    <STOCK>5</STOCK>
    <AVAILABILITY>skladem</AVAILABILITY>
    <IMGURL>https://example.com/a.jpg</IMGURL>
    <IMGURL>https://example.com/b.jpg</IMGURL>
    <CATEGORYTEXT>Hlavní > Knihy > Beletrie</CATEGORYTEXT>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);
        $this->assertCount(1, $products);

        $p = $products[0];
        $this->assertSame('SKU-1', $p->code);
        $this->assertSame('Demo product', $p->name);
        $this->assertSame('Body', $p->description);
        $this->assertSame('Acme', $p->manufacturer);
        $this->assertSame(82.6446, $p->price);
        $this->assertSame(99.9999, $p->price_vat);
        $this->assertSame('CZK', $p->currency);
        $this->assertSame('8590000000001', $p->ean);
        $this->assertSame('PN-1', $p->product_number);
        $this->assertSame(5, $p->stock_quantity);
        $this->assertSame('skladem', $p->availability);
        $this->assertSame('https://example.com/a.jpg', $p->image_url, 'first IMGURL must win');
        $this->assertSame('Beletrie', $p->category_text);
        $this->assertSame('Hlavní > Knihy > Beletrie', $p->complete_path);
    }

    public function test_falls_back_to_short_description_when_no_description(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<SHOP>
  <SHOPITEM>
    <CODE>X</CODE><PRODUCT>X</PRODUCT>
    <SHORT_DESCRIPTION>Only short</SHORT_DESCRIPTION>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);
        $this->assertSame('Only short', $products[0]->description);
    }

    public function test_falls_back_to_alternative_field_names(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>FALLBACK</ITEM_ID>
    <PRODUCTNAME>Old style name</PRODUCTNAME>
    <STOCK_AMOUNT>3</STOCK_AMOUNT>
    <DELIVERY_DATE>2 dny</DELIVERY_DATE>
  </SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);
        $this->assertSame('FALLBACK', $products[0]->code);
        $this->assertSame('Old style name', $products[0]->name);
        $this->assertSame(3, $products[0]->stock_quantity);
        $this->assertSame('2 dny', $products[0]->availability);
    }

    public function test_skips_items_without_code_or_name(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<SHOP>
  <SHOPITEM><PRODUCT>No code</PRODUCT></SHOPITEM>
  <SHOPITEM><CODE>NO_NAME</CODE></SHOPITEM>
  <SHOPITEM><CODE>OK</CODE><PRODUCT>Both</PRODUCT></SHOPITEM>
</SHOP>
XML;

        $products = $this->parse($xml);
        $this->assertCount(1, $products);
        $this->assertSame('OK', $products[0]->code);
    }

    public function test_throws_on_invalid_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse('garbage');
    }

    /**
     * @return array<int, ParsedProduct>
     */
    private function parse(string $xml): array
    {
        return iterator_to_array((new ShoptetFeedParser())->parse($xml), false);
    }
}
