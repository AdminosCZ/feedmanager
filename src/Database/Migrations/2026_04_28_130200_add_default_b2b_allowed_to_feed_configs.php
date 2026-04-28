<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table) {
            // When true (default), products imported from this feed get
            // is_b2b_allowed = true on creation. Set false for "I'm reselling
            // this supplier's catalogue and have no wholesale rights" feeds —
            // their products import as B2B-blocked unless admin enables them
            // case-by-case.
            $table->boolean('default_b2b_allowed')->default(true)->after('auto_update');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table) {
            $table->dropColumn('default_b2b_allowed');
        });
    }
};
