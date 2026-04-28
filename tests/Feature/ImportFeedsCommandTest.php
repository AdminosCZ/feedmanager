<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Feature;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Models\Supplier;
use Adminos\Modules\Feedmanager\Services\FeedDownloader;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ImportFeedsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(FeedDownloader::class, new class extends FeedDownloader {
            public function __construct() {}

            public function download(FeedConfig $config): string
            {
                return <<<'XML'
<?xml version="1.0"?>
<SHOP>
  <SHOPITEM><ITEM_ID>X1</ITEM_ID><PRODUCTNAME>X1</PRODUCTNAME></SHOPITEM>
</SHOP>
XML;
            }
        });
    }

    public function test_runs_single_config_when_id_provided(): void
    {
        $config = $this->makeConfig(['is_active' => false, 'auto_update' => false]);

        $this->artisan('feedmanager:import', ['feed_config' => $config->id])
            ->assertSuccessful();

        $log = ImportLog::query()->where('feed_config_id', $config->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(ImportLog::TRIGGER_MANUAL, $log->triggered_by);
    }

    public function test_runs_only_active_auto_update_configs_when_id_omitted(): void
    {
        $included = $this->makeConfig(['is_active' => true, 'auto_update' => true, 'name' => 'Included']);
        $skippedManual = $this->makeConfig(['is_active' => true, 'auto_update' => false, 'name' => 'No-auto']);
        $skippedInactive = $this->makeConfig(['is_active' => false, 'auto_update' => true, 'name' => 'Inactive']);

        $this->artisan('feedmanager:import')->assertSuccessful();

        $this->assertSame(1, ImportLog::query()->where('feed_config_id', $included->id)->count());
        $this->assertSame(0, ImportLog::query()->where('feed_config_id', $skippedManual->id)->count());
        $this->assertSame(0, ImportLog::query()->where('feed_config_id', $skippedInactive->id)->count());

        $log = ImportLog::query()->where('feed_config_id', $included->id)->first();
        $this->assertSame(ImportLog::TRIGGER_CRON, $log->triggered_by);
    }

    public function test_returns_failure_exit_code_when_an_import_fails(): void
    {
        $this->makeConfig([
            'is_active' => true,
            'auto_update' => true,
            'format' => 'broken-format',
        ]);

        $this->artisan('feedmanager:import')->assertFailed();
    }

    public function test_unknown_id_reports_error(): void
    {
        $this->artisan('feedmanager:import', ['feed_config' => 99999])
            ->assertSuccessful() // command itself returns SUCCESS, no configs to run
            ->expectsOutputToContain('FeedConfig #99999 not found.');
    }

    private function makeConfig(array $overrides): FeedConfig
    {
        $supplier = Supplier::query()->firstOrCreate(
            ['slug' => 'test'],
            ['name' => 'Test'],
        );

        return FeedConfig::query()->create([
            'supplier_id' => $supplier->id,
            'name' => $overrides['name'] ?? 'Test config',
            'source_url' => 'https://example.com/feed.xml',
            'format' => $overrides['format'] ?? FeedConfig::FORMAT_HEUREKA,
            'is_active' => $overrides['is_active'] ?? true,
            'auto_update' => $overrides['auto_update'] ?? false,
        ]);
    }
}
