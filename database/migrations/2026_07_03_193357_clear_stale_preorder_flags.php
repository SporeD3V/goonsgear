<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The WC backfill flagged 271 variants (and their 66 products) as pre-order
 * from the legacy `_is_pre_order` meta without checking the release date, so
 * historical pre-orders that shipped years ago still show "Pre-order" in the
 * catalog. The import itself only ever set the flag together with a FUTURE
 * availability date — restore that invariant. Idempotent.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // Stale by date: the pre-order window has closed.
        DB::table('product_variants')
            ->where('is_preorder', true)
            ->whereNotNull('preorder_available_from')
            ->where('preorder_available_from', '<=', $now)
            ->update(['is_preorder' => false, 'preorder_available_from' => null]);

        // Dateless flags on imported variants — these can only come from the
        // backfill (the import required a future date, and admins set dates
        // via the variant form). Scoped to the import mapping for safety.
        DB::table('product_variants')
            ->where('is_preorder', true)
            ->whereNull('preorder_available_from')
            ->whereIn('id', fn ($query) => $query
                ->select('product_variant_id')
                ->from('import_legacy_variants'))
            ->update(['is_preorder' => false]);

        // Products: same date rule…
        DB::table('products')
            ->where('is_preorder', true)
            ->whereNotNull('preorder_available_from')
            ->where('preorder_available_from', '<=', $now)
            ->update(['is_preorder' => false, 'preorder_available_from' => null]);

        // …and dateless product flags that no longer have a single live
        // pre-order variant behind them.
        DB::table('products')
            ->where('is_preorder', true)
            ->whereNull('preorder_available_from')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('product_variants')
                ->whereColumn('product_variants.product_id', 'products.id')
                ->where('product_variants.is_preorder', true))
            ->update(['is_preorder' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data repair: the stale flags carried no information
        // beyond what the legacy WC database still holds.
    }
};
