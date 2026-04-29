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
            // Two-state B2B exclusion:
            //   is_b2b_allowed = false  → permanently out (set on the
            //                              "Vlastní katalog" tab; the product
            //                              disappears from the Partners tab).
            //   is_b2b_paused  = true   → temporarily out of the feed but
            //                              still visible on the Partners tab
            //                              so admin can flip it back easily.
            $table->boolean('is_b2b_paused')->default(false)->after('is_b2b_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_products', function (Blueprint $table) {
            $table->dropColumn('is_b2b_paused');
        });
    }
};
