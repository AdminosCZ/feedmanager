<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Tests\Unit;

use Adminos\Modules\Feedmanager\Models\ExportConfig;
use Adminos\Modules\Feedmanager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ExportConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_is_auto_generated_on_create(): void
    {
        $config = ExportConfig::query()->create([
            'name' => 'Heuréka',
            'slug' => 'heureka',
            'format' => ExportConfig::FORMAT_HEUREKA,
        ]);

        $this->assertNotEmpty($config->access_hash);
        $this->assertGreaterThanOrEqual(40, strlen($config->access_hash));
    }

    public function test_explicit_hash_is_kept_on_create(): void
    {
        $explicit = str_repeat('a', 64);

        $config = ExportConfig::query()->create([
            'name' => 'Shoptet',
            'slug' => 'shoptet',
            'format' => ExportConfig::FORMAT_SHOPTET,
            'access_hash' => $explicit,
        ]);

        $this->assertSame($explicit, $config->access_hash);
    }

    public function test_regenerate_hash_changes_the_hash(): void
    {
        $config = ExportConfig::query()->create([
            'name' => 'Shoptet',
            'slug' => 'shoptet',
            'format' => ExportConfig::FORMAT_SHOPTET,
        ]);

        $original = $config->access_hash;
        $config->regenerateHash();

        $this->assertNotSame($original, $config->access_hash);
    }

    public function test_is_active_reads_flag(): void
    {
        $config = ExportConfig::query()->create([
            'name' => 'Heuréka',
            'slug' => 'heureka',
            'format' => ExportConfig::FORMAT_HEUREKA,
            'is_active' => false,
        ]);

        $this->assertFalse($config->isActive());
        $config->update(['is_active' => true]);
        $this->assertTrue($config->isActive());
    }

    public function test_json_fields_cast_to_arrays(): void
    {
        $config = ExportConfig::query()->create([
            'name' => 'Test',
            'slug' => 'test',
            'format' => ExportConfig::FORMAT_SHOPTET,
            'field_whitelist' => ['NAME', 'PRICE_VAT'],
            'supplier_filter' => [1, 2, 3],
            'extra_flags' => ['FLAG_1' => 'akce'],
        ]);

        $fresh = ExportConfig::query()->find($config->id);

        $this->assertIsArray($fresh->field_whitelist);
        $this->assertSame(['NAME', 'PRICE_VAT'], $fresh->field_whitelist);
        $this->assertSame([1, 2, 3], $fresh->supplier_filter);
        $this->assertSame(['FLAG_1' => 'akce'], $fresh->extra_flags);
    }
}
