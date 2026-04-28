<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\FeedRule;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;
use Adminos\Modules\Feedmanager\Services\RuleEngine\RuleEngine;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class RuleEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_input_unchanged_when_no_rules(): void
    {
        $config = $this->makeConfig();
        $product = $this->makeProduct();

        $result = (new RuleEngine())->apply($product, $config);

        $this->assertEquals($product, $result);
    }

    public function test_set_action_overwrites_field(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'manufacturer',
            'action' => FeedRule::ACTION_SET,
            'action_value' => 'Override Brand',
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['manufacturer' => 'Old']), $config);

        $this->assertSame('Override Brand', $result->manufacturer);
    }

    public function test_multiply_increases_price_by_percentage(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'price_vat',
            'action' => FeedRule::ACTION_MULTIPLY,
            'action_value' => '1.10',
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['price_vat' => 100.0]), $config);

        $this->assertEqualsWithDelta(110.0, $result->price_vat, 0.0001);
    }

    public function test_add_increases_price_by_fixed_amount(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'price_vat',
            'action' => FeedRule::ACTION_ADD,
            'action_value' => '50',
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['price_vat' => 100.0]), $config);

        $this->assertEqualsWithDelta(150.0, $result->price_vat, 0.0001);
    }

    public function test_replace_with_pipe_separated_value(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'name',
            'action' => FeedRule::ACTION_REPLACE,
            'action_value' => 'TM|',
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['name' => 'Foo TM Bar TM Baz']), $config);

        $this->assertSame('Foo  Bar  Baz', $result->name);
    }

    public function test_replace_supports_regex_when_needle_wrapped_in_slashes(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'name',
            'action' => FeedRule::ACTION_REPLACE,
            'action_value' => '/\s+/| ',
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['name' => 'Foo    Bar']), $config);

        $this->assertSame('Foo Bar', $result->name);
    }

    public function test_round_truncates_to_decimals(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'price_vat',
            'action' => FeedRule::ACTION_ROUND,
            'action_value' => '0',
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['price_vat' => 99.6]), $config);

        $this->assertEqualsWithDelta(100.0, $result->price_vat, 0.0001);
    }

    public function test_remove_action_filters_out_product(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'manufacturer',
            'condition_op' => FeedRule::COND_EQ,
            'condition_value' => 'BannedBrand',
            'action' => FeedRule::ACTION_REMOVE,
        ]);

        $kept = (new RuleEngine())->apply($this->makeProduct(['manufacturer' => 'Acme']), $config);
        $removed = (new RuleEngine())->apply($this->makeProduct(['manufacturer' => 'BannedBrand']), $config);

        $this->assertNotNull($kept);
        $this->assertNull($removed);
    }

    public function test_condition_eq_only_applies_when_match(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'manufacturer',
            'condition_op' => FeedRule::COND_EQ,
            'condition_value' => 'Acme',
            'action' => FeedRule::ACTION_SET,
            'action_value' => 'Acme Corp',
        ]);

        $hit = (new RuleEngine())->apply($this->makeProduct(['manufacturer' => 'Acme']), $config);
        $miss = (new RuleEngine())->apply($this->makeProduct(['manufacturer' => 'Other']), $config);

        $this->assertSame('Acme Corp', $hit->manufacturer);
        $this->assertSame('Other', $miss->manufacturer);
    }

    public function test_condition_contains(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'name',
            'condition_op' => FeedRule::COND_CONTAINS,
            'condition_value' => 'sale',
            'action' => FeedRule::ACTION_PREPEND,
            'action_value' => '[SALE] ',
        ]);

        $hit = (new RuleEngine())->apply($this->makeProduct(['name' => 'Big sale today']), $config);
        $miss = (new RuleEngine())->apply($this->makeProduct(['name' => 'Regular product']), $config);

        $this->assertSame('[SALE] Big sale today', $hit->name);
        $this->assertSame('Regular product', $miss->name);
    }

    public function test_condition_gt_for_numeric(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'price_vat',
            'condition_op' => FeedRule::COND_GT,
            'condition_value' => '1000',
            'action' => FeedRule::ACTION_MULTIPLY,
            'action_value' => '0.9',
        ]);

        $expensive = (new RuleEngine())->apply($this->makeProduct(['price_vat' => 1500.0]), $config);
        $cheap = (new RuleEngine())->apply($this->makeProduct(['price_vat' => 500.0]), $config);

        $this->assertEqualsWithDelta(1350.0, $expensive->price_vat, 0.0001);
        $this->assertEqualsWithDelta(500.0, $cheap->price_vat, 0.0001);
    }

    public function test_priority_orders_rule_application(): void
    {
        $config = $this->makeConfig();

        // Rule A runs first (priority 10): set price = 100
        $this->makeRule($config, [
            'field' => 'price_vat',
            'action' => FeedRule::ACTION_SET,
            'action_value' => '100',
            'priority' => 10,
        ]);

        // Rule B runs after (priority 20): multiply by 2 → 200
        $this->makeRule($config, [
            'field' => 'price_vat',
            'action' => FeedRule::ACTION_MULTIPLY,
            'action_value' => '2',
            'priority' => 20,
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['price_vat' => 50.0]), $config);

        $this->assertEqualsWithDelta(200.0, $result->price_vat, 0.0001);
    }

    public function test_supplier_scoped_rule_applies_to_all_feeds_from_that_supplier(): void
    {
        $supplier = Supplier::query()->create(['name' => 'A', 'slug' => 'sup-a']);
        $config = FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Test',
            'source_url' => 'https://example.com',
            'format' => FeedConfig::FORMAT_HEUREKA,
        ]);

        FeedRule::query()->create([
            'feed_config_id' => null,
            'supplier_id' => $supplier->id,
            'name' => 'Supplier-wide markup',
            'field' => 'price_vat',
            'action' => FeedRule::ACTION_MULTIPLY,
            'action_value' => '1.5',
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['price_vat' => 100.0]), $config);

        $this->assertEqualsWithDelta(150.0, $result->price_vat, 0.0001);
    }

    public function test_inactive_rules_are_skipped(): void
    {
        $config = $this->makeConfig();
        $this->makeRule($config, [
            'field' => 'name',
            'action' => FeedRule::ACTION_SET,
            'action_value' => 'Should not change',
            'is_active' => false,
        ]);

        $result = (new RuleEngine())->apply($this->makeProduct(['name' => 'Original']), $config);

        $this->assertSame('Original', $result->name);
    }

    private function makeConfig(): FeedConfig
    {
        $supplier = Supplier::query()->firstOrCreate(['slug' => 'sup'], ['name' => 'Sup']);

        return FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Test',
            'source_url' => 'https://example.com',
            'format' => FeedConfig::FORMAT_HEUREKA,
        ]);
    }

    private function makeRule(FeedConfig $config, array $overrides): FeedRule
    {
        return FeedRule::query()->create(array_merge([
            'feed_config_id' => $config->id,
            'name' => 'Test rule',
            'field' => 'name',
            'condition_op' => FeedRule::COND_ALWAYS,
            'action' => FeedRule::ACTION_SET,
            'action_value' => 'X',
        ], $overrides));
    }

    private function makeProduct(array $overrides = []): ParsedProduct
    {
        return new ParsedProduct(
            code: $overrides['code'] ?? 'CODE',
            name: $overrides['name'] ?? 'Name',
            ean: $overrides['ean'] ?? null,
            product_number: $overrides['product_number'] ?? null,
            description: $overrides['description'] ?? null,
            manufacturer: $overrides['manufacturer'] ?? null,
            price: $overrides['price'] ?? null,
            price_vat: $overrides['price_vat'] ?? null,
            old_price_vat: $overrides['old_price_vat'] ?? null,
            currency: $overrides['currency'] ?? 'CZK',
            stock_quantity: $overrides['stock_quantity'] ?? null,
            availability: $overrides['availability'] ?? null,
            image_url: $overrides['image_url'] ?? null,
            category_text: $overrides['category_text'] ?? null,
            complete_path: $overrides['complete_path'] ?? null,
        );
    }
}
