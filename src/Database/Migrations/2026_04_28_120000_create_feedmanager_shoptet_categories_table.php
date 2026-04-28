<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_shoptet_categories', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('shoptet_id')->unique();
            $table->unsignedInteger('parent_shoptet_id')->nullable()->index();

            $table->string('guid', 64)->nullable();
            $table->string('title', 500);
            $table->string('link_text', 500)->nullable();

            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('visible')->default(true);

            $table->string('full_path', 1000)->nullable();
            $table->unsignedTinyInteger('depth')->default(0);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_shoptet_categories');
    }
};
