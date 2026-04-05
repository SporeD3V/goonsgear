<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class TagController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tag::query()
            ->withCount([
                'followers as followers_count',
                'products as active_products_count' => fn ($q) => $q->where('status', 'active'),
            ]);

        if ($request->filled('type') && in_array($request->input('type'), ['artist', 'brand', 'custom'])) {
            $query->where('type', $request->input('type'));
        }

        $tags = $query->orderBy('type')
            ->orderBy('name')
            ->paginate((int) config('pagination.admin_tag_per_page', 30))
            ->withQueryString();

        return view('admin.tags.index', [
            'tags' => $tags,
        ]);
    }

    public function create(): View
    {
        return view('admin.tags.create');
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');
        $validated['show_on_homepage'] = $request->boolean('show_on_homepage');
        unset($validated['logo']);

        $tag = Tag::query()->create($validated);

        if ($request->hasFile('logo') && in_array($tag->type, ['artist', 'brand'], true)) {
            $logoPath = $this->storeTagLogo($request->file('logo'), $tag->slug);

            if ($logoPath !== null) {
                $tag->update(['logo_path' => $logoPath]);
            }
        }

        // Ensure show_on_homepage is only set when a logo exists
        if ($tag->show_on_homepage && $tag->logo_path === null) {
            $tag->update(['show_on_homepage' => false]);
        }

        return redirect()
            ->route('admin.tags.index')
            ->with('status', 'Tag created successfully.');
    }

    public function edit(Tag $tag): View
    {
        return view('admin.tags.edit', [
            'tag' => $tag,
        ]);
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');
        $validated['show_on_homepage'] = $request->boolean('show_on_homepage');
        unset($validated['logo'], $validated['remove_logo']);

        $tag->update($validated);

        // Handle logo removal
        if ($request->boolean('remove_logo') && $tag->logo_path !== null) {
            $this->deleteTagLogo($tag->logo_path);
            $tag->update(['logo_path' => null, 'show_on_homepage' => false]);
        }

        // Handle new logo upload
        if ($request->hasFile('logo') && in_array($tag->type, ['artist', 'brand'], true)) {
            if ($tag->logo_path !== null) {
                $this->deleteTagLogo($tag->logo_path);
            }

            $logoPath = $this->storeTagLogo($request->file('logo'), $tag->slug);

            if ($logoPath !== null) {
                $tag->update(['logo_path' => $logoPath]);
            }
        }

        // Ensure show_on_homepage is only set when a logo exists
        if ($tag->fresh()->show_on_homepage && $tag->fresh()->logo_path === null) {
            $tag->update(['show_on_homepage' => false]);
        }

        return redirect()
            ->route('admin.tags.index')
            ->with('status', 'Tag updated successfully.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        if ($tag->logo_path !== null) {
            $this->deleteTagLogo($tag->logo_path);
        }

        $tag->delete();

        return redirect()
            ->route('admin.tags.index')
            ->with('status', 'Tag deleted successfully.');
    }

    private function storeTagLogo(UploadedFile $file, string $slug): ?string
    {
        try {
            $directory = 'tags/'.$slug.'/logo';
            $baseFilename = $slug.'-logo';

            $fallbackDir = 'tags/'.$slug.'/fallback';
            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $fallbackPath = $file->storeAs($fallbackDir, $baseFilename.'.'.$extension, 'public');

            if ($fallbackPath === false) {
                return null;
            }

            $absoluteFallbackPath = storage_path('app/public/'.$fallbackPath);

            if (! is_dir(storage_path('app/public/'.$directory))) {
                mkdir(storage_path('app/public/'.$directory), 0755, true);
            }

            // Try AVIF first, then WebP
            $avifRelativePath = $directory.'/'.$baseFilename.'-thumbnail-200x200.avif';
            $webpRelativePath = $directory.'/'.$baseFilename.'-thumbnail-200x200.webp';

            $avifCreated = $this->convertTagLogoTo($absoluteFallbackPath, $avifRelativePath, 'avif', 200, 200);
            if ($avifCreated) {
                return $avifRelativePath;
            }

            $webpCreated = $this->convertTagLogoTo($absoluteFallbackPath, $webpRelativePath, 'webp', 200, 200);
            if ($webpCreated) {
                return $webpRelativePath;
            }

            // Fallback: store original in logo dir
            $originalPath = $directory.'/'.$baseFilename.'.'.$extension;
            Storage::disk('public')->copy($fallbackPath, $originalPath);

            return $originalPath;
        } catch (Throwable $exception) {
            Log::warning('Tag logo upload failed.', [
                'slug' => $slug,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function convertTagLogoTo(string $absoluteSourcePath, string $relativeTargetPath, string $format, int $width, int $height): bool
    {
        try {
            $absoluteTargetPath = storage_path('app/public/'.$relativeTargetPath);
            $targetDir = dirname($absoluteTargetPath);

            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            if (class_exists('Imagick')) {
                $imagick = new \Imagick($absoluteSourcePath);
                $imagick->cropThumbnailImage($width, $height);
                $imagick->setImageFormat($format);
                $imagick->setImageCompressionQuality($format === 'avif' ? 62 : 82);
                $imagick->stripImage();
                $saved = $imagick->writeImage($absoluteTargetPath);
                $imagick->clear();
                $imagick->destroy();

                return $saved && is_file($absoluteTargetPath);
            }

            if (! function_exists('imagecreatetruecolor')) {
                return false;
            }

            if ($format === 'avif' && ! function_exists('imageavif')) {
                return false;
            }

            if ($format === 'webp' && ! function_exists('imagewebp')) {
                return false;
            }

            $imageInfo = @getimagesize($absoluteSourcePath);
            if ($imageInfo === false) {
                return false;
            }

            $mime = strtolower((string) $imageInfo['mime']);
            $srcImage = match ($mime) {
                'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absoluteSourcePath) : false,
                'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absoluteSourcePath) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absoluteSourcePath) : false,
                'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($absoluteSourcePath) : false,
                default => false,
            };

            if ($srcImage === false) {
                return false;
            }

            $srcW = $imageInfo[0];
            $srcH = $imageInfo[1];
            $ratio = max($width / $srcW, $height / $srcH);
            $newW = (int) ($srcW * $ratio);
            $newH = (int) ($srcH * $ratio);
            $cropX = (int) (($newW - $width) / 2);
            $cropY = (int) (($newH - $height) / 2);

            $destImage = @imagecreatetruecolor($width, $height);
            if ($destImage === false) {
                @imagedestroy($srcImage);

                return false;
            }

            @imagealphablending($destImage, false);
            @imagesavealpha($destImage, true);

            $tempImage = @imagecreatetruecolor($newW, $newH);
            if ($tempImage !== false) {
                @imagecopyresampled($tempImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
                @imagecopy($destImage, $tempImage, 0, 0, $cropX, $cropY, $width, $height);
                @imagedestroy($tempImage);
            }

            $saved = match ($format) {
                'avif' => @imageavif($destImage, $absoluteTargetPath, 62),
                'webp' => @imagewebp($destImage, $absoluteTargetPath, 82),
                default => false,
            };

            @imagedestroy($srcImage);
            @imagedestroy($destImage);

            return $saved && is_file($absoluteTargetPath);
        } catch (Throwable $exception) {
            Log::warning('Tag logo conversion failed.', [
                'target' => $relativeTargetPath,
                'format' => $format,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function deleteTagLogo(string $logoPath): void
    {
        try {
            Storage::disk('public')->delete($logoPath);
        } catch (Throwable $exception) {
            Log::warning('Failed to delete tag logo file.', [
                'path' => $logoPath,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
