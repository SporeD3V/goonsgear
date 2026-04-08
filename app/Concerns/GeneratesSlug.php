<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait GeneratesSlug
{
    public static function bootGeneratesSlug(): void
    {
        static::creating(function ($model): void {
            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = static::generateUniqueSlug($model->name, $model);
            }
        });

        static::updating(function ($model): void {
            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = static::generateUniqueSlug($model->name, $model);
            }
        });
    }

    private static function generateUniqueSlug(string $name, $model): string
    {
        $slug = Str::slug($name);

        $query = static::where('slug', $slug);

        if ($model->exists) {
            $query->where('id', '!=', $model->id);
        }

        if (! $query->exists()) {
            return $slug;
        }

        $suffix = 2;

        while (static::where('slug', "{$slug}-{$suffix}")->exists()) {
            $suffix++;
        }

        return "{$slug}-{$suffix}";
    }
}
