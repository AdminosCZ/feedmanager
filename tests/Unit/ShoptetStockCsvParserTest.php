<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;
use Adminos\Modules\Feedmanager\Services\Parsing\ShoptetStockCsvParser;
use PHPUnit\Framework\TestCase;

final class ShoptetStockCsvParserTest extends TestCase
{
    public function test_parses_basic_csv(): void
    {
        $csv = <<<CSV
itemCode;itemName;stock;minStockSupply;stockStatus;daysSinceLastSale;totalPurchasePrice;currency;
"ZUUM-125-14-12";"Pitbike Zuumav";"6";"";"6";"";"";"CZK";
"121_XS";"Helma XS";"30";"";"30";"";"";"CZK";
"DEAD-1";"Sold out";"0";"";"0";"1083";"";"CZK";
CSV;

        $products = $this->parse($csv);

        $this->assertCount(3, $products);

        $this->assertSame('ZUUM-125-14-12', $products[0]->code);
        $this->assertSame('Pitbike Zuumav', $products[0]->name);
        $this->assertSame(6, $products[0]->stock_quantity);

        $this->assertSame('121_XS', $products[1]->code);
        $this->assertSame(30, $products[1]->stock_quantity);

        $this->assertSame('DEAD-1', $products[2]->code);
        $this->assertSame(0, $products[2]->stock_quantity);
    }

    public function test_normalizes_codes_to_match_shoptet_xml_sanitization(): void
    {
        // Stock CSV ships raw codes with `/` and spaces. Shoptet XML feeds
        // (seznam, heureka) sanitize those to `_`. Parser normalizes the
        // CSV side so downstream lookups can use exact match.
        $csv = <<<CSV
itemCode;itemName;stock;
"121/XS";"Variant XS";"5";
"121/S -";"Variant S minus";"3";
"NORMAL-CODE";"Already canonical";"7";
CSV;

        $products = $this->parse($csv);

        $this->assertSame('121_XS', $products[0]->code);
        $this->assertSame('121_S_-', $products[1]->code);
        $this->assertSame('NORMAL-CODE', $products[2]->code);
    }

    public function test_handles_windows_1250_encoding(): void
    {
        // Shoptet ships stock CSV in Windows-1250 (cp1250) with Czech
        // diacritics. Parser must convert to UTF-8 transparently.
        $utf8 = <<<CSV
itemCode;itemName;stock;
"CZ-1";"Dětská čtyřkolka Sonic";"6";
CSV;

        $cp1250 = iconv('UTF-8', 'Windows-1250', $utf8);
        $this->assertNotFalse($cp1250);

        $products = $this->parse($cp1250);

        $this->assertCount(1, $products);
        $this->assertSame('Dětská čtyřkolka Sonic', $products[0]->name);
    }

    public function test_falls_back_to_code_when_name_missing(): void
    {
        $csv = <<<CSV
itemCode;itemName;stock;
"ONLY-CODE";"";"5";
CSV;

        $products = $this->parse($csv);

        // ParsedProduct requires non-null name. Use code as the safe fallback.
        $this->assertSame('ONLY-CODE', $products[0]->name);
    }

    public function test_skips_rows_with_empty_code(): void
    {
        $csv = <<<CSV
itemCode;itemName;stock;
"";"Ghost row";"5";
"VALID";"Valid row";"3";
CSV;

        $products = $this->parse($csv);

        $this->assertCount(1, $products);
        $this->assertSame('VALID', $products[0]->code);
    }

    public function test_handles_non_numeric_stock(): void
    {
        $csv = <<<CSV
itemCode;itemName;stock;
"WEIRD";"Weird stock";"N/A";
"OK";"Numeric";"5";
CSV;

        $products = $this->parse($csv);

        $this->assertNull($products[0]->stock_quantity);
        $this->assertSame(5, $products[1]->stock_quantity);
    }

    public function test_handles_crlf_line_endings(): void
    {
        $csv = "itemCode;itemName;stock;\r\n"
            . "\"A\";\"Alpha\";\"1\";\r\n"
            . "\"B\";\"Beta\";\"2\";\r\n";

        $products = $this->parse($csv);

        $this->assertCount(2, $products);
        $this->assertSame(['A', 'B'], array_map(fn (ParsedProduct $p): string => $p->code, $products));
    }

    public function test_throws_when_required_columns_missing(): void
    {
        $csv = <<<CSV
foo;bar;baz;
"x";"y";"z";
CSV;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing required columns');

        $this->parse($csv);
    }

    public function test_throws_on_empty_payload(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse('');
    }

    public function test_normalizeCode_helper(): void
    {
        $parser = new ShoptetStockCsvParser();

        $this->assertSame('121_XS', $parser->normalizeCode('121/XS'));
        $this->assertSame('121_S_-', $parser->normalizeCode('121/S -'));
        $this->assertSame('FOO_BAR_BAZ', $parser->normalizeCode('FOO BAR BAZ'));
        $this->assertSame('ZUUM-125-14-12', $parser->normalizeCode('ZUUM-125-14-12'));
        $this->assertSame('', $parser->normalizeCode('   '));
    }

    /**
     * @return array<int, ParsedProduct>
     */
    private function parse(string $csv): array
    {
        return iterator_to_array((new ShoptetStockCsvParser())->parse($csv), false);
    }
}
