<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table): void {
            // Per-feed knobs to control how much data the importer pulls in.
            // Useful when a source feed sends bloated descriptions or lots
            // of images that the client doesn't want to mirror.
            $table->boolean('import_all_images')->default(true)->after('default_b2b_allowed');
            $table->boolean('import_short_description')->default(true)->after('import_all_images');
            $table->boolean('import_long_description')->default(true)->after('import_short_description');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table): void {
            $table->dropColumn(['import_all_images', 'import_short_description', 'import_long_description']);
        });
    }
};
