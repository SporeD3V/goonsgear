<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncProductMediaFromStorageCommand extends Command
{
    protected $signature = 'media:sync-from-storage {--dry-run : Preview changes without writing to database}';

    protected $description = 'Sync product media records from files under storage/app/public/products';

    private const DERIVATIVE_SUFFIXES = [
        '-thumbnail-200x200',
        '-hero-1200x600',
        '-gallery-600x600',
    ];

    /**
     * @var array<string, string>
     */
    private const MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
    ];

    public function handle(): int
    {
        $productsRoot = storage_path('app/public/products');

        if (! is_dir($productsRoot)) {
            $this->warn('Products storage directory does not exist.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $updated = 0;
        $processedProducts = 0;

        foreach (File::directories($productsRoot) as $productDirectory) {
            $slug = basename($productDirectory);
            $product = Product::query()->where('slug', $slug)->first();

            if ($product === null) {
                continue;
            }

            $candidatePaths = $this->collectCandidatePaths($productDirectory);

            if ($candidatePaths === []) {
                continue;
            }

            $processedProducts++;
            $position = 0;

            foreach ($candidatePaths as $relativePath) {
                $existing = ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->where('path', $relativePath)
                    ->first();

                $mimeType = $this->inferMimeType($relativePath);
                $isPrimary = $position === 0;

                if ($existing !== null) {
                    if (! $dryRun) {
                        $existing->update([
                            'mime_type' => $mimeType,
                            'position' => $position,
                            'is_primary' => $isPrimary,
                        ]);
                    }

                    $updated++;
                    $position++;

                    continue;
                }

                if (! $dryRun) {
                    ProductMedia::query()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'disk' => 'public',
                        'path' => $relativePath,
                        'mime_type' => $mimeType,
                        'is_converted' => false,
                        'converted_to' => null,
                        'is_primary' => $isPrimary,
                        'position' => $position,
                    ]);
                }

                $created++;
                $position++;
            }

            if (! $dryRun) {
                ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->whereNotIn('path', $candidatePaths)
                    ->update(['is_primary' => false]);
            }
        }

        $this->info('Processed products: '.$processedProducts);
        $this->info('Media created: '.$created);
        $this->info('Media updated: '.$updated);

        if ($dryRun) {
            $this->line('Dry run mode enabled. No database changes were written.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function collectCandidatePaths(string $productDirectory): array
    {
        $galleryDirectory = $productDirectory.'/gallery';
        $fallbackDirectory = $productDirectory.'/fallback';
        $relativePaths = [];

        if (is_dir($galleryDirectory)) {
            foreach (File::files($galleryDirectory) as $file) {
                $extension = strtolower((string) $file->getExtension());
                $filenameWithoutExtension = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                if (! array_key_exists($extension, self::MIME_BY_EXTENSION)) {
                    continue;
                }

                if ($this->isDerivativeFilename($filenameWithoutExtension)) {
                    continue;
                }

                $relativePaths[] = 'products/'.basename($productDirectory).'/gallery/'.$file->getFilename();
            }
        }

        if ($relativePaths === [] && is_dir($fallbackDirectory)) {
            foreach (File::files($fallbackDirectory) as $file) {
                $extension = strtolower((string) $file->getExtension());

                if (! array_key_exists($extension, self::MIME_BY_EXTENSION)) {
                    continue;
                }

                $relativePaths[] = 'products/'.basename($productDirectory).'/fallback/'.$file->getFilename();
            }
        }

        usort($relativePaths, function (string $left, string $right): int {
            $leftMime = $this->inferMimeType($left);
            $rightMime = $this->inferMimeType($right);
            $leftIsVideo = str_starts_with($leftMime, 'video/');
            $rightIsVideo = str_starts_with($rightMime, 'video/');

            if ($leftIsVideo !== $rightIsVideo) {
                return $leftIsVideo ? 1 : -1;
            }

            return strcmp($left, $right);
        });

        return array_values(array_unique($relativePaths));
    }

    private function isDerivativeFilename(string $filenameWithoutExtension): bool
    {
        foreach (self::DERIVATIVE_SUFFIXES as $suffix) {
            if (str_ends_with($filenameWithoutExtension, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function inferMimeType(string $relativePath): string
    {
        $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));

        return self::MIME_BY_EXTENSION[$extension] ?? 'application/octet-stream';
    }
}
