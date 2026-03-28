<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ManageFallbackMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:fallback {action=list : list or clean fallback originals} {--dry-run : Preview deletions without removing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List or clean original fallback image uploads after optimization verification.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));

        if (! in_array($action, ['list', 'clean'], true)) {
            $this->error('Action must be either list or clean.');

            return self::FAILURE;
        }

        $fallbackFiles = $this->collectFallbackFiles();

        if ($fallbackFiles === []) {
            $this->info('No fallback files found.');

            return self::SUCCESS;
        }

        $eligibleFiles = array_values(array_filter($fallbackFiles, fn (array $file): bool => $file['has_optimized']));

        $this->table(
            ['Fallback File', 'Optimized Available', 'Optimized Variants'],
            array_map(
                fn (array $file): array => [
                    $file['relative_path'],
                    $file['has_optimized'] ? 'yes' : 'no',
                    $file['optimized_variants'] !== [] ? implode(', ', $file['optimized_variants']) : '-',
                ],
                $fallbackFiles
            )
        );

        if ($action === 'list') {
            $this->info('Total fallback files: '.count($fallbackFiles));
            $this->info('Eligible for cleanup: '.count($eligibleFiles));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run enabled. No files were deleted.');
            $this->info('Files that would be deleted: '.count($eligibleFiles));

            return self::SUCCESS;
        }

        $deletedCount = 0;

        foreach ($eligibleFiles as $eligibleFile) {
            $absolutePath = storage_path('app/public/'.$eligibleFile['relative_path']);

            if (is_file($absolutePath)) {
                File::delete($absolutePath);
                $deletedCount++;
            }
        }

        $this->info('Deleted fallback files: '.$deletedCount);

        return self::SUCCESS;
    }

    /**
     * @return list<array{relative_path: string, has_optimized: bool, optimized_variants: list<string>}>
     */
    private function collectFallbackFiles(): array
    {
        $productsRoot = storage_path('app/public/products');

        if (! is_dir($productsRoot)) {
            return [];
        }

        $fallbackFiles = [];

        foreach (File::allFiles($productsRoot) as $file) {
            $absolutePath = str_replace('\\', '/', $file->getRealPath());

            if (! str_contains($absolutePath, '/fallback/')) {
                continue;
            }

            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $relativePath = 'products/'.$relativePath;

            $pathWithoutExtension = pathinfo($relativePath, PATHINFO_DIRNAME).'/'.pathinfo($relativePath, PATHINFO_FILENAME);
            $galleryPathWithoutExtension = str_replace('/fallback/', '/gallery/', $pathWithoutExtension);
            $optimizedVariants = [];

            foreach (['webp', 'avif'] as $optimizedExtension) {
                $optimizedRelativePath = $galleryPathWithoutExtension.'.'.$optimizedExtension;

                if (is_file(storage_path('app/public/'.$optimizedRelativePath))) {
                    $optimizedVariants[] = $optimizedExtension;
                }
            }

            $fallbackFiles[] = [
                'relative_path' => $relativePath,
                'has_optimized' => $optimizedVariants !== [],
                'optimized_variants' => $optimizedVariants,
            ];
        }

        return $fallbackFiles;
    }
}
