<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbandonedCartSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'is_enabled',
        'delay_minutes',
        'coupon_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'delay_minutes' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            [
                'is_enabled' => true,
                'delay_minutes' => 60,
                'coupon_code' => null,
            ]
        );
    }
    //
}
