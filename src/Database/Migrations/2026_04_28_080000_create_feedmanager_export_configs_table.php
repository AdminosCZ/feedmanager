<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_export_configs', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('access_hash', 80)->unique();

            $table->string('format', 32); // shoptet | heureka | glami | zbozi
            $table->boolean('is_active')->default(true);

            $table->string('price_mode', 16)->default('with_vat');     // with_vat | without_vat
            $table->string('category_mode', 16)->default('full_path'); // full_path | last_leaf
            $table->string('excluded_mode', 16)->default('skip');      // skip | hidden

            $table->json('field_whitelist')->nullable();   // null = all standard fields
            $table->json('supplier_filter')->nullable();   // null = all suppliers
            $table->json('extra_flags')->nullable();       // {"flag_1": "akce", "flag_2": "novinka"}

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_export_configs');
    }
};
