<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_import_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feed_config_id')
                ->constrained('feedmanager_feed_configs')
                ->cascadeOnDelete();

            $table->string('status', 16); // success | failed
            $table->string('triggered_by', 16); // manual | cron | api

            $table->unsignedInteger('products_found')->default(0);
            $table->unsignedInteger('products_new')->default(0);
            $table->unsignedInteger('products_updated')->default(0);
            $table->unsignedInteger('products_failed')->default(0);

            $table->text('message')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['feed_config_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_import_logs');
    }
};
