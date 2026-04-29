<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Services\Parsing\ParsedCategory;
use Adminos\Modules\Feedmanager\Services\Parsing\ShoptetCategoriesParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ShoptetCategoriesParserTest extends TestCase
{
    public function test_parses_shoptet_categories_csv(): void
    {
        $csv = "id;parentId;parentUrl;expandInMenu;visible;priority;access;title;linkText;url\n"
            ."1;;;0;1;0;system;Dámské;Dámské šperky;https://example.com/damske/\n"
            ."2;1;;0;1;10;system;Prsteny;Dámské prsteny;https://example.com/damske/prsteny/\n"
            ."3;2;;0;0;20;system;Zlato;Zlaté prsteny;https://example.com/damske/prsteny/zlato/\n";

        $parser = new ShoptetCategoriesParser();
        $rows = iterator_to_array($parser->parse($csv));

        $this->assertCount(3, $rows);
        $this->assertInstanceOf(ParsedCategory::class, $rows[0]);
        $this->assertSame(1, $rows[0]->shoptet_id);
        $this->assertNull($rows[0]->parent_shoptet_id);
        $this->assertSame('Dámské', $rows[0]->title);
        $this->assertTrue($rows[0]->visible);

        $this->assertSame(2, $rows[1]->shoptet_id);
        $this->assertSame(1, $rows[1]->parent_shoptet_id);
        $this->assertSame('Prsteny', $rows[1]->title);
        $this->assertSame(10, $rows[1]->priority);

        $this->assertSame(3, $rows[2]->shoptet_id);
        $this->assertSame(2, $rows[2]->parent_shoptet_id);
        $this->assertFalse($rows[2]->visible);
    }

    public function test_parses_shoptet_categories_xml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<SHOP>
  <CATEGORIES>
    <CATEGORY>
      <ID>10</ID>
      <PARENT_ID></PARENT_ID>
      <GUID>damske</GUID>
      <TITLE>Dámské</TITLE>
      <LINK_TEXT>Dámské šperky</LINK_TEXT>
      <PRIORITY>0</PRIORITY>
      <VISIBLE>true</VISIBLE>
    </CATEGORY>
    <CATEGORY>
      <ID>11</ID>
      <PARENT_ID>10</PARENT_ID>
      <TITLE>Prsteny</TITLE>
      <PRIORITY>5</PRIORITY>
      <VISIBLE>true</VISIBLE>
    </CATEGORY>
  </CATEGORIES>
</SHOP>
XML;

        $parser = new ShoptetCategoriesParser();
        $rows = iterator_to_array($parser->parse($xml));

        $this->assertCount(2, $rows);
        $this->assertSame(10, $rows[0]->shoptet_id);
        $this->assertNull($rows[0]->parent_shoptet_id);
        $this->assertSame('Dámské', $rows[0]->title);
        $this->assertSame('damske', $rows[0]->guid);

        $this->assertSame(11, $rows[1]->shoptet_id);
        $this->assertSame(10, $rows[1]->parent_shoptet_id);
        $this->assertSame('Prsteny', $rows[1]->title);
    }

    public function test_skips_rows_without_id_or_title(): void
    {
        $csv = "id;parentId;title\n"
            .";;Bez ID\n"
            ."5;;\n"
            ."6;;Validní\n";

        $parser = new ShoptetCategoriesParser();
        $rows = iterator_to_array($parser->parse($csv));

        $this->assertCount(1, $rows);
        $this->assertSame(6, $rows[0]->shoptet_id);
    }

    public function test_throws_on_empty_payload(): void
    {
        $this->expectException(RuntimeException::class);
        iterator_to_array((new ShoptetCategoriesParser())->parse(''));
    }

    public function test_throws_when_csv_missing_required_columns(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing required columns/');

        iterator_to_array((new ShoptetCategoriesParser())->parse("foo;bar\n1;a\n"));
    }

    public function test_throws_on_malformed_xml(): void
    {
        $this->expectException(RuntimeException::class);
        iterator_to_array((new ShoptetCategoriesParser())->parse('<SHOP><CATEGORIES><CATEGORY></SHOP>'));
    }
}
