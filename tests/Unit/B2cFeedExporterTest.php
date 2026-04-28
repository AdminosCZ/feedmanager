<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Models\ExportConfig;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Services\B2cFeedExporter;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

final class B2cFeedExporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_shoptet_xml_with_full_field_set(): void
    {
        Product::query()->create([
            'code' => 'SKU-1',
            'name' => 'Demo',
            'description' => '<p>Body</p>',
            'manufacturer' => 'Acme',
            'price' => '82.6446',
            'price_vat' => '99.9999',
            'old_price_vat' => '129.0000',
            'currency' => 'CZK',
            'ean' => '8590000000001',
            'product_number' => 'PN-1',
            'image_url' => 'https://example.com/a.jpg',
            'category_text' => 'Knihy',
            'complete_path' => 'Hlavní > Knihy',
            'stock_quantity' => 5,
            'availability' => 'skladem',
            'is_b2b_allowed' => true,
            'is_excluded' => false,
        ]);

        $config = $this->shoptetConfig();
        $result = $this->exporter()->export($config);

        $this->assertSame(1, $result['count']);
        $xml = $result['xml'];
        $this->assertStringContainsString('<SHOP', $xml);
        $this->assertStringContainsString('format="shoptet"', $xml);
        $this->assertStringContainsString('count="1"', $xml);
        $this->assertStringContainsString('<SHOPITEM>', $xml);
        $this->assertStringContainsString('<CODE>SKU-1</CODE>', $xml);
        $this->assertStringContainsString('<NAME>Demo</NAME>', $xml);
        $this->assertStringContainsString('<![CDATA[<p>Body</p>]]>', $xml);
        $this->assertStringContainsString('<MANUFACTURER>Acme</MANUFACTURER>', $xml);
        $this->assertStringContainsString('<PRICE_VAT>99.9999</PRICE_VAT>', $xml);
        $this->assertStringContainsString('<OLD_PRICE_VAT>129.0000</OLD_PRICE_VAT>', $xml);
        $this->assertStringContainsString('<CURRENCY>CZK</CURRENCY>', $xml);
        $this->assertStringContainsString('<EAN>8590000000001</EAN>', $xml);
        $this->assertStringContainsString('<IMGURL>https://example.com/a.jpg</IMGURL>', $xml);
        $this->assertStringContainsString('<CATEGORYTEXT>Hlavní &gt; Knihy</CATEGORYTEXT>', $xml);
        $this->assertStringContainsString('<AVAILABILITY>skladem</AVAILABILITY>', $xml);
        $this->assertStringContainsString('<STOCK>5</STOCK>', $xml);
    }

    public function test_skips_excluded_when_mode_is_skip(): void
    {
        Product::query()->create([
            'code' => 'KEEP', 'name' => 'Keep', 'price_vat' => '10', 'is_excluded' => false,
        ]);
        Product::query()->create([
            'code' => 'DROP', 'name' => 'Drop', 'price_vat' => '20', 'is_excluded' => true,
        ]);

        $config = $this->shoptetConfig(['excluded_mode' => ExportConfig::EXCLUDED_SKIP]);
        $xml = $this->exporter()->export($config)['xml'];

        $this->assertStringContainsString('<CODE>KEEP</CODE>', $xml);
        $this->assertStringNotContainsString('DROP', $xml);
    }

    public function test_emits_visibility_hidden_when_mode_is_hidden(): void
    {
        Product::query()->create([
            'code' => 'HIDE', 'name' => 'Hide me', 'price_vat' => '20', 'is_excluded' => true,
        ]);

        $config = $this->shoptetConfig(['excluded_mode' => ExportConfig::EXCLUDED_HIDDEN]);
        $result = $this->exporter()->export($config);

        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('<VISIBILITY>visibility_hidden</VISIBILITY>', $result['xml']);
    }

    public function test_price_mode_without_vat_emits_price_element(): void
    {
        Product::query()->create([
            'code' => 'X', 'name' => 'X',
            'price' => '82.6446', 'price_vat' => '99.9999',
        ]);

        $config = $this->shoptetConfig(['price_mode' => ExportConfig::PRICE_WITHOUT_VAT]);
        $xml = $this->exporter()->export($config)['xml'];

        $this->assertStringContainsString('<PRICE>82.6446</PRICE>', $xml);
        $this->assertStringNotContainsString('<PRICE_VAT>', $xml);
    }

    public function test_category_mode_full_path_uses_complete_path(): void
    {
        Product::query()->create([
            'code' => 'X', 'name' => 'X',
            'price_vat' => '10',
            'category_text' => 'Beletrie',
            'complete_path' => 'Hlavní > Knihy > Beletrie',
        ]);

        $config = $this->shoptetConfig(['category_mode' => ExportConfig::CATEGORY_FULL_PATH]);
        $xml = $this->exporter()->export($config)['xml'];

        $this->assertStringContainsString('<CATEGORYTEXT>Hlavní &gt; Knihy &gt; Beletrie</CATEGORYTEXT>', $xml);
    }

    public function test_category_mode_last_leaf_uses_category_text(): void
    {
        Product::query()->create([
            'code' => 'X', 'name' => 'X',
            'price_vat' => '10',
            'category_text' => 'Beletrie',
            'complete_path' => 'Hlavní > Knihy > Beletrie',
        ]);

        $config = $this->shoptetConfig(['category_mode' => ExportConfig::CATEGORY_LAST_LEAF]);
        $xml = $this->exporter()->export($config)['xml'];

        $this->assertStringContainsString('<CATEGORYTEXT>Beletrie</CATEGORYTEXT>', $xml);
    }

    public function test_field_whitelist_drops_non_listed_fields(): void
    {
        Product::query()->create([
            'code' => 'X', 'name' => 'X',
            'price_vat' => '10',
            'description' => 'Body',
            'manufacturer' => 'Acme',
            'ean' => '12345',
        ]);

        $config = $this->shoptetConfig([
            'field_whitelist' => ['CODE', 'NAME', 'PRICE_VAT'],
        ]);
        $xml = $this->exporter()->export($config)['xml'];

        $this->assertStringContainsString('<CODE>X</CODE>', $xml);
        $this->assertStringContainsString('<NAME>X</NAME>', $xml);
        $this->assertStringContainsString('<PRICE_VAT>10', $xml);
        $this->assertStringNotContainsString('<DESCRIPTION>', $xml);
        $this->assertStringNotContainsString('<MANUFACTURER>', $xml);
        $this->assertStringNotContainsString('<EAN>', $xml);
    }

    public function test_supplier_filter_limits_to_listed_suppliers(): void
    {
        $a = Supplier::query()->create(['name' => 'A', 'slug' => 'sup-a']);
        $b = Supplier::query()->create(['name' => 'B', 'slug' => 'sup-b']);

        Product::query()->create([
            'supplier_id' => $a->id, 'code' => 'A1', 'name' => 'From A', 'price_vat' => '10',
        ]);
        Product::query()->create([
            'supplier_id' => $b->id, 'code' => 'B1', 'name' => 'From B', 'price_vat' => '10',
        ]);

        $config = $this->shoptetConfig(['supplier_filter' => [$a->id]]);
        $xml = $this->exporter()->export($config)['xml'];

        $this->assertStringContainsString('From A', $xml);
        $this->assertStringNotContainsString('From B', $xml);
    }

    public function test_extra_flags_emitted_per_product(): void
    {
        Product::query()->create([
            'code' => 'X', 'name' => 'X', 'price_vat' => '10',
        ]);

        $config = $this->shoptetConfig([
            'extra_flags' => ['FLAG_1' => 'akce', 'FLAG_2' => 'novinka'],
        ]);
        $xml = $this->exporter()->export($config)['xml'];

        $this->assertStringContainsString('<FLAG_1>akce</FLAG_1>', $xml);
        $this->assertStringContainsString('<FLAG_2>novinka</FLAG_2>', $xml);
    }

    public function test_uses_effective_overrides(): void
    {
        Product::query()->create([
            'code' => 'X',
            'name' => 'Original name',
            'description' => 'Original desc',
            'price_vat' => '99.0000',
            'override_name' => 'Branded',
            'override_description' => 'Branded desc',
            'override_price_vat' => '79.0000',
        ]);

        $config = $this->shoptetConfig();
        $xml = $this->exporter()->export($config)['xml'];

        $this->assertStringContainsString('<NAME>Branded</NAME>', $xml);
        $this->assertStringContainsString('<![CDATA[Branded desc]]>', $xml);
        $this->assertStringContainsString('<PRICE_VAT>79.0000</PRICE_VAT>', $xml);
        $this->assertStringNotContainsString('Original name', $xml);
    }

    public function test_glami_and_zbozi_share_shop_shopitem_schema(): void
    {
        \Adminos\Modules\Feedmanager\Models\Product::query()->create([
            'code' => 'X', 'name' => 'X', 'price_vat' => '10',
        ]);

        foreach ([ExportConfig::FORMAT_GLAMI, ExportConfig::FORMAT_ZBOZI] as $format) {
            $config = $this->shoptetConfig(['format' => $format, 'slug' => "slug-$format"]);
            $result = $this->exporter()->export($config);
            $this->assertSame(1, $result['count']);
            $this->assertStringContainsString("format=\"$format\"", $result['xml']);
            $this->assertStringContainsString('<SHOPITEM>', $result['xml']);
        }
    }

    public function test_unknown_format_throws(): void
    {
        $config = $this->shoptetConfig();
        // bypass the model's FORMATS constant
        $config->forceFill(['format' => 'bogus'])->saveQuietly();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown export format');
        $this->exporter()->export($config);
    }

    private function shoptetConfig(array $overrides = []): ExportConfig
    {
        return ExportConfig::query()->create(array_merge([
            'name' => 'Shoptet',
            'slug' => 'shoptet',
            'format' => ExportConfig::FORMAT_SHOPTET,
        ], $overrides));
    }

    private function exporter(): B2cFeedExporter
    {
        return new B2cFeedExporter();
    }
}
