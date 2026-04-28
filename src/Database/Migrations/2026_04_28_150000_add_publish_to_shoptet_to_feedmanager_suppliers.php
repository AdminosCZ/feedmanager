<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedmanager_suppliers', function (Blueprint $table): void {
            // Default true: external suppliers (is_own=false) typically should
            // be republished into the client's Shoptet eshop. Own eshop
            // suppliers are excluded by exporter logic, not by this flag.
            $table->boolean('publish_to_shoptet')->default(true)->after('is_own');
            $table->index('publish_to_shoptet');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_suppliers', function (Blueprint $table): void {
            $table->dropIndex(['publish_to_shoptet']);
            $table->dropColumn('publish_to_shoptet');
        });
    }
};
