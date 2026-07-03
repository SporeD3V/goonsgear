<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearExpiredPreorders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-expired-preorders {--dry-run : Show what would be released without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release pre-orders whose availability date has passed so they sell as regular products.';

    /**
     * Execute the console command.
     *
     * Only date-expired flags are cleared. Dateless pre-order flags are an
     * admin's deliberate "pre-order until I say otherwise" and stay untouched.
     */
    public function handle(): int
    {
        $now = now();
        $dryRun = (bool) $this->option('dry-run');

        $expiredVariants = DB::table('product_variants')
            ->where('is_preorder', true)
            ->whereNotNull('preorder_available_from')
            ->where('preorder_available_from', '<=', $now);

        $expiredProducts = DB::table('products')
            ->where('is_preorder', true)
            ->whereNotNull('preorder_available_from')
            ->where('preorder_available_from', '<=', $now);

        if ($dryRun) {
            $this->info("[DRY RUN] Would release {$expiredVariants->count()} variant(s) and {$expiredProducts->count()} product(s).");

            return self::SUCCESS;
        }

        $variantCount = $expiredVariants->update(['is_preorder' => false, 'preorder_available_from' => null]);
        $productCount = $expiredProducts->update(['is_preorder' => false, 'preorder_available_from' => null]);

        $this->info("Released {$variantCount} variant(s) and {$productCount} product(s) from expired pre-order state.");

        return self::SUCCESS;
    }
}
