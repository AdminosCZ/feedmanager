<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Models\ProductImage;
use Adminos\Modules\Feedmanager\Models\ProductParameter;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Services\CategoryMappingService;
use Adminos\Modules\Feedmanager\Services\FeedDownloader;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Adminos\Modules\Feedmanager\Services\Parsing\FeedParserFactory;
use Adminos\Modules\Feedmanager\Services\Parsing\ShoptetCategoriesParser;
use Adminos\Modules\Feedmanager\Services\RuleEngine\RuleEngine;
use Adminos\Modules\Feedmanager\Services\ShoptetCategorySyncService;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

final class FeedImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_products_from_feed(): void
    {
        $config = $this->makeConfig();
        $importer = $this->makeImporter($this->fakeFeedXml());

        $log = $importer->run($config);

        $this->assertSame(ImportLog::STATUS_SUCCESS, $log->status);
        $this->assertSame(2, $log->products_found);
        $this->assertSame(2, $log->products_new);
        $this->assertSame(0, $log->products_updated);
        $this->assertSame(0, $log->products_failed);

        $this->assertSame(2, Product::query()->where('supplier_id', $config->supplier_id)->count());
    }

    public function test_updates_existing_products_matched_by_supplier_and_code(): void
    {
        $config = $this->makeConfig();

        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => 'SKU-1',
            'name' => 'Old name',
            'price_vat' => '50.0000',
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $log = $importer->run($config);

        $this->assertSame(2, $log->products_found);
        $this->assertSame(1, $log->products_new);
        $this->assertSame(1, $log->products_updated);

        $updated = Product::query()->where('supplier_id', $config->supplier_id)
            ->where('code', 'SKU-1')->first();
        $this->assertNotNull($updated);
        $this->assertSame('Demo product 1', $updated->name);
        $this->assertSame('99.9999', $updated->price_vat);
    }

    public function test_locked_fields_are_not_overwritten(): void
    {
        $config = $this->makeConfig();

        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => 'SKU-1',
            'name' => 'Hand-edited name',
            'price_vat' => '199.0000',
            'locked_fields' => ['name', 'price_vat'],
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame('Hand-edited name', $product->name);
        $this->assertSame('199.0000', $product->price_vat);
    }

    public function test_failed_download_is_logged_as_failure(): void
    {
        $config = $this->makeConfig();
        $importer = $this->makeImporter('', throwOnDownload: true);

        $log = $importer->run($config);

        $this->assertSame(ImportLog::STATUS_FAILED, $log->status);
        $this->assertNotNull($log->message);
        $this->assertSame(0, $log->products_found);
        $this->assertSame(FeedConfig::STATUS_FAILED, $config->fresh()->last_status);
    }

    public function test_unknown_format_is_logged_as_failure(): void
    {
        $config = $this->makeConfig(['format' => 'broken-format']);
        $importer = $this->makeImporter('<SHOP/>');

        $log = $importer->run($config);

        $this->assertSame(ImportLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('Unknown feed format', (string) $log->message);
    }

    public function test_records_supplier_id_and_feed_config_id_on_created_products(): void
    {
        $config = $this->makeConfig();
        $importer = $this->makeImporter($this->fakeFeedXml());

        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertNotNull($product);
        $this->assertSame($config->supplier_id, $product->supplier_id);
        $this->assertSame($config->id, $product->feed_config_id);
        $this->assertNotNull($product->imported_at);
    }

    public function test_updates_last_run_at_and_last_status_after_success(): void
    {
        $config = $this->makeConfig();
        $importer = $this->makeImporter($this->fakeFeedXml());

        $importer->run($config);

        $config->refresh();
        $this->assertNotNull($config->last_run_at);
        $this->assertSame(FeedConfig::STATUS_SUCCESS, $config->last_status);
    }

    public function test_default_b2b_allowed_false_imports_products_blocked_for_b2b(): void
    {
        $config = $this->makeConfig(['default_b2b_allowed' => false]);
        $importer = $this->makeImporter($this->fakeFeedXml());

        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertNotNull($product);
        $this->assertFalse($product->is_b2b_allowed);
    }

    public function test_default_b2b_allowed_true_imports_products_open_for_b2b(): void
    {
        $config = $this->makeConfig(['default_b2b_allowed' => true]);
        $importer = $this->makeImporter($this->fakeFeedXml());

        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertTrue($product->is_b2b_allowed);
    }

    public function test_default_b2b_allowed_does_not_overwrite_existing_product(): void
    {
        $config = $this->makeConfig(['default_b2b_allowed' => false]);

        // Pre-existing product, admin already approved it for B2B.
        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => 'SKU-1',
            'name' => 'existing',
            'is_b2b_allowed' => true,
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertTrue($product->is_b2b_allowed, 'existing approval must survive re-import');
    }

    public function test_own_eshop_products_land_as_approved(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Markstore',
            'slug' => 'markstore',
            'is_own' => true,
        ]);
        $config = FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Markstore feed',
            'source_url' => 'https://example.com/feed.xml',
            'format' => FeedConfig::FORMAT_HEUREKA,
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame(Product::STATUS_APPROVED, $product->status);
    }

    public function test_external_supplier_products_land_as_pending(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Velkoobchod',
            'slug' => 'velkoobchod',
            'is_own' => false,
        ]);
        $config = FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Velkoobchod feed',
            'source_url' => 'https://example.com/feed.xml',
            'format' => FeedConfig::FORMAT_HEUREKA,
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame(Product::STATUS_PENDING, $product->status);
    }

    public function test_status_default_does_not_overwrite_existing_status(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Velkoobchod',
            'slug' => 'velkoobchod',
            'is_own' => false,
        ]);
        $config = FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Velkoobchod feed',
            'source_url' => 'https://example.com/feed.xml',
            'format' => FeedConfig::FORMAT_HEUREKA,
        ]);

        // Admin already approved this product manually.
        Product::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'SKU-1',
            'name' => 'Pre-approved',
            'status' => Product::STATUS_APPROVED,
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame(Product::STATUS_APPROVED, $product->status, 'manual approval must survive re-import');
    }

    public function test_imports_gallery_images_alongside_primary(): void
    {
        $config = $this->makeConfig();

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <IMGURL>https://example.com/main.jpg</IMGURL>
    <IMGURL>https://example.com/alt-1.jpg</IMGURL>
    <IMGURL_ALTERNATIVE>https://example.com/alt-2.jpg</IMGURL_ALTERNATIVE>
  </SHOPITEM>
</SHOP>
XML;

        $importer = $this->makeImporter($payload);
        $importer->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame('https://example.com/main.jpg', $product->image_url);

        $gallery = ProductImage::query()->where('product_id', $product->id)->orderBy('position')->get();
        $this->assertCount(2, $gallery);
        $this->assertSame('https://example.com/alt-1.jpg', $gallery[0]->url);
        $this->assertSame('https://example.com/alt-2.jpg', $gallery[1]->url);
        $this->assertSame(1, $gallery[0]->position);
        $this->assertSame(2, $gallery[1]->position);
    }

    public function test_gallery_is_replaced_on_re_import(): void
    {
        $config = $this->makeConfig();

        $first = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <IMGURL>https://example.com/main.jpg</IMGURL>
    <IMGURL>https://example.com/old-1.jpg</IMGURL>
    <IMGURL>https://example.com/old-2.jpg</IMGURL>
  </SHOPITEM>
</SHOP>
XML;

        $this->makeImporter($first)->run($config);

        $second = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <IMGURL>https://example.com/main.jpg</IMGURL>
    <IMGURL>https://example.com/new.jpg</IMGURL>
  </SHOPITEM>
</SHOP>
XML;

        $this->makeImporter($second)->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $gallery = ProductImage::query()->where('product_id', $product->id)->get();

        $this->assertCount(1, $gallery);
        $this->assertSame('https://example.com/new.jpg', $gallery[0]->url);
    }

    public function test_imports_parameters_alongside_product(): void
    {
        $config = $this->makeConfig();

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <PARAM><PARAM_NAME>Barva</PARAM_NAME><VAL>Modrá</VAL></PARAM>
    <PARAM><PARAM_NAME>Velikost</PARAM_NAME><VAL>XL</VAL></PARAM>
  </SHOPITEM>
</SHOP>
XML;

        $this->makeImporter($payload)->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $params = ProductParameter::query()
            ->where('product_id', $product->id)
            ->orderBy('position')
            ->get();

        $this->assertCount(2, $params);
        $this->assertSame('Barva', $params[0]->name);
        $this->assertSame('Modrá', $params[0]->value);
        $this->assertSame('Velikost', $params[1]->name);
        $this->assertSame('XL', $params[1]->value);
    }

    public function test_parameters_are_replaced_on_re_import(): void
    {
        $config = $this->makeConfig();

        $first = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <PARAM><PARAM_NAME>Barva</PARAM_NAME><VAL>Modrá</VAL></PARAM>
  </SHOPITEM>
</SHOP>
XML;
        $this->makeImporter($first)->run($config);

        $second = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <PARAM><PARAM_NAME>Velikost</PARAM_NAME><VAL>XXL</VAL></PARAM>
  </SHOPITEM>
</SHOP>
XML;
        $this->makeImporter($second)->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $params = ProductParameter::query()->where('product_id', $product->id)->get();

        $this->assertCount(1, $params);
        $this->assertSame('Velikost', $params[0]->name);
    }

    public function test_parameters_are_left_alone_when_feed_has_none(): void
    {
        $config = $this->makeConfig();
        $this->makeImporter($this->fakeFeedXml())->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        ProductParameter::query()->create([
            'product_id' => $product->id,
            'name' => 'Manual',
            'value' => 'Yes',
            'position' => 1,
        ]);

        $this->makeImporter($this->fakeFeedXml())->run($config);

        $params = ProductParameter::query()->where('product_id', $product->id)->get();
        $this->assertCount(1, $params);
        $this->assertSame('Manual', $params[0]->name);
    }

    public function test_import_all_images_off_skips_gallery_sync(): void
    {
        $config = $this->makeConfig(['import_all_images' => false]);

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <IMGURL>https://example.com/main.jpg</IMGURL>
    <IMGURL>https://example.com/alt-1.jpg</IMGURL>
    <IMGURL>https://example.com/alt-2.jpg</IMGURL>
  </SHOPITEM>
</SHOP>
XML;

        $this->makeImporter($payload)->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        // Primary image still lands on Product (it's not "the gallery").
        $this->assertSame('https://example.com/main.jpg', $product->image_url);

        // Gallery rows are NOT created when import_all_images is off.
        $this->assertSame(0, ProductImage::query()->where('product_id', $product->id)->count());
    }

    public function test_import_short_description_off_drops_short_field(): void
    {
        $config = $this->makeConfig(['import_short_description' => false]);

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <SHORT_DESCRIPTION>Krátký popis z feedu</SHORT_DESCRIPTION>
    <DESCRIPTION>Dlouhý popis z feedu.</DESCRIPTION>
  </SHOPITEM>
</SHOP>
XML;

        $this->makeImporter($payload)->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertNull($product->short_description);
        $this->assertSame('Dlouhý popis z feedu.', $product->description);
    }

    public function test_import_long_description_off_drops_long_field(): void
    {
        $config = $this->makeConfig(['import_long_description' => false]);

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <SHORT_DESCRIPTION>Krátký</SHORT_DESCRIPTION>
    <DESCRIPTION>Dlouhý popis.</DESCRIPTION>
  </SHOPITEM>
</SHOP>
XML;

        $this->makeImporter($payload)->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame('Krátký', $product->short_description);
        $this->assertNull($product->description);
    }

    public function test_parameters_only_mode_only_syncs_parameters_for_existing_products(): void
    {
        $config = $this->makeConfig(['import_parameters_only' => true]);

        // Pre-existing product with manual data we don't want to lose.
        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => 'SKU-1',
            'name' => 'Original name',
            'price_vat' => '999.0000',
            'stock_quantity' => 10,
            'short_description' => 'Original short',
            'description' => 'Original long',
            'image_url' => 'https://example.com/original.jpg',
        ]);

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Different name from supplement feed</PRODUCTNAME>
    <PRICE_VAT>500.0000</PRICE_VAT>
    <SHORT_DESCRIPTION>Different short</SHORT_DESCRIPTION>
    <DESCRIPTION>Different long</DESCRIPTION>
    <IMGURL>https://example.com/different.jpg</IMGURL>
    <PARAM><PARAM_NAME>Barva</PARAM_NAME><VAL>Modrá</VAL></PARAM>
    <PARAM><PARAM_NAME>Velikost</PARAM_NAME><VAL>XL</VAL></PARAM>
  </SHOPITEM>
