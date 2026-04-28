<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_category_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_category_id')
                ->unique('fmcatmap_supplier_unique')
                ->constrained('feedmanager_supplier_categories')
                ->cascadeOnDelete();

            $table->foreignId('shoptet_category_id')
                ->constrained('feedmanager_shoptet_categories')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_category_mappings');
    }
};
