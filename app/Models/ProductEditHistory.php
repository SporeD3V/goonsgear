<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEditHistory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
