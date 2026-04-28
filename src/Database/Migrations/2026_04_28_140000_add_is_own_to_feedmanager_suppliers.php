<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedmanager_suppliers', function (Blueprint $table) {
            // Distinguishes the client's own eshop (true) from external
            // suppliers (false). Sourcing logic is identical — the field is
            // for UX context only (a client shouldn't have to label their
            // own catalogue as "Supplier").
            $table->boolean('is_own')->default(false)->after('slug');
            $table->index('is_own');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_suppliers', function (Blueprint $table) {
            $table->dropIndex(['is_own']);
            $table->dropColumn('is_own');
        });
    }
};
