<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
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

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $products = Product::query()
            ->with(['primaryCategory:id,name'])
            ->withCount(['variants', 'media'])
            ->latest('id')
            ->paginate(20);

        return view('admin.products.index', [
            'products' => $products,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.products.create', [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_preorder'] = $request->boolean('is_preorder');

        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);

        $product = Product::query()->create($validated);
        $product->categories()->sync($categoryIds);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function edit(Product $product): View
    {
        $product->load([
            'categories:id',
            'variants' => fn ($query) => $query->orderBy('position')->orderBy('id'),
            'media' => fn ($query) => $query
                ->with('variant:id,name')
                ->orderByDesc('is_primary')
                ->orderBy('position')
                ->orderBy('id'),
        ]);

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_preorder'] = $request->boolean('is_preorder');

        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);
        unset($validated['media_files']);
        unset($validated['media_alt_text']);

        $product->update($validated);
        $product->categories()->sync($categoryIds);

        $uploadedMediaFiles = $request->file('media_files', []);
        $mediaAltText = $request->string('media_alt_text')->trim()->toString();
        $mediaVariantId = $request->integer('media_variant_id');
        $mediaVariant = null;

        if ($mediaVariantId > 0) {
            $mediaVariant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->find($mediaVariantId);
        }

        if ($uploadedMediaFiles !== []) {
            $nextPosition = (int) ($product->media()->max('position') ?? -1) + 1;
            $hasPrimaryMedia = $product->media()->where('is_primary', true)->exists();
            $uploadTraceId = Str::uuid()->toString();

            Log::info('Product media upload started.', [
                'trace_id' => $uploadTraceId,
                'product_id' => $product->id,
                'product_slug' => $product->slug,
                'media_count' => count($uploadedMediaFiles),
                'media_variant_id' => $mediaVariant?->id,
                'request_url' => URL::current(),
                'php_upload_limits' => [
                    'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                    'post_max_size' => (string) ini_get('post_max_size'),
                    'max_file_uploads' => (string) ini_get('max_file_uploads'),
                    'memory_limit' => (string) ini_get('memory_limit'),
                ],
            ]);

            try {
                foreach ($uploadedMediaFiles as $index => $uploadedMediaFile) {
                    Log::info('Product media upload file received.', [
                        'trace_id' => $uploadTraceId,
                        'product_id' => $product->id,
                        'index' => $index,
                        'original_name' => $uploadedMediaFile->getClientOriginalName(),
                        'client_extension' => $uploadedMediaFile->getClientOriginalExtension(),
                        'client_mime' => $uploadedMediaFile->getClientMimeType(),
                        'detected_mime' => $uploadedMediaFile->getMimeType(),
                        'size_bytes' => $uploadedMediaFile->getSize(),
                        'tmp_path' => $uploadedMediaFile->getPathname(),
                        'is_valid' => $uploadedMediaFile->isValid(),
                        'upload_error_code' => $uploadedMediaFile->getError(),
                        'upload_error_label' => $this->uploadErrorCodeLabel($uploadedMediaFile->getError()),
                    ]);

                    if (! $uploadedMediaFile->isValid()) {
                        Log::warning('Product media upload file invalid.', [
                            'trace_id' => $uploadTraceId,
                            'product_id' => $product->id,
                            'index' => $index,
                            'upload_error_code' => $uploadedMediaFile->getError(),
                            'upload_error_label' => $this->uploadErrorCodeLabel($uploadedMediaFile->getError()),
                            'upload_error_message' => $uploadedMediaFile->getErrorMessage(),
                        ]);

                        continue;
                    }

                    $storedMedia = $this->storeMediaFile(
                        $product,
                        $uploadedMediaFile,
                        $mediaVariant,
                        $index,
                        $uploadTraceId
                    );

                    $mediaPayload = [
                        'product_id' => $product->id,
                        'product_variant_id' => $mediaVariant?->id,
                        'disk' => 'public',
                        'path' => $storedMedia['path'],
                        'mime_type' => $storedMedia['mime_type'],
                        'alt_text' => $mediaAltText !== '' ? $mediaAltText : null,
                        'is_primary' => ! $hasPrimaryMedia && $index === 0,
                        'position' => $nextPosition + $index,
                    ];

                    if ($this->productMediaSupportsConversionMetadata()) {
                        $mediaPayload['is_converted'] = $storedMedia['is_converted'];
                        $mediaPayload['converted_to'] = $storedMedia['converted_to'];
                    }

                    ProductMedia::query()->create($mediaPayload);

                    Log::info('Product media upload file stored.', [
                        'trace_id' => $uploadTraceId,
                        'product_id' => $product->id,
                        'index' => $index,
                        'path' => $storedMedia['path'],
                        'mime_type' => $storedMedia['mime_type'],
                        'is_converted' => $storedMedia['is_converted'],
                        'converted_to' => $storedMedia['converted_to'],
                    ]);
                }

                Log::info('Product media upload completed.', [
                    'trace_id' => $uploadTraceId,
                    'product_id' => $product->id,
                ]);
            } catch (Throwable $exception) {
                Log::error('Product media upload failed.', [
                    'trace_id' => $uploadTraceId,
                    'product_id' => $product->id,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);

                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([
                        'media_files' => 'Media upload failed. Please contact support with trace ID: '.$uploadTraceId,
                    ]);
            }
        }

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product updated successfully.');
    }

    /**
     * @return array{path: string, mime_type: string, is_converted: bool, converted_to: ?string}
     */
    private function storeMediaFile(Product $product, UploadedFile $uploadedMediaFile, ?ProductVariant $variant, int $index, string $uploadTraceId): array
    {
        $productDirectory = 'products/'.Str::slug($product->slug);
        $mediaDirectory = $productDirectory.'/gallery';
        $fallbackDirectory = $productDirectory.'/fallback';
        $originalName = pathinfo($uploadedMediaFile->getClientOriginalName(), PATHINFO_FILENAME);
        $seoBaseName = Str::slug((string) $originalName);
        $seoBaseName = $seoBaseName !== '' ? $seoBaseName : 'media';
        $variantPrefix = $variant instanceof ProductVariant
            ? 'variant-'.Str::slug($variant->name).'-'
            : 'product-';
        $baseFilename = $variantPrefix.now()->format('YmdHis').'-'.$index.'-'.$seoBaseName;
        $extension = strtolower((string) ($uploadedMediaFile->getClientOriginalExtension() ?: $uploadedMediaFile->extension() ?: 'bin'));

        if (! $this->isImageMimeType((string) $uploadedMediaFile->getMimeType())) {
            $filename = $baseFilename.'.'.$extension;
            $path = $uploadedMediaFile->storeAs($mediaDirectory, $filename, 'public');

            Log::info('Stored non-image product media file.', [
                'trace_id' => $uploadTraceId,
                'product_id' => $product->id,
                'index' => $index,
                'path' => $path,
            ]);

            return [
                'path' => $path,
                'mime_type' => (string) ($uploadedMediaFile->getMimeType() ?: 'application/octet-stream'),
                'is_converted' => false,
                'converted_to' => null,
            ];
        }

        // Keep the original upload as a fallback copy so we can remove it later after verification.
        $fallbackFilename = $baseFilename.'.'.$extension;
        $fallbackPath = $uploadedMediaFile->storeAs($fallbackDirectory, $fallbackFilename, 'public');

        $absoluteFallbackPath = storage_path('app/public/'.$fallbackPath);

        if (! is_file($absoluteFallbackPath)) {
            $filename = $baseFilename.'.'.$extension;
            $path = $uploadedMediaFile->storeAs($mediaDirectory, $filename, 'public');

            Log::warning('Fallback media file missing after initial store; stored original in gallery.', [
                'trace_id' => $uploadTraceId,
                'product_id' => $product->id,
                'index' => $index,
                'fallback_path' => $fallbackPath,
                'gallery_path' => $path,
            ]);

            return [
                'path' => $path,
                'mime_type' => (string) ($uploadedMediaFile->getMimeType() ?: 'application/octet-stream'),
                'is_converted' => false,
                'converted_to' => null,
            ];
        }

        $webpPath = $mediaDirectory.'/'.$baseFilename.'.webp';
        $avifPath = $mediaDirectory.'/'.$baseFilename.'.avif';

        $webpCreated = $this->convertImageToFormat($absoluteFallbackPath, $webpPath, 'webp');
        $avifCreated = $this->convertImageToFormat($absoluteFallbackPath, $avifPath, 'avif');

        if ($webpCreated) {
            return [
                'path' => $webpPath,
                'mime_type' => 'image/webp',
                'is_converted' => true,
                'converted_to' => 'webp',
            ];
        }

        if ($avifCreated) {
            return [
                'path' => $avifPath,
                'mime_type' => 'image/avif',
                'is_converted' => true,
                'converted_to' => 'avif',
            ];
        }

        $galleryFallbackPath = $mediaDirectory.'/'.$fallbackFilename;
        $copiedToGallery = Storage::disk('public')->copy($fallbackPath, $galleryFallbackPath);

        if (! $copiedToGallery) {
            Log::warning('Fallback copy to gallery failed, using fallback path directly.', [
                'trace_id' => $uploadTraceId,
                'product_id' => $product->id,
                'index' => $index,
                'fallback_path' => $fallbackPath,
                'gallery_path' => $galleryFallbackPath,
            ]);
        }

        return [
            'path' => $copiedToGallery ? $galleryFallbackPath : $fallbackPath,
            'mime_type' => self::IMAGE_MIME_BY_EXTENSION[$extension] ?? 'application/octet-stream',
            'is_converted' => false,
            'converted_to' => null,
        ];
    }

    private function isImageMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    private function uploadErrorCodeLabel(int $uploadErrorCode): string
    {
        return match ($uploadErrorCode) {
            UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
            UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
            UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
            UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
            UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
            UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
            UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
            UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
            default => 'UPLOAD_ERR_UNKNOWN',
        };
    }

    private function convertImageToFormat(string $sourceAbsolutePath, string $targetRelativePath, string $targetFormat): bool
    {
        try {
            if (! function_exists('imagecreatetruecolor') || ! function_exists('getimagesize')) {
                return false;
            }

            if (! in_array($targetFormat, ['webp', 'avif'], true)) {
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
        } catch (Throwable $exception) {
            Log::warning('Media conversion failed, using fallback/original media.', [
                'source' => $sourceAbsolutePath,
                'target' => $targetRelativePath,
                'format' => $targetFormat,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product deleted successfully.');
    }
}
