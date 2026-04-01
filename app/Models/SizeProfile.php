<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'is_self',
        'top_size',
        'bottom_size',
        'shoe_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_self' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All sizes present on this profile as a flat array.
     *
     * @return array<int, string>
     */
    public function allSizes(): array
    {
        return array_values(array_filter([
            $this->top_size,
            $this->bottom_size,
            $this->shoe_size,
        ], fn ($v) => $v !== null && $v !== ''));
    }
}
