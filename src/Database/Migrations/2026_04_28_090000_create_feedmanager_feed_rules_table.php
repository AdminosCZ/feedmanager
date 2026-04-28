<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedmanager_feed_rules', function (Blueprint $table) {
            $table->id();

            // Either feed_config_id (rule applies to one feed) OR supplier_id
            // (rule applies to all feeds from that supplier). At least one must be set.
            $table->foreignId('feed_config_id')
                ->nullable()
                ->constrained('feedmanager_feed_configs')
                ->cascadeOnDelete();
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('feedmanager_suppliers')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('field', 32);                // which ParsedProduct field the rule looks at / writes to
            $table->string('condition_op', 16)->nullable(); // eq | neq | contains | starts_with | ends_with | gt | lt | matches | always
            $table->string('condition_value', 1024)->nullable();
            $table->string('action', 32);               // set | add | subtract | multiply | divide | replace | prepend | append | round | remove
            $table->string('action_value', 1024)->nullable();

            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['feed_config_id', 'is_active', 'priority'], 'fmrules_feed_lookup_idx');
            $table->index(['supplier_id', 'is_active', 'priority'], 'fmrules_supplier_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedmanager_feed_rules');
    }
};
