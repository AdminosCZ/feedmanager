<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Services\Parsing\CustomFeedParser;
use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;
use PHPUnit\Framework\TestCase;

final class CustomFeedParserTest extends TestCase
{
    public function test_handles_simple_product_wrapper(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<root>
  <product>
    <id>SKU-1</id>
    <title>Demo</title>
    <description>Body</description>
    <price>99.50</price>
    <stock>3</stock>
  </product>
</root>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $this->assertSame('SKU-1', $products[0]->code);
        $this->assertSame('Demo', $products[0]->name);
        $this->assertSame('Body', $products[0]->description);
        $this->assertSame(99.5, $products[0]->price);
        $this->assertSame(3, $products[0]->stock_quantity);
    }

    public function test_handles_zbozi_wrapper(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<root>
  <ZBOZI>
    <CODE>SKU-Z</CODE>
    <NAME>Zboží item</NAME>
    <PRICE_VAT>50.00</PRICE_VAT>
  </ZBOZI>
</root>
XML;

        $products = $this->parse($xml);

        $this->assertSame('SKU-Z', $products[0]->code);
        $this->assertSame('Zboží item', $products[0]->name);
        $this->assertSame(50.0, $products[0]->price_vat);
    }

    public function test_throws_when_no_recognised_wrapper_found(): void
    {
        $xml = '<?xml version="1.0"?><root><weirdthing>X</weirdthing></root>';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not find any of the recognised wrapper');
        $this->parse($xml);
    }

    public function test_throws_on_invalid_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse('not xml at all');
    }

    public function test_skips_items_without_id_or_name(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<root>
  <product><title>No id</title></product>
  <product><id>NO_NAME</id></product>
  <product><id>OK</id><title>Has both</title></product>
</root>
XML;

        $products = $this->parse($xml);

        $this->assertCount(1, $products);
        $this->assertSame('OK', $products[0]->code);
    }

    /**
     * @return array<int, ParsedProduct>
     */
    private function parse(string $xml): array
    {
        return iterator_to_array((new CustomFeedParser())->parse($xml), false);
    }
}
