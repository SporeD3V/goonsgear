<?php

namespace App\Console\Commands;

use App\Models\ProductMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillMediaVariantPathsCommand extends Command
{
    protected $signature = 'media:backfill-variant-paths {--dry-run : Preview changes without writing to database}';

    protected $description = 'Backfill thumbnail_path, gallery_path, and zoom_path for existing product_media records';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        $query = ProductMedia::query()
            ->whereNull('thumbnail_path')
            ->whereNull('gallery_path')
            ->whereNull('zoom_path');

        $total = $query->count();
        $this->info("Found {$total} media records to backfill.");

        $query->chunkById(100, function ($mediaItems) use ($dryRun, &$updated, &$skipped): void {
            foreach ($mediaItems as $media) {
                /** @var ProductMedia $media */
                if (str_starts_with((string) $media->mime_type, 'video/')) {
                    $skipped++;

                    continue;
                }

                $disk = (string) ($media->disk ?: 'public');
                $thumbnailPath = $this->resolveExistingPath($disk, $media->computeVariantPath('thumbnail'), $media->path);
                $galleryPath = $this->resolveExistingPath($disk, $media->computeVariantPath('gallery'), $media->path);
                $zoomPath = $this->resolveZoomPathForBackfill($disk, $media->path);

                if (! $dryRun) {
                    $media->update([
                        'thumbnail_path' => $thumbnailPath,
                        'gallery_path' => $galleryPath,
                        'zoom_path' => $zoomPath,
                    ]);
                }

                $updated++;
            }
        });

        $this->info("Updated: {$updated}, Skipped (video): {$skipped}");

        if ($dryRun) {
            $this->line('Dry run mode — no database changes were written.');
        }

        return self::SUCCESS;
    }

    private function resolveExistingPath(string $disk, string $preferredPath, string $fallbackPath): string
    {
        $candidates = [$preferredPath];

        if (str_contains($preferredPath, '/fallback/')) {
            array_unshift($candidates, str_replace('/fallback/', '/gallery/', $preferredPath));
        }

        $candidates[] = $fallbackPath;

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (Storage::disk($disk)->exists($candidate)) {
                return $candidate;
            }
        }

        return $fallbackPath;
    }

    private function resolveZoomPathForBackfill(string $disk, string $path): string
    {
        $pathInfo = pathinfo($path);
        $directory = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'];

        if ($filename === '') {
            return $path;
        }

        $baseName = $filename;
        foreach (['-thumbnail-200x200', '-hero-1200x600', '-gallery-600x600'] as $suffix) {
            if (str_ends_with($baseName, $suffix)) {
                $baseName = substr($baseName, 0, -strlen($suffix));
                break;
            }
        }

        $basePath = ($directory !== '' && $directory !== '.' ? $directory.'/' : '').$baseName;
        $candidates = [
            $basePath.'.avif',
            $basePath.'.webp',
            $path,
        ];

        if (str_contains($path, '/fallback/')) {
            $galleryBasePath = str_replace('/fallback/', '/gallery/', $basePath);
            array_unshift($candidates, $galleryBasePath.'.avif', $galleryBasePath.'.webp');
        }

        foreach ($candidates as $candidate) {
            if (Storage::disk($disk)->exists($candidate)) {
                return $candidate;
            }
        }

        return $path;
    }
}
