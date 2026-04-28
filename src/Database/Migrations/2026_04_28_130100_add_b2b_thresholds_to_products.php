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
            // Per-product low-stock threshold floor. When set, the effective
            // threshold for any partner = max(partner.default, product.b2b_*).
            // This is "increase only" — admin sets it on a product when they
            // want to protect that specific item more aggressively than the
            // partner-tier default. Leaving NULL means partner default wins.
            $table->unsignedInteger('b2b_low_stock_threshold')->nullable()->after('is_excluded');
            $table->string('b2b_low_stock_availability', 64)->nullable()->after('b2b_low_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_products', function (Blueprint $table) {
            $table->dropColumn(['b2b_low_stock_threshold', 'b2b_low_stock_availability']);
        });
    }
};
