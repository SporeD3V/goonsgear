<?php

namespace App\Models;

use Database\Factories\TagFollowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagFollow extends Model
{
    /** @use HasFactory<TagFollowFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tag_id',
        'notify_new_drops',
        'notify_discounts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notify_new_drops' => 'boolean',
            'notify_discounts' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
