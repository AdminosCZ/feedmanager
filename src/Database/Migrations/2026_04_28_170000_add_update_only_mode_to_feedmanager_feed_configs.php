<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table): void {
            // When true, the importer only updates products that already exist
            // (matched by code). Useful for partial feeds like Shoptet stock
            // CSV that supplement the main catalogue feed without bringing
            // their own product list.
            $table->boolean('update_only_mode')->default(false)->after('default_b2b_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table): void {
            $table->dropColumn('update_only_mode');
        });
    }
};
