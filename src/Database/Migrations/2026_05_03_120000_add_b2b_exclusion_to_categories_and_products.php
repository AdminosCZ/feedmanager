<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B2B inclusion rules — declarative model:
     *
     *   shoptet_categories.exclude_from_b2b — admin označí kategorii, exporter
     *   filtruje všechny produkty napárované na ni i její descendants.
     *
     *   products.b2b_inclusion_override — per-product manuální přebití
     *   kategoriálního pravidla:
     *     null              = řiď se kategorií (default)
     *     'force_allowed'   = vždy v B2B feedu, ignorovat kategorie
     *     'force_excluded'  = vždy mimo B2B feed, ignorovat kategorie
     *
     * Precedence (B2bInclusionResolver):
     *   1. is_excluded                        → OUT (global exclude)
     *   2. b2b_inclusion_override=force_excluded → OUT (admin override)
     *   3. is_b2b_allowed=false               → OUT (master off)
     *   4. is_b2b_paused=true                 → OUT (temporary pause)
     *   5. b2b_inclusion_override=force_allowed → IN  (admin override)
     *   6. category_id ∈ excluded_tree        → OUT (category rule)
     *   7. otherwise                          → IN
     */
    public function up(): void
    {
        Schema::table('feedmanager_shoptet_categories', function (Blueprint $table) {
            $table->boolean('exclude_from_b2b')->default(false)->after('is_orphaned');
            // Index — exporter dotaz `whereNotIn(shoptet_category_id, $excluded)`
            // často potřebuje rychlé scan kategorií s tímto flagem.
            $table->index('exclude_from_b2b');
        });

        Schema::table('feedmanager_products', function (Blueprint $table) {
            $table->string('b2b_inclusion_override', 16)->nullable()->after('is_b2b_paused');
            $table->index('b2b_inclusion_override');
        });
    }

    public function down(): void
    {
        Schema::table('feedmanager_shoptet_categories', function (Blueprint $table) {
            $table->dropIndex(['exclude_from_b2b']);
            $table->dropColumn('exclude_from_b2b');
        });

        Schema::table('feedmanager_products', function (Blueprint $table) {
            $table->dropIndex(['b2b_inclusion_override']);
            $table->dropColumn('b2b_inclusion_override');
        });
    }
};
