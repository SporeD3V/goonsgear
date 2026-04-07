<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AssociateLegacyMedia extends Command
{
    protected $signature = 'media:associate-legacy
        {--dry-run : Preview without writing to product_media}
        {--limit=0 : Limit number of mapped products to process}
        {--product= : Only process a specific local product ID}
        {--legacy-root= : Absolute path to extracted legacy uploads root}
        {--clear-existing : Remove existing media records for each processed product before associating}';

    protected $description = 'Associate legacy WooCommerce attachments to imported products and variants, generating AVIF/WebP media.';

    /**
     * @var array<int, string|null>
     */
    private array $attachmentPathCache = [];

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

    /**
     * @var array<string, string>
     */
    private const VIDEO_MIME_BY_EXTENSION = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
    ];

    /**
     * @var list<string>
     */
    private const CROPPED_SUFFIXES = [
        '-thumbnail-200x200',
        '-hero-1200x600',
        '-gallery-600x600',
    ];

    public function handle(): int
    {
        $legacyRoot = (string) ($this->option('legacy-root') ?: storage_path('app/legacy-uploads/uploads_extracted'));

        if (! is_dir($legacyRoot)) {
            $this->error('Legacy uploads root not found: '.$legacyRoot);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $clearExisting = (bool) $this->option('clear-existing');
        $limit = max(0, (int) $this->option('limit'));
        $onlyProductId = $this->option('product');
        $legacy = DB::connection('legacy');

        $mappedProductsQuery = DB::table('import_legacy_products as map')
            ->join('products', 'products.id', '=', 'map.product_id')
            ->select('map.legacy_wp_post_id', 'map.product_id', 'products.slug')
            ->orderBy('map.product_id');

        if ($onlyProductId !== null && $onlyProductId !== '') {
            $mappedProductsQuery->where('map.product_id', (int) $onlyProductId);
        }

        if ($limit > 0) {
            $mappedProductsQuery->limit($limit);
        }

        $mappedProducts = $mappedProductsQuery->get();

        if ($mappedProducts->isEmpty()) {
            $this->warn('No mapped products found for association.');

            return self::SUCCESS;
        }

        $processedProducts = 0;
        $createdMedia = 0;
        $updatedMedia = 0;
        $missingSources = 0;

        foreach ($mappedProducts as $mappedProduct) {
            $product = Product::query()->find((int) $mappedProduct->product_id);

            if ($product === null) {
                continue;
            }

            $processedProducts++;
            $position = 0;

            if ($clearExisting && ! $dryRun) {
                ProductMedia::query()->where('product_id', $product->id)->delete();
            }

            $productAttachmentIds = $this->legacyProductAttachmentIds($legacy, (int) $mappedProduct->legacy_wp_post_id);

            foreach ($productAttachmentIds as $attachmentId) {
                $result = $this->syncAttachmentForProduct(
                    legacy: $legacy,
                    legacyRoot: $legacyRoot,
                    product: $product,
                    attachmentId: $attachmentId,
                    variantId: null,
                    position: $position,
                    isPrimary: $position === 0,
                    dryRun: $dryRun,
                );

                if ($result['synced']) {
                    $createdMedia += $result['created'];
                    $updatedMedia += $result['updated'];
                    $position++;
                } else {
                    $missingSources += $result['missing'];
                }
            }

            $variantMappings = DB::table('import_legacy_variants as map')
                ->join('product_variants', 'product_variants.id', '=', 'map.product_variant_id')
                ->where('product_variants.product_id', $product->id)
                ->select('map.legacy_wp_post_id', 'map.product_variant_id')
                ->get();

            foreach ($variantMappings as $variantMapping) {
                $variantThumbnail = $legacy->table('wp_postmeta')
                    ->where('post_id', (int) $variantMapping->legacy_wp_post_id)
                    ->where('meta_key', '_thumbnail_id')
                    ->value('meta_value');

                $variantAttachmentId = (int) $variantThumbnail;

                if ($variantAttachmentId <= 0) {
                    continue;
                }

                if (in_array($variantAttachmentId, $productAttachmentIds, true)) {
                    continue;
                }

                $result = $this->syncAttachmentForProduct(
                    legacy: $legacy,
                    legacyRoot: $legacyRoot,
                    product: $product,
                    attachmentId: $variantAttachmentId,
                    variantId: (int) $variantMapping->product_variant_id,
                    position: $position,
                    isPrimary: false,
                    dryRun: $dryRun,
                );

                if ($result['synced']) {
                    $createdMedia += $result['created'];
                    $updatedMedia += $result['updated'];
                    $position++;
                } else {
                    $missingSources += $result['missing'];
                }
            }
        }

        $this->info('Processed products: '.$processedProducts);
        $this->info('Media created: '.$createdMedia);
        $this->info('Media updated: '.$updatedMedia);
        $this->info('Missing legacy sources: '.$missingSources);

        if ($dryRun) {
            $this->line('Dry run mode enabled. No database changes were written.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function legacyProductAttachmentIds(ConnectionInterface $legacy, int $legacyProductId): array
    {
        $meta = $legacy->table('wp_postmeta')
            ->where('post_id', $legacyProductId)
            ->whereIn('meta_key', ['_thumbnail_id', '_product_image_gallery'])
            ->pluck('meta_value', 'meta_key');

        $thumbnailId = (int) ($meta->get('_thumbnail_id') ?? 0);
        $galleryIdsRaw = (string) ($meta->get('_product_image_gallery') ?? '');
        $galleryIds = array_values(array_filter(array_map('intval', explode(',', $galleryIdsRaw))));

        $ordered = [];

        if ($thumbnailId > 0) {
            $ordered[] = $thumbnailId;
        }

        foreach ($galleryIds as $galleryId) {
            if ($galleryId > 0) {
                $ordered[] = $galleryId;
            }
        }

        return array_values(array_unique($ordered));
    }

    /**
     * @return array{synced: bool, created: int, updated: int, missing: int}
     */
    private function syncAttachmentForProduct(
        ConnectionInterface $legacy,
        string $legacyRoot,
        Product $product,
        int $attachmentId,
        ?int $variantId,
        int $position,
        bool $isPrimary,
        bool $dryRun,
    ): array {
        $attachmentRelativePath = $this->legacyAttachmentRelativePath($legacy, $attachmentId);

        if ($attachmentRelativePath === null) {
            return ['synced' => false, 'created' => 0, 'updated' => 0, 'missing' => 1];
        }

        $sourceAbsolutePath = $this->resolveBestLegacySourcePath($legacyRoot, $attachmentRelativePath);

        if ($sourceAbsolutePath === null) {
            return ['synced' => false, 'created' => 0, 'updated' => 0, 'missing' => 1];
        }

        $storedMedia = $dryRun
            ? $this->predictLegacyMediaPath($product, $attachmentId, $sourceAbsolutePath)
            : $this->storeLegacyMediaFile($product, $attachmentId, $sourceAbsolutePath);

        $existing = ProductMedia::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->where('path', $storedMedia['path'])
            ->first();

        if ($existing !== null) {
            if (! $dryRun) {
                $existing->update([
                    'mime_type' => $storedMedia['mime_type'],
                    'is_converted' => $storedMedia['is_converted'],
                    'converted_to' => $storedMedia['converted_to'],
                    'position' => $position,
                    'is_primary' => $isPrimary,
                ]);
            }

            return ['synced' => true, 'created' => 0, 'updated' => 1, 'missing' => 0];
        }

        if (! $dryRun) {
            ProductMedia::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'disk' => 'public',
                'path' => $storedMedia['path'],
                'mime_type' => $storedMedia['mime_type'],
                'is_converted' => $storedMedia['is_converted'],
                'converted_to' => $storedMedia['converted_to'],
                'is_primary' => $isPrimary,
                'position' => $position,
            ]);
        }

        return ['synced' => true, 'created' => 1, 'updated' => 0, 'missing' => 0];
    }

    private function legacyAttachmentRelativePath(ConnectionInterface $legacy, int $attachmentId): ?string
    {
        if (array_key_exists($attachmentId, $this->attachmentPathCache)) {
            return $this->attachmentPathCache[$attachmentId];
        }

        $attachedFile = $legacy->table('wp_postmeta')
            ->where('post_id', $attachmentId)
            ->where('meta_key', '_wp_attached_file')
            ->value('meta_value');

        $normalized = is_string($attachedFile) && trim($attachedFile) !== ''
            ? str_replace('\\\\', '/', trim($attachedFile))
            : null;

        $this->attachmentPathCache[$attachmentId] = $normalized;

        return $normalized;
    }

    private function resolveBestLegacySourcePath(string $legacyRoot, string $attachmentRelativePath): ?string
    {
        $normalizedRelativePath = ltrim(str_replace('\\\\', '/', $attachmentRelativePath), '/');
        $absolutePath = $legacyRoot.'/'.$normalizedRelativePath;

        $candidatePaths = [$absolutePath];

        $pathInfo = pathinfo($absolutePath);
        $directory = $pathInfo['dirname'] === '.' ? $legacyRoot : $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = strtolower((string) ($pathInfo['extension'] ?? ''));

        if ($filename !== '') {
            foreach (self::CROPPED_SUFFIXES as $suffix) {
                if (str_ends_with($filename, $suffix)) {
                    $uncroppedBase = substr($filename, 0, -strlen($suffix));

                    if ($uncroppedBase !== '') {
                        $candidatePaths[] = $directory.'/'.$uncroppedBase.($extension !== '' ? '.'.$extension : '');

                        foreach (array_keys(self::IMAGE_MIME_BY_EXTENSION) as $imageExtension) {
                            $candidatePaths[] = $directory.'/'.$uncroppedBase.'.'.$imageExtension;
                        }
                    }
                }
            }
        }

        foreach ($candidatePaths as $candidatePath) {
            if (is_file($candidatePath)) {
                return $candidatePath;
            }
        }

        return null;
    }

    /**
     * @return array{path: string, mime_type: string, is_converted: bool, converted_to: ?string}
     */
    private function predictLegacyMediaPath(Product $product, int $attachmentId, string $sourceAbsolutePath): array
    {
        $productDirectory = 'products/'.Str::slug($product->slug);
        $mediaDirectory = $productDirectory.'/gallery';
        $fallbackDirectory = $productDirectory.'/fallback';

        $originalName = pathinfo($sourceAbsolutePath, PATHINFO_FILENAME);
        $seoBaseName = Str::slug($originalName);
        $seoBaseName = $seoBaseName !== '' ? $seoBaseName : 'media';
        $baseFilename = 'legacy-'.$attachmentId.'-'.$seoBaseName;

        $extension = strtolower((string) pathinfo($sourceAbsolutePath, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : 'bin';

        if ($this->isImageExtension($extension)) {
            return [
                'path' => $mediaDirectory.'/'.$baseFilename.'.avif',
                'mime_type' => 'image/avif',
                'is_converted' => true,
                'converted_to' => 'avif',
            ];
        }

        return [
            'path' => $fallbackDirectory.'/'.$baseFilename.'.'.$extension,
            'mime_type' => $this->mimeTypeFromExtension($extension),
            'is_converted' => false,
            'converted_to' => null,
        ];
    }

    /**
     * @return array{path: string, mime_type: string, is_converted: bool, converted_to: ?string}
     */
    private function storeLegacyMediaFile(Product $product, int $attachmentId, string $sourceAbsolutePath): array
    {
        $productDirectory = 'products/'.Str::slug($product->slug);
        $mediaDirectory = $productDirectory.'/gallery';
        $fallbackDirectory = $productDirectory.'/fallback';

        $originalName = pathinfo($sourceAbsolutePath, PATHINFO_FILENAME);
        $seoBaseName = Str::slug($originalName);
        $seoBaseName = $seoBaseName !== '' ? $seoBaseName : 'media';
        $baseFilename = 'legacy-'.$attachmentId.'-'.$seoBaseName;

        $extension = strtolower((string) pathinfo($sourceAbsolutePath, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : 'bin';

        $fallbackPath = $fallbackDirectory.'/'.$baseFilename.'.'.$extension;

        if (! Storage::disk('public')->exists($fallbackPath)) {
            $fallbackAbsolute = storage_path('app/public/'.$fallbackPath);
            $fallbackAbsoluteDir = dirname($fallbackAbsolute);

            if (! is_dir($fallbackAbsoluteDir)) {
                mkdir($fallbackAbsoluteDir, 0755, true);
            }

            copy($sourceAbsolutePath, $fallbackAbsolute);
        }

        if (! $this->isImageExtension($extension)) {
            $galleryPath = $mediaDirectory.'/'.$baseFilename.'.'.$extension;

            if (! Storage::disk('public')->exists($galleryPath)) {
                Storage::disk('public')->copy($fallbackPath, $galleryPath);
            }

            return [
                'path' => $galleryPath,
                'mime_type' => $this->mimeTypeFromExtension($extension),
                'is_converted' => false,
                'converted_to' => null,
            ];
        }

        $webpPath = $mediaDirectory.'/'.$baseFilename.'.webp';
        $avifPath = $mediaDirectory.'/'.$baseFilename.'.avif';

        $avifCreated = Storage::disk('public')->exists($avifPath)
            ? (Storage::disk('public')->size($avifPath) > 0)
            : $this->convertImageToFormat($sourceAbsolutePath, storage_path('app/public/'.$avifPath), 'avif');

        if ($avifCreated && Storage::disk('public')->size($avifPath) === 0) {
            Storage::disk('public')->delete($avifPath);
            $avifCreated = false;
            $this->warn("AVIF conversion resulted in 0-byte file, skipping: {$avifPath}");
        }

        $webpCreated = Storage::disk('public')->exists($webpPath)
            ? (Storage::disk('public')->size($webpPath) > 0)
            : $this->convertImageToFormat($sourceAbsolutePath, storage_path('app/public/'.$webpPath), 'webp');

        if ($webpCreated && Storage::disk('public')->size($webpPath) === 0) {
            Storage::disk('public')->delete($webpPath);
            $webpCreated = false;
            $this->warn("WebP conversion resulted in 0-byte file, skipping: {$webpPath}");
        }

        if ($avifCreated) {
            $storedPath = $avifPath;
            $mimeType = 'image/avif';
            $convertedTo = 'avif';
        } elseif ($webpCreated) {
            $storedPath = $webpPath;
            $mimeType = 'image/webp';
            $convertedTo = 'webp';
        } else {
            $galleryFallbackPath = $mediaDirectory.'/'.$baseFilename.'.'.$extension;

            if (! Storage::disk('public')->exists($galleryFallbackPath)) {
                Storage::disk('public')->copy($fallbackPath, $galleryFallbackPath);
            }

            $storedPath = $galleryFallbackPath;
            $mimeType = $this->mimeTypeFromExtension($extension);
            $convertedTo = null;
        }

        $this->createImageVariants($sourceAbsolutePath, $mediaDirectory, $baseFilename);

        return [
            'path' => $storedPath,
            'mime_type' => $mimeType,
            'is_converted' => $convertedTo !== null,
            'converted_to' => $convertedTo,
        ];
    }

    private function isImageExtension(string $extension): bool
    {
        return array_key_exists($extension, self::IMAGE_MIME_BY_EXTENSION);
    }

    private function mimeTypeFromExtension(string $extension): string
    {
        $normalized = strtolower($extension);

        return self::IMAGE_MIME_BY_EXTENSION[$normalized]
            ?? self::VIDEO_MIME_BY_EXTENSION[$normalized]
            ?? 'application/octet-stream';
    }

    private function convertImageToFormat(string $sourceAbsolutePath, string $targetAbsolutePath, string $targetFormat): bool
    {
        try {
            $targetDirectory = dirname($targetAbsolutePath);
            if (! is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0755, true);
            }

            if ($targetFormat === 'avif' && ! function_exists('imageavif') && class_exists('Imagick')) {
                return $this->convertWithImagick($sourceAbsolutePath, $targetAbsolutePath, 'avif', 62);
            }

            if ($targetFormat === 'webp' && ! function_exists('imagewebp') && class_exists('Imagick')) {
                return $this->convertWithImagick($sourceAbsolutePath, $targetAbsolutePath, 'webp', 82);
            }

            if (! function_exists('getimagesize') || ! function_exists('imagecreatetruecolor')) {
                return false;
            }

            $imageInfo = @getimagesize($sourceAbsolutePath);
            if ($imageInfo === false) {
                return false;
            }

            $mime = strtolower((string) $imageInfo['mime']);
            $image = match ($mime) {
                'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourceAbsolutePath) : false,
                'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourceAbsolutePath) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourceAbsolutePath) : false,
                'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($sourceAbsolutePath) : false,
                default => false,
            };

            if ($image === false) {
                return false;
            }

            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($image);
            }

            if (function_exists('imagealphablending')) {
                @imagealphablending($image, true);
            }

            if (function_exists('imagesavealpha')) {
                @imagesavealpha($image, true);
            }

            $saved = match ($targetFormat) {
                'avif' => function_exists('imageavif') ? @imageavif($image, $targetAbsolutePath, 62) : false,
                'webp' => function_exists('imagewebp') ? @imagewebp($image, $targetAbsolutePath, 82) : false,
                default => false,
            };

            return $saved && is_file($targetAbsolutePath);
        } catch (Throwable) {
            return false;
        }
    }

    private function convertWithImagick(string $sourcePath, string $targetPath, string $format, int $quality): bool
    {
        try {
            $class = 'Imagick';
            $imagick = new $class($sourcePath);
            $imagick->setImageFormat($format);
            $imagick->setImageCompressionQuality($quality);
            $imagick->stripImage();
            $saved = $imagick->writeImage($targetPath);
            $imagick->clear();
            $imagick->destroy();

            return $saved && is_file($targetPath);
        } catch (Throwable) {
            return false;
        }
    }

    private function createImageVariants(string $sourceAbsolutePath, string $mediaDirectory, string $baseFilename): void
    {
        $variants = config('images.sizes', []);

        if (! is_array($variants) || $variants === []) {
            return;
        }

        foreach ($variants as $variantName => $variant) {
            $variantWidth = (int) ($variant['width'] ?? 0);
            $variantHeight = (int) ($variant['height'] ?? 0);

            if ($variantWidth <= 0 || $variantHeight <= 0) {
                continue;
            }

            $variantFilename = $baseFilename.'-'.$variantName.'-'.$variantWidth.'x'.$variantHeight;
            $variantAvifPath = storage_path('app/public/'.$mediaDirectory.'/'.$variantFilename.'.avif');
            $variantWebpPath = storage_path('app/public/'.$mediaDirectory.'/'.$variantFilename.'.webp');

            if (! is_file($variantAvifPath)) {
                $this->createCroppedVariant($sourceAbsolutePath, $variantAvifPath, $variantWidth, $variantHeight, 'avif');
            }

            if (! is_file($variantWebpPath)) {
                $this->createCroppedVariant($sourceAbsolutePath, $variantWebpPath, $variantWidth, $variantHeight, 'webp');
            }
        }
    }

    private function createCroppedVariant(string $sourceAbsolutePath, string $targetAbsolutePath, int $width, int $height, string $format): void
    {
        try {
            if (class_exists('Imagick')) {
                $class = 'Imagick';
                $imagick = new $class($sourceAbsolutePath);
                $imagick->cropThumbnailImage($width, $height);
                $imagick->setImageFormat($format);
                $imagick->setImageCompressionQuality($format === 'avif' ? 62 : 82);
                $imagick->stripImage();

                $directory = dirname($targetAbsolutePath);
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $imagick->writeImage($targetAbsolutePath);
                $imagick->clear();
                $imagick->destroy();

                return;
            }

            $this->createCroppedVariantWithGd($sourceAbsolutePath, $targetAbsolutePath, $width, $height, $format);
        } catch (Throwable) {
            // Keep import resilient; individual variant failures should not stop association.
        }
    }

    private function createCroppedVariantWithGd(string $sourceAbsolutePath, string $targetAbsolutePath, int $width, int $height, string $format): void
    {
        if (! function_exists('getimagesize') || ! function_exists('imagecreatetruecolor')) {
            return;
        }

        $imageInfo = @getimagesize($sourceAbsolutePath);
        if ($imageInfo === false) {
            return;
        }

        $source = match (strtolower((string) $imageInfo['mime'])) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourceAbsolutePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourceAbsolutePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourceAbsolutePath) : false,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($sourceAbsolutePath) : false,
            default => false,
        };

        if ($source === false) {
            return;
        }

        $srcWidth = (int) $imageInfo[0];
        $srcHeight = (int) $imageInfo[1];
        if ($srcWidth <= 0 || $srcHeight <= 0) {
            return;
        }

        $ratio = max($width / $srcWidth, $height / $srcHeight);
        $scaledWidth = (int) round($srcWidth * $ratio);
        $scaledHeight = (int) round($srcHeight * $ratio);
        $offsetX = (int) floor(($scaledWidth - $width) / 2);
        $offsetY = (int) floor(($scaledHeight - $height) / 2);

        $scaled = @imagecreatetruecolor($scaledWidth, $scaledHeight);
        $target = @imagecreatetruecolor($width, $height);

        if ($scaled === false || $target === false) {
            return;
        }

        @imagealphablending($scaled, false);
        @imagesavealpha($scaled, true);
        @imagealphablending($target, false);
        @imagesavealpha($target, true);

        @imagecopyresampled($scaled, $source, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $srcWidth, $srcHeight);
        @imagecopy($target, $scaled, 0, 0, $offsetX, $offsetY, $width, $height);

        $directory = dirname($targetAbsolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if ($format === 'avif' && function_exists('imageavif')) {
            @imageavif($target, $targetAbsolutePath, 62);
        }

        if ($format === 'webp' && function_exists('imagewebp')) {
            @imagewebp($target, $targetAbsolutePath, 82);
        }
    }
}
