<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_products', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->string('ean')->nullable()->index();
            $table->string('product_number')->nullable()->index();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();

            $table->decimal('price', 12, 4)->default(0);
            $table->decimal('price_vat', 12, 4)->default(0);
            $table->decimal('old_price_vat', 12, 4)->nullable();
            $table->string('currency', 3)->default('CZK');

            $table->integer('stock_quantity')->default(0);
            $table->string('availability', 32)->nullable();
            $table->date('delivery_date')->nullable();

            $table->string('image_url')->nullable();

            $table->string('category_text')->nullable();
            $table->string('complete_path')->nullable();

            $table->boolean('is_b2b_allowed')->default(true);
            $table->boolean('is_excluded')->default(false);

            $table->string('override_name')->nullable();
            $table->text('override_description')->nullable();
            $table->decimal('override_price_vat', 12, 4)->nullable();
            $table->json('locked_fields')->nullable();

            $table->dateTimeTz('imported_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_products');
    }
};
