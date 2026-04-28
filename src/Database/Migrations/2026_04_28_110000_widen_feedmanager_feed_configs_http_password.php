<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table) {
            // Laravel's `encrypted` cast wraps plaintext in a base64-encoded
            // payload that's typically 100+ chars even for short passwords.
            // VARCHAR(255) was tight; bump to TEXT for safety.
            $table->text('http_password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_feed_configs', function (Blueprint $table) {
            $table->string('http_password', 255)->nullable()->change();
        });
    }
};
