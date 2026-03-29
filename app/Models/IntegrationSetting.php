<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    public static function value(string $name, ?string $default = null): ?string
    {
        $resolvedValue = self::query()
            ->where('name', $name)
            ->value('value');

        if (! is_string($resolvedValue) || trim($resolvedValue) === '') {
            return $default;
        }

        return $resolvedValue;
    }

    /**
     * @return array<string, ?string>
     */
    public static function allValues(): array
    {
        return self::query()
            ->orderBy('name')
            ->get(['name', 'value'])
            ->mapWithKeys(fn (self $setting): array => [$setting->name => $setting->value])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function putMany(array $values): void
    {
        foreach ($values as $name => $value) {
            $normalizedName = trim((string) $name);

            if ($normalizedName === '') {
                continue;
            }

            $normalizedValue = $value !== null ? trim((string) $value) : null;

            self::query()->updateOrCreate(
                ['name' => $normalizedName],
                ['value' => $normalizedValue !== '' ? $normalizedValue : null]
            );
        }
    }
}
