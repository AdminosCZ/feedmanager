<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_feed_configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')
                ->constrained('feedmanager_suppliers')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('source_url', 2048);
            $table->string('format', 32); // heureka | google | shoptet | custom
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_update')->default(false);

            $table->string('http_username')->nullable();
            $table->string('http_password')->nullable();

            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 16)->nullable(); // success | failed | running
            $table->text('last_message')->nullable();

            $table->timestamps();

            $table->index(['supplier_id', 'is_active']);
            $table->index(['is_active', 'auto_update'], 'fmcfg_cron_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_feed_configs');
    }
};
