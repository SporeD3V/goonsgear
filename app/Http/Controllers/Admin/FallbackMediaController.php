<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FallbackMediaController extends Controller
{
    private static ?bool $productMediaHasConversionColumns = null;

    /**
     * @var array<string, string>
     */
    private const IMAGE_MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $optimization = $request->string('optimization')->trim()->toString();
        $usage = $request->string('usage')->trim()->toString();
        $productState = $request->string('product_state')->trim()->toString();

        $entries = $this->collectEnrichedEntries();

        $filtered = $entries->filter(function (array $entry) use ($search, $optimization, $usage, $productState): bool {
            if ($search !== '') {
                $searchHaystack = strtolower(implode(' ', [
                    $entry['filename'],
                    $entry['fallback_path'],
                    $entry['product_slug'],
                    $entry['product_name'],
                ]));

                if (! str_contains($searchHaystack, strtolower($search))) {
                    return false;
                }
            }

            if ($optimization === 'missing' && $entry['has_optimized']) {
                return false;
            }

            if ($optimization === 'present' && ! $entry['has_optimized']) {
                return false;
            }

            if ($usage === 'fallback' && ! $entry['uses_fallback']) {
                return false;
            }

            if ($usage === 'webp' && ! $entry['uses_webp']) {
                return false;
            }

            if ($usage === 'avif' && ! $entry['uses_avif']) {
                return false;
            }

            if ($usage === 'none' && $entry['matching_media_count'] > 0) {
                return false;
            }

            if ($productState === 'unknown' && $entry['has_product']) {
                return false;
            }

            if ($productState === 'known' && ! $entry['has_product']) {
                return false;
            }

            return true;
        })->values();

        $page = $request->integer('page', 1);
        $perPage = 25;

        $paginated = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('admin.maintenance.fallback-media', [
            'entries' => $paginated,
            'filters' => [
                'search' => $search,
                'optimization' => in_array($optimization, ['all', 'missing', 'present'], true) ? $optimization : 'all',
                'usage' => in_array($usage, ['all', 'fallback', 'webp', 'avif', 'none'], true) ? $usage : 'all',
                'product_state' => in_array($productState, ['all', 'known', 'unknown'], true) ? $productState : 'all',
            ],
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $fallbackPath = $request->string('fallback_path')->trim()->toString();

        if (! $this->isValidFallbackPath($fallbackPath)) {
            return redirect()
                ->back()
                ->withErrors(['fallback_media' => 'Invalid fallback path.']);
        }

        if (! Storage::disk('public')->exists($fallbackPath)) {
            return redirect()
                ->back()
                ->withErrors(['fallback_media' => 'Fallback file does not exist.']);
        }

        Storage::disk('public')->delete($fallbackPath);

        Cache::forget('admin:fallback-media:entries');

        Log::warning('Fallback media deleted from admin maintenance page.', [
            'fallback_path' => $fallbackPath,
        ]);

        return redirect()
            ->back()
            ->with('status', 'Fallback file deleted successfully.');
    }

    public function reconvertAndUse(Request $request): RedirectResponse
    {
        $fallbackPath = $request->string('fallback_path')->trim()->toString();

        if (! $this->isValidFallbackPath($fallbackPath)) {
            return redirect()
                ->back()
                ->withErrors(['fallback_media' => 'Invalid fallback path.']);
        }

        if (! Storage::disk('public')->exists($fallbackPath)) {
            return redirect()
                ->back()
                ->withErrors(['fallback_media' => 'Fallback file does not exist.']);
        }

        $productSlug = $this->extractProductSlug($fallbackPath);
        $product = $productSlug !== null ? Product::query()->where('slug', $productSlug)->first() : null;

        if (! $product instanceof Product) {
            return redirect()
                ->back()
                ->withErrors(['fallback_media' => 'Could not resolve product for fallback image.']);
        }

        $pathWithoutExtension = pathinfo($fallbackPath, PATHINFO_DIRNAME).'/'.pathinfo($fallbackPath, PATHINFO_FILENAME);
        $galleryPathWithoutExtension = str_replace('/fallback/', '/gallery/', $pathWithoutExtension);

        $webpPath = $galleryPathWithoutExtension.'.webp';
        $avifPath = $galleryPathWithoutExtension.'.avif';

        $absoluteSourcePath = storage_path('app/public/'.$fallbackPath);

        $avifCreated = $this->convertImageToFormat($absoluteSourcePath, $avifPath, 'avif');
        $webpCreated = $this->convertImageToFormat($absoluteSourcePath, $webpPath, 'webp');

        if (! $avifCreated && ! $webpCreated) {
            return redirect()
                ->back()
                ->withErrors(['fallback_media' => 'Reconversion failed. No optimized file was created.']);
        }

        $preferredPath = $avifCreated ? $avifPath : $webpPath;
        $preferredFormat = $avifCreated ? 'avif' : 'webp';

        $matchingMedia = ProductMedia::query()
            ->where('product_id', $product->id)
            ->where(function ($query) use ($fallbackPath, $galleryPathWithoutExtension): void {
                $query->where('path', $fallbackPath)
                    ->orWhere('path', 'like', $galleryPathWithoutExtension.'.%');
            })
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        if ($matchingMedia->isEmpty()) {
            $nextPosition = (int) (ProductMedia::query()->where('product_id', $product->id)->max('position') ?? -1) + 1;

            ProductMedia::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'disk' => 'public',
                'path' => $preferredPath,
                'mime_type' => self::IMAGE_MIME_BY_EXTENSION[$preferredFormat],
                'is_primary' => ! ProductMedia::query()->where('product_id', $product->id)->where('is_primary', true)->exists(),
                'position' => $nextPosition,
                ...($this->productMediaSupportsConversionMetadata() ? [
                    'is_converted' => true,
                    'converted_to' => $preferredFormat,
                ] : []),
            ]);
        } else {
            $media = $matchingMedia->first();

            if ($media instanceof ProductMedia) {
                $updatePayload = [
                    'path' => $preferredPath,
                    'mime_type' => self::IMAGE_MIME_BY_EXTENSION[$preferredFormat],
                ];

                if ($this->productMediaSupportsConversionMetadata()) {
                    $updatePayload['is_converted'] = true;
                    $updatePayload['converted_to'] = $preferredFormat;
                }

                $media->update($updatePayload);
            }
        }

        Log::warning('Fallback media reconverted and set as product media.', [
            'product_id' => $product->id,
            'fallback_path' => $fallbackPath,
            'preferred_path' => $preferredPath,
            'preferred_format' => $preferredFormat,
        ]);

        Cache::forget('admin:fallback-media:entries');

        return redirect()
            ->back()
            ->with('status', 'Fallback image reconverted and applied successfully.');
    }

    /**
     * @return Collection<int, array{fallback_path: string, filename: string, product_name: string, product_slug: string, has_product: bool, fallback_url: string, optimized_variants: array<int, string>, has_optimized: bool, uses_webp: bool, uses_avif: bool, uses_fallback: bool, matching_media_count: int, base_gallery_path: string}>
     */
    private function collectEnrichedEntries(): Collection
    {
        return Cache::remember('admin:fallback-media:entries', 300, function (): Collection {
            $allFiles = collect(Storage::disk('public')->allFiles('products'));
            $allPathsSet = $allFiles->flip();

            $fallbackFiles = $allFiles
                ->filter(fn (string $path): bool => str_contains($path, '/fallback/'))
                ->map(function (string $path) use ($allPathsSet): array {
                    $pathWithoutExtension = pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME);
                    $galleryPathWithoutExtension = str_replace('/fallback/', '/gallery/', $pathWithoutExtension);
                    $optimizedVariants = [];

                    foreach (['webp', 'avif'] as $ext) {
                        if ($allPathsSet->has($galleryPathWithoutExtension.'.'.$ext)) {
                            $optimizedVariants[] = $ext;
                        }
                    }

                    return [
                        'relative_path' => $path,
                        'product_slug' => (string) ($this->extractProductSlug($path) ?? ''),
                        'filename' => basename($path),
                        'path_without_extension' => $pathWithoutExtension,
                        'optimized_variants' => $optimizedVariants,
                    ];
                })
                ->values();

            $productSlugs = $fallbackFiles->pluck('product_slug')->filter()->unique()->values();

            $productsBySlug = Product::query()
                ->whereIn('slug', $productSlugs)
                ->get(['id', 'name', 'slug'])
                ->keyBy('slug');

            $mediaColumns = ['id', 'product_id', 'path', 'mime_type', 'is_primary'];

            if ($this->productMediaSupportsConversionMetadata()) {
                $mediaColumns[] = 'is_converted';
                $mediaColumns[] = 'converted_to';
            }

            $allMedia = ProductMedia::query()
                ->whereIn('product_id', $productsBySlug->pluck('id')->values())
                ->get($mediaColumns);

            return $fallbackFiles->map(function (array $fallbackFile) use ($productsBySlug, $allMedia): array {
                $product = $productsBySlug->get($fallbackFile['product_slug']);
                $baseGalleryPath = str_replace('/fallback/', '/gallery/', $fallbackFile['path_without_extension']);
                $matchingMedia = collect();

                if ($product instanceof Product) {
                    $matchingMedia = $allMedia->where('product_id', $product->id)
                        ->filter(function (ProductMedia $media) use ($fallbackFile, $baseGalleryPath): bool {
                            return $media->path === $fallbackFile['relative_path']
                                || Str::startsWith($media->path, $baseGalleryPath.'.');
                        })
                        ->values();
                }

                return [
                    'fallback_path' => $fallbackFile['relative_path'],
                    'filename' => $fallbackFile['filename'],
                    'product_name' => $product instanceof Product ? $product->name : '',
                    'product_slug' => $fallbackFile['product_slug'],
                    'has_product' => $product instanceof Product,
                    'fallback_url' => Storage::disk('public')->url($fallbackFile['relative_path']),
                    'optimized_variants' => $fallbackFile['optimized_variants'],
                    'has_optimized' => $fallbackFile['optimized_variants'] !== [],
                    'uses_webp' => $matchingMedia->contains(fn (ProductMedia $media): bool => str_ends_with($media->path, '.webp')),
                    'uses_avif' => $matchingMedia->contains(fn (ProductMedia $media): bool => str_ends_with($media->path, '.avif')),
                    'uses_fallback' => $matchingMedia->contains(fn (ProductMedia $media): bool => $media->path === $fallbackFile['relative_path']),
                    'matching_media_count' => $matchingMedia->count(),
                    'base_gallery_path' => $baseGalleryPath,
                ];
            })->sortBy([
                fn (array $entry): int => $entry['has_product'] ? 0 : 1,
                fn (array $entry): string => $entry['product_slug'],
                fn (array $entry): string => $entry['filename'],
            ])->values();
        });
    }

    private function isValidFallbackPath(string $path): bool
    {
        return str_starts_with($path, 'products/')
            && str_contains($path, '/fallback/')
            && ! str_contains($path, '..');
    }

    private function extractProductSlug(string $path): ?string
    {
        $segments = explode('/', $path);

        if (count($segments) < 4 || $segments[0] !== 'products') {
            return null;
        }

        return $segments[1] !== '' ? $segments[1] : null;
    }

    private function productMediaSupportsConversionMetadata(): bool
    {
        if (self::$productMediaHasConversionColumns !== null) {
            return self::$productMediaHasConversionColumns;
        }

        self::$productMediaHasConversionColumns = Schema::hasColumns('product_media', [
            'is_converted',
            'converted_to',
        ]);

        return self::$productMediaHasConversionColumns;
    }

    private function convertImageToFormat(string $sourceAbsolutePath, string $targetRelativePath, string $targetFormat): bool
    {
        if (! in_array($targetFormat, ['webp', 'avif'], true)) {
            return false;
        }

        $absoluteTargetPath = storage_path('app/public/'.$targetRelativePath);
        $targetDirectory = dirname($absoluteTargetPath);

        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        if ($targetFormat === 'avif' && ! function_exists('imageavif') && class_exists('Imagick')) {
            return $this->convertWithImagick($sourceAbsolutePath, $absoluteTargetPath, 'avif', 62);
        }

        if ($targetFormat === 'webp' && ! function_exists('imagewebp') && class_exists('Imagick')) {
            return $this->convertWithImagick($sourceAbsolutePath, $absoluteTargetPath, 'webp', 82);
        }

        if (! function_exists('imagecreatetruecolor') || ! function_exists('getimagesize')) {
            return false;
        }

        if ($targetFormat === 'webp' && ! function_exists('imagewebp')) {
            return false;
        }

        if ($targetFormat === 'avif' && ! function_exists('imageavif')) {
            return false;
        }

        $imageInfo = @getimagesize($sourceAbsolutePath);

        if ($imageInfo === false || ! isset($imageInfo['mime'])) {
            return false;
        }

        $mimeType = strtolower((string) $imageInfo['mime']);
        $imageResource = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourceAbsolutePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourceAbsolutePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourceAbsolutePath) : false,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($sourceAbsolutePath) : false,
            default => false,
        };

        if ($imageResource === false) {
            return false;
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($imageResource);
        }

        if (function_exists('imagealphablending')) {
            @imagealphablending($imageResource, true);
        }

        if (function_exists('imagesavealpha')) {
            @imagesavealpha($imageResource, true);
        }

        $absoluteTargetPath = storage_path('app/public/'.$targetRelativePath);
        $targetDirectory = dirname($absoluteTargetPath);

        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $saved = match ($targetFormat) {
            'webp' => @imagewebp($imageResource, $absoluteTargetPath, 82),
            'avif' => @imageavif($imageResource, $absoluteTargetPath, 62),
            default => false,
        };

        return $saved && is_file($absoluteTargetPath);
    }

    private function convertWithImagick(string $sourcePath, string $targetPath, string $format, int $quality): bool
    {
        try {
            $imagick = $this->createImagick($sourcePath);
            $imagick->setImageFormat($format);
            $imagick->setImageCompressionQuality($quality);
            $imagick->stripImage();
            $saved = $imagick->writeImage($targetPath);
            $imagick->clear();
            $imagick->destroy();

            return $saved && is_file($targetPath);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return object{setImageFormat: callable, setImageCompressionQuality: callable, stripImage: callable, writeImage: callable, clear: callable, destroy: callable}
     */
    private function createImagick(string $path): object
    {
        $class = 'Imagick';

        return new $class($path);
    }
}
