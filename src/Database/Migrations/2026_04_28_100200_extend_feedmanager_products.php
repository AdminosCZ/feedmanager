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
            // External system ID (e.g. Shoptet GUID); independent of `code`.
            $table->string('shoptet_id', 64)->nullable()->after('product_number');
            // Short product summary, distinct from `description`.
            $table->text('short_description')->nullable()->after('description');
            // Approval workflow — Klenoty pattern.
            $table->string('status', 16)->default('pending')->after('is_excluded');

            $table->index('shoptet_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['shoptet_id']);

            $table->dropColumn(['shoptet_id', 'short_description', 'status']);
        });
    }
};
