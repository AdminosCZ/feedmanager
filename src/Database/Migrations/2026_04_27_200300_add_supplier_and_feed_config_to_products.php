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
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('id')
                ->constrained('feedmanager_suppliers')
                ->nullOnDelete();

            $table->foreignId('feed_config_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('feedmanager_feed_configs')
                ->nullOnDelete();

            $table->index(['supplier_id', 'feed_config_id'], 'fmproducts_provenance_idx');
        });

        // The unique on `code` made sense in PR 1 (locally-managed catalog).
        // Once products can come from multiple suppliers, a code may legitimately
        // repeat across suppliers — uniqueness now scoped per (supplier_id, code).
        Schema::table('feedmanager_products', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['supplier_id', 'code'], 'fmproducts_supplier_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_products', function (Blueprint $table) {
            $table->dropUnique('fmproducts_supplier_code_unique');
            $table->unique('code');

            $table->dropIndex('fmproducts_provenance_idx');
            $table->dropConstrainedForeignId('feed_config_id');
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
