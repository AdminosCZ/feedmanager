<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_supplier_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')
                ->constrained('feedmanager_suppliers')
                ->cascadeOnDelete();

            $table->foreignId('feed_config_id')
                ->nullable()
                ->constrained('feedmanager_feed_configs')
                ->nullOnDelete();

            $table->string('original_name', 500);
            $table->string('original_path', 1000);

            $table->unsignedInteger('product_count')->default(0);

            $table->timestamps();

            $table->unique(['supplier_id', 'original_path'], 'fmsupcat_path_unique');
            $table->index(['supplier_id', 'feed_config_id'], 'fmsupcat_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_supplier_categories');
    }
};
