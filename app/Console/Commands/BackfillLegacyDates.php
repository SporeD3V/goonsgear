<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillLegacyDates extends Command
{
    protected $signature = 'app:backfill-legacy-dates {--dry-run : Preview changes without writing}';

    protected $description = 'Backfill customer registration dates, order shipped_at, and product published_at from WooCommerce legacy database';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — no changes will be written.');
        }

        $this->backfillCustomerDates($dryRun);
        $this->backfillOrderShippedAt($dryRun);
        $this->backfillProductPublishedAt($dryRun);

        return self::SUCCESS;
    }

    private function backfillCustomerDates(bool $dryRun): void
    {
        $this->info('Backfilling customer registration dates...');

        $legacy = DB::connection('legacy');
        $mappings = DB::table('import_legacy_customers')->get();

        $updated = 0;
        $skipped = 0;

        foreach ($mappings as $mapping) {
            $wpUser = $legacy->table('wp_users')
                ->where('ID', $mapping->legacy_wp_user_id)
                ->select('user_registered')
                ->first();

            if (! $wpUser || ! $wpUser->user_registered) {
                $skipped++;

                continue;
            }

            $user = User::find($mapping->user_id);

            if (! $user) {
                $skipped++;

                continue;
            }

            $registeredAt = Carbon::parse($wpUser->user_registered);

            if ($dryRun) {
                $this->line("  Would update user #{$user->id} ({$user->email}): created_at {$user->created_at} → {$registeredAt}");
            } else {
                $user->timestamps = false;
                $user->created_at = $registeredAt;
                $user->save();
                $user->timestamps = true;
            }

            $updated++;
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("✓ {$verb} {$updated} customer dates ({$skipped} skipped).");
    }

    private function backfillOrderShippedAt(bool $dryRun): void
    {
        $this->info('Backfilling order shipped_at dates...');

        $legacy = DB::connection('legacy');
        $mappings = DB::table('import_legacy_orders')->get();

        $updated = 0;
        $skipped = 0;

        foreach ($mappings as $mapping) {
            $order = Order::find($mapping->order_id);

            if (! $order) {
                $skipped++;

                continue;
            }

            // Only backfill completed orders that don't already have shipped_at
            if ($order->shipped_at !== null || $order->status !== 'completed') {
                $skipped++;

                continue;
            }

            $dateCompleted = $legacy->table('wp_postmeta')
                ->where('post_id', $mapping->legacy_wc_order_id)
                ->where('meta_key', '_date_completed')
                ->value('meta_value');

            if (! $dateCompleted) {
                $skipped++;

                continue;
            }

            $shippedAt = Carbon::createFromTimestamp((int) $dateCompleted);

            if ($dryRun) {
                $this->line("  Would set order #{$order->id} ({$order->order_number}): shipped_at → {$shippedAt}");
            } else {
                $order->shipped_at = $shippedAt;
                $order->save();
            }

            $updated++;
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("✓ {$verb} {$updated} order shipped_at dates ({$skipped} skipped).");
    }

    private function backfillProductPublishedAt(bool $dryRun): void
    {
        $this->info('Backfilling product published_at dates...');

        $legacy = DB::connection('legacy');
        $mappings = DB::table('import_legacy_products')->get();

        $updated = 0;
        $skipped = 0;

        foreach ($mappings as $mapping) {
            $wpPost = $legacy->table('wp_posts')
                ->where('ID', $mapping->legacy_wp_post_id)
                ->select('post_date')
                ->first();

            if (! $wpPost || ! $wpPost->post_date) {
                $skipped++;

                continue;
            }

            $product = Product::find($mapping->product_id);

            if (! $product) {
                $skipped++;

                continue;
            }

            $publishedAt = Carbon::parse($wpPost->post_date);

            if ($dryRun) {
                $this->line("  Would update product #{$product->id} ({$product->name}): published_at {$product->published_at} → {$publishedAt}");
            } else {
                $product->published_at = $publishedAt;
                $product->save();
            }

            $updated++;
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("✓ {$verb} {$updated} product published_at dates ({$skipped} skipped).");
    }
}