</SHOP>
XML;

        $log = $this->makeImporter($payload)->run($config);
        $this->assertSame(1, $log->products_updated);

        $product = Product::query()->where('code', 'SKU-1')->first();

        // Original data must survive — parameters-only mode skips upsert.
        $this->assertSame('Original name', $product->name);
        $this->assertSame('999.0000', $product->price_vat);
        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame('Original short', $product->short_description);
        $this->assertSame('Original long', $product->description);
        $this->assertSame('https://example.com/original.jpg', $product->image_url);

        // Parameters were synced from the supplement feed.
        $params = ProductParameter::query()
            ->where('product_id', $product->id)
            ->orderBy('position')
            ->get();
        $this->assertCount(2, $params);
        $this->assertSame('Barva', $params[0]->name);
        $this->assertSame('Modrá', $params[0]->value);
        $this->assertSame('Velikost', $params[1]->name);
        $this->assertSame('XL', $params[1]->value);
    }

    public function test_parameters_only_mode_skips_unknown_products(): void
    {
        $config = $this->makeConfig(['import_parameters_only' => true]);
        // Catalogue is empty.

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>UNKNOWN</ITEM_ID>
    <PRODUCTNAME>Ghost</PRODUCTNAME>
    <PARAM><PARAM_NAME>Foo</PARAM_NAME><VAL>Bar</VAL></PARAM>
  </SHOPITEM>
</SHOP>
XML;

        $log = $this->makeImporter($payload)->run($config);

        $this->assertSame(1, $log->products_found);
        $this->assertSame(0, $log->products_new);
        $this->assertSame(0, $log->products_updated);
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, ProductParameter::query()->count());
    }

    public function test_imports_short_description_when_present(): void
    {
        $config = $this->makeConfig();

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo</PRODUCTNAME>
    <SHORT_DESCRIPTION>Stručný popis</SHORT_DESCRIPTION>
    <DESCRIPTION>Detailní popis produktu.</DESCRIPTION>
  </SHOPITEM>
</SHOP>
XML;

        $this->makeImporter($payload)->run($config);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame('Stručný popis', $product->short_description);
        $this->assertSame('Detailní popis produktu.', $product->description);
    }

    public function test_gallery_is_left_alone_when_feed_has_no_extra_images(): void
    {
        $config = $this->makeConfig();

        // Feed without extra <IMGURL> rows; just the primary one.
        $payload = $this->fakeFeedXml();

        $this->makeImporter($payload)->run($config);

        // Manually add a gallery row (e.g. uploaded via admin).
        $product = Product::query()->where('code', 'SKU-1')->first();
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => 'https://example.com/manual.jpg',
            'position' => 1,
        ]);

        // Re-import: feed still has no extras, so manual upload should remain.
        $this->makeImporter($payload)->run($config);

        $gallery = ProductImage::query()->where('product_id', $product->id)->get();
        $this->assertCount(1, $gallery);
        $this->assertSame('https://example.com/manual.jpg', $gallery[0]->url);
    }

    public function test_update_only_mode_skips_unknown_products(): void
    {
        $config = $this->makeConfig(['update_only_mode' => true]);

        // Catalogue has only SKU-1; SKU-2 from the feed should be skipped.
        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => 'SKU-1',
            'name' => 'Old name',
            'price_vat' => '50.0000',
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $log = $importer->run($config);

        $this->assertSame(2, $log->products_found);
        $this->assertSame(0, $log->products_new);
        $this->assertSame(1, $log->products_updated);

        $this->assertNull(Product::query()->where('code', 'SKU-2')->first());

        $config->refresh();
        $this->assertStringContainsString('skipped 1', (string) $config->last_message);
    }

    public function test_update_only_mode_preserves_original_feed_config_id(): void
    {
        // Stock supplement updates should NOT change the product's primary
        // feed source. The catalogue feed_config remains the source of record.
        $catalogueConfig = $this->makeConfig();
        $supplementConfig = FeedConfig::query()->create([
            'supplier_id' => $catalogueConfig->supplier_id,
            'name' => 'Stock supplement',
            'source_url' => 'https://example.com/stock.csv',
            'format' => FeedConfig::FORMAT_HEUREKA,
            'update_only_mode' => true,
        ]);

        Product::query()->create([
            'supplier_id' => $catalogueConfig->supplier_id,
            'feed_config_id' => $catalogueConfig->id,
            'code' => 'SKU-1',
            'name' => 'Existing',
            'price_vat' => '50.0000',
        ]);

        $importer = $this->makeImporter($this->fakeFeedXml());
        $importer->run($supplementConfig);

        $product = Product::query()->where('code', 'SKU-1')->first();
        $this->assertSame($catalogueConfig->id, $product->feed_config_id);
    }

    public function test_finds_existing_product_by_sanitized_code_variant(): void
    {
        // Catalogue was imported via Shoptet seznam feed → code is "121_XS".
        // Stock CSV ships the raw code "121/XS" — importer must still update.
        $config = $this->makeConfig();

        Product::query()->create([
            'supplier_id' => $config->supplier_id,
            'code' => '121_XS',
            'name' => 'Variant XS',
            'stock_quantity' => 0,
        ]);

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>121/XS</ITEM_ID>
    <PRODUCTNAME>Variant XS updated</PRODUCTNAME>
    <STOCK_AMOUNT>15</STOCK_AMOUNT>
  </SHOPITEM>
</SHOP>
XML;

        $importer = $this->makeImporter($payload);
        $log = $importer->run($config);

        $this->assertSame(1, $log->products_updated);
        $this->assertSame(0, $log->products_new);

        $product = Product::query()->where('code', '121_XS')->first();
        $this->assertNotNull($product);
        $this->assertSame(15, $product->stock_quantity);
    }

    private function makeConfig(array $overrides = []): FeedConfig
    {
        $supplier = Supplier::query()->create([
            'name' => 'Test Supplier',
            'slug' => 'test-supplier',
        ]);

        return FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Test Feed',
            'source_url' => 'https://example.com/feed.xml',
            'format' => FeedConfig::FORMAT_HEUREKA,
            ...$overrides,
        ]);
    }

    private function fakeFeedXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <SHOPITEM>
    <ITEM_ID>SKU-1</ITEM_ID>
    <PRODUCTNAME>Demo product 1</PRODUCTNAME>
    <PRICE>82.6446</PRICE>
    <PRICE_VAT>99.9999</PRICE_VAT>
    <CURRENCY>CZK</CURRENCY>
  </SHOPITEM>
  <SHOPITEM>
    <ITEM_ID>SKU-2</ITEM_ID>
    <PRODUCTNAME>Demo product 2</PRODUCTNAME>
    <PRICE>123.0000</PRICE>
    <PRICE_VAT>148.83</PRICE_VAT>
  </SHOPITEM>
</SHOP>
XML;
    }

    private function makeImporter(string $payload, bool $throwOnDownload = false): FeedImporter
    {
        $downloader = new class($payload, $throwOnDownload) extends FeedDownloader {
            public function __construct(
                private readonly string $payload,
                private readonly bool $throws,
            ) {
            }

            public function download(FeedConfig $config): string
            {
                if ($this->throws) {
                    throw new RuntimeException('simulated network failure');
                }
                return $this->payload;
            }
        };

        return new FeedImporter(
            $downloader,
            new FeedParserFactory(),
            new RuleEngine(),
            new CategoryMappingService(),
            new ShoptetCategorySyncService($downloader, new ShoptetCategoriesParser()),
        );
    }
}
