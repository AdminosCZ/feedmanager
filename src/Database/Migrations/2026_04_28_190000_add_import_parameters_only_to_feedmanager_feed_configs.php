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
            // When true, FeedImporter skips the upsert/gallery sync entirely
            // and only refreshes ProductParameter rows for products that
            // already exist. Use case: Shoptet custom XML templates can't
            // export parameters via placeholders, so a secondary Heureka feed
            // pulls them in without trampling on the primary feed's data.
            // Column position not pinned — depends on whether PR #13 (update_only_mode)
            // is merged first. Stays at the end of the table either way.
            $table->boolean('import_parameters_only')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table): void {
            $table->dropColumn('import_parameters_only');
        });
    }
};
