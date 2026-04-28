<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_product_parameters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('feedmanager_products')
                ->cascadeOnDelete();

            $table->string('name', 128);
            $table->string('value', 1024);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'position']);
            $table->unique(['product_id', 'name'], 'fmprodparam_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_product_parameters');
    }
};
