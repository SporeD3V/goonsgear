<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'invoice_number',
        'year',
        'sequence',
        'issued_at',
        'snapshot',
        'pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'sequence' => 'integer',
            'issued_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
