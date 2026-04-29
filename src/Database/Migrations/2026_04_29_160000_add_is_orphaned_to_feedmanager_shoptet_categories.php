<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedmanager_shoptet_categories', function (Blueprint $table) {
            // Set when a category was present on a previous sync but is missing
            // from the latest one. Soft-keep semantics — admin needs the row
            // to remap any supplier categories that pointed at it. The next
            // sync that finds the shoptet_id again clears the flag.
            $table->boolean('is_orphaned')->default(false)->after('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_shoptet_categories', function (Blueprint $table) {
            $table->dropColumn('is_orphaned');
        });
    }
};
