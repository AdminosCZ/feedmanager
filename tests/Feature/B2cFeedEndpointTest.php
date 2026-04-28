<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\ExportConfig;
use Adminos\Modules\Feedmanager\Models\ExportLog;
use Adminos\Modules\Feedmanager\Models\Product;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class B2cFeedEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_when_slug_unknown(): void
    {
        $this->getJson('/export/feed/unknown/anyhash')->assertStatus(404);
    }

    public function test_returns_403_when_hash_wrong(): void
    {
        $config = $this->makeConfig();

        $this->getJson("/export/feed/{$config->slug}/wronghash")->assertStatus(403);
    }

    public function test_returns_403_when_config_disabled(): void
    {
        $config = $this->makeConfig(['is_active' => false]);

        $this->getJson("/export/feed/{$config->slug}/{$config->access_hash}")
            ->assertStatus(403);
    }

    public function test_returns_xml_for_valid_request(): void
    {
        $config = $this->makeConfig();
        Product::query()->create([
            'code' => 'SKU-A',
            'name' => 'Demo',
            'price_vat' => '99.9999',
        ]);

        $response = $this->get("/export/feed/{$config->slug}/{$config->access_hash}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertHeader('X-Feedmanager-Format', 'shoptet');
        $response->assertHeader('X-Feedmanager-Count', '1');
        $this->assertStringContainsString('<CODE>SKU-A</CODE>', $response->getContent());
    }

    public function test_logs_successful_download(): void
    {
        $config = $this->makeConfig();

        $this->get("/export/feed/{$config->slug}/{$config->access_hash}")
            ->assertStatus(200);

        $log = ExportLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($config->id, $log->export_config_id);
        $this->assertSame(200, $log->status_code);
        $this->assertSame(0, $log->product_count);
    }

    public function test_no_log_written_for_failed_auth(): void
    {
        $config = $this->makeConfig();

        $this->get("/export/feed/{$config->slug}/wrong")->assertStatus(403);

        $this->assertSame(0, ExportLog::query()->count());
    }

    private function makeConfig(array $overrides = []): ExportConfig
    {
        return ExportConfig::query()->create(array_merge([
            'name' => 'Shoptet',
            'slug' => 'shoptet',
            'format' => ExportConfig::FORMAT_SHOPTET,
        ], $overrides));
    }
}
