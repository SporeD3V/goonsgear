<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanRedundantWebp extends Command
{
    protected $signature = 'media:clean-redundant-webp
                            {--dry-run : List files that would be deleted without deleting them}';

    protected $description = 'Delete WebP files that have an AVIF counterpart in public storage.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');
        $allFiles = $disk->allFiles();
        $deleted = 0;
        $freed = 0;

        foreach ($allFiles as $file) {
            if (! str_ends_with(strtolower($file), '.webp')) {
                continue;
            }

            $avifSibling = substr($file, 0, -5).'.avif';

            if (! $disk->exists($avifSibling)) {
                continue;
            }

            $size = $disk->size($file);

            if ($dryRun) {
                $this->line("Would delete: {$file} (".number_format($size / 1024, 1).' KB)');
            } else {
                $disk->delete($file);
                $this->line("Deleted: {$file} (".number_format($size / 1024, 1).' KB)');
            }

            $deleted++;
            $freed += $size;
        }

        $action = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$action} {$deleted} redundant WebP files, freeing ".number_format($freed / 1024 / 1024, 2).' MB.');

        return self::SUCCESS;
    }
}
