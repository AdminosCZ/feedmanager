<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedmanager_products', function (Blueprint $table) {
            // Source: which supplier_category this product belongs to (auto-set during import).
            $table->foreignId('supplier_category_id')
                ->nullable()
                ->after('feed_config_id')
                ->constrained('feedmanager_supplier_categories')
                ->nullOnDelete();

            // Target: which shoptet_category we're emitting this product as
            // (denormalised copy of the category_mappings lookup, refreshed on
            // import + on mapping changes).
            $table->foreignId('shoptet_category_id')
                ->nullable()
                ->after('supplier_category_id')
                ->constrained('feedmanager_shoptet_categories')
                ->nullOnDelete();

            $table->index(['supplier_category_id', 'shoptet_category_id'], 'fmproducts_category_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_products', function (Blueprint $table) {
            $table->dropIndex('fmproducts_category_lookup_idx');
            $table->dropConstrainedForeignId('shoptet_category_id');
            $table->dropConstrainedForeignId('supplier_category_id');
        });
    }
};
