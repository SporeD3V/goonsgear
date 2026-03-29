<?php

namespace App\Models;

use Database\Factories\CartAbandonmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartAbandonment extends Model
{
    /** @use HasFactory<CartAbandonmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'cart_data',
        'token',
        'abandoned_at',
        'reminder_sent_at',
        'recovered_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cart_data' => 'array',
            'abandoned_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }
}
