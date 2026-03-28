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

        $absolutePath = storage_path('app/public/'.$normalizedPath);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
