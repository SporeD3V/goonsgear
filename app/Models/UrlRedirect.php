<?php

namespace App\Models;

use Database\Factories\UrlRedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UrlRedirect extends Model
{
    /** @use HasFactory<UrlRedirectFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'from_path',
        'to_url',
        'status_code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function normalizePath(string $path): string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '' || $trimmedPath === '/') {
            return '/';
        }

        $pathWithoutQuery = (string) strtok($trimmedPath, '?');
        $normalizedPath = '/'.ltrim($pathWithoutQuery, '/');

        return rtrim($normalizedPath, '/') ?: '/';
    }
}
