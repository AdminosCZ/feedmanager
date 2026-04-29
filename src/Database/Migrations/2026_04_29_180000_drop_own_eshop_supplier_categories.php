<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Own-eshop suppliers shouldn't have rows in `feedmanager_supplier_categories` —
     * those are for the supplier→shop translation only. The original
     * FeedImporter ran `syncFromProducts` for every supplier, so existing
     * setups (Markstore: 232 rows for Markstore eshop) need cleanup.
     *
     * Steps:
     *   1. Null out `shoptet_category_id` on own-eshop products that came
     *      from a now-deleted supplier_category — the next own-eshop import
     *      links them by path directly.
     *   2. Null out `supplier_category_id` on those same products.
     *   3. Delete `category_mappings` rows pointing at to-be-deleted
     *      `supplier_categories`.
     *   4. Delete the `supplier_categories` rows themselves.
     */
    public function up(): void
    {
        $ownIds = DB::table('feedmanager_suppliers')->where('is_own', true)->pluck('id')->all();
        if ($ownIds === []) {
            return;
        }

        $supplierCatIds = DB::table('feedmanager_supplier_categories')
            ->whereIn('supplier_id', $ownIds)
            ->pluck('id')
            ->all();

        if ($supplierCatIds !== []) {
            DB::table('feedmanager_products')
                ->whereIn('supplier_category_id', $supplierCatIds)
                ->update([
                    'shoptet_category_id' => null,
                    'supplier_category_id' => null,
                ]);

            DB::table('feedmanager_category_mappings')
                ->whereIn('supplier_category_id', $supplierCatIds)
                ->delete();
        }

        DB::table('feedmanager_supplier_categories')
            ->whereIn('supplier_id', $ownIds)
            ->delete();
    }

    public function down(): void
    {
        // Irreversible by design — supplier_categories for own-eshop suppliers
        // were always nonsensical, the next import will derive whatever is
        // genuinely needed.
    }
};
