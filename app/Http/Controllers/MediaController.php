<?php

namespace App\Http\Controllers;

class MediaController extends Controller
{
    public function show(string $path)
    {
        $normalizedPath = ltrim($path, '/');

        if (str_contains($normalizedPath, '..')) {
            abort(404);
        }

        $resolvedPath = $this->resolveBestImagePathForClient($normalizedPath);
        $absolutePath = storage_path('app/public/'.$resolvedPath);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    private function resolveBestImagePathForClient(string $normalizedPath): string
    {
        $absolutePath = storage_path('app/public/'.$normalizedPath);

        if (! is_file($absolutePath)) {
            return $normalizedPath;
        }

        $pathInfo = pathinfo($normalizedPath);
        $directory = $pathInfo['dirname'] ?? '.';
        $filename = $pathInfo['filename'] ?? '';

        if ($filename === '') {
            return $normalizedPath;
        }

        $baseRelativePath = ($directory === '.' ? '' : $directory.'/').$filename;
        $avifRelativePath = $baseRelativePath.'.avif';
        $webpRelativePath = $baseRelativePath.'.webp';
        $acceptHeader = strtolower((string) request()->header('Accept', ''));

        if (str_contains($acceptHeader, 'image/avif') && is_file(storage_path('app/public/'.$avifRelativePath))) {
            return $avifRelativePath;
        }

        if (str_contains($acceptHeader, 'image/webp') && is_file(storage_path('app/public/'.$webpRelativePath))) {
            return $webpRelativePath;
        }

        return $normalizedPath;
    }
}
