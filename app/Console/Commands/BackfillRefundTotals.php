<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillRefundTotals extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:backfill-refund-totals {--dry-run : Show what would be updated without writing}';

    /**
     * @var string
     */
    protected $description = 'Backfill orders.refund_total from WooCommerce legacy refund data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $legacy = DB::connection('legacy');

        // Sum refund amounts per parent (original) order from WC refund posts
        $refunds = $legacy->table('wp_posts')
            ->where('post_type', 'shop_order_refund')
            ->join('wp_postmeta', function ($join) {
                $join->on('wp_posts.ID', '=', 'wp_postmeta.post_id')
                    ->where('wp_postmeta.meta_key', '=', '_refund_amount');
            })
            ->groupBy('wp_posts.post_parent')
            ->selectRaw('wp_posts.post_parent as wc_order_id, SUM(CAST(wp_postmeta.meta_value AS DECIMAL(10,2))) as refund_sum')
            ->get();

        $this->info("Found {$refunds->count()} WC orders with refunds.");

        $updated = 0;
        $skipped = 0;

        foreach ($refunds as $refund) {
            // Resolve WC order ID → local order ID via the import mapping table
            $mapping = DB::table('import_legacy_orders')
                ->where('legacy_wc_order_id', (int) $refund->wc_order_id)
                ->first();

            if ($mapping === null) {
                $skipped++;

                continue;
            }

            $refundAmount = round((float) $refund->refund_sum, 2);

            if ($dryRun) {
                $this->line("  [DRY RUN] Order #{$mapping->order_id} (WC-{$refund->wc_order_id}): refund_total = {$refundAmount}");
            } else {
                DB::table('orders')
                    ->where('id', $mapping->order_id)
                    ->update(['refund_total' => $refundAmount]);
            }

            $updated++;
        }

        $label = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$label} {$updated} orders. Skipped {$skipped} (no mapping found).");

        return self::SUCCESS;
    }
}
