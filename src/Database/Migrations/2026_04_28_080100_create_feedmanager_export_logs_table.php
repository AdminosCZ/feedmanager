<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_export_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('export_config_id')
                ->nullable()
                ->constrained('feedmanager_export_configs')
                ->nullOnDelete();

            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('product_count')->nullable();

            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['export_config_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_export_logs');
    }
};
