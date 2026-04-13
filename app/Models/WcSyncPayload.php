<?php

namespace App\Models;

use Database\Factories\WcSyncPayloadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WcSyncPayload extends Model
{
    /** @use HasFactory<WcSyncPayloadFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'event',
        'wc_entity_type',
        'wc_entity_id',
        'payload',
        'received_at',
        'processed_at',
        'processing_error',
        'attempts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'wc_entity_id' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function markProcessed(): void
    {
        $this->update([
            'processed_at' => now(),
            'processing_error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'attempts' => $this->attempts + 1,
            'processing_error' => mb_substr($error, 0, 255),
        ]);
    }
}
