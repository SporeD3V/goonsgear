<?php

namespace App\Models;

use Database\Factories\WcSyncPayloadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WcSyncPayload extends Model
{
    /** @use HasFactory<WcSyncPayloadFactory> */
    use HasFactory;

    /**
     * Failures past this point stop being retried, so one poisoned payload
     * cannot consume every processor run. Clear it with resetForRetry().
     */
    public const MAX_ATTEMPTS = 5;

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

    /**
     * Retire a payload without running it. The error is kept so the row still
     * explains why it never processed — that is what separates a dismissal
     * from a genuine success, which always clears the error.
     */
    public function markDismissed(): void
    {
        $this->update([
            'processed_at' => now(),
            'processing_error' => $this->processing_error ?: 'Dismissed manually without processing.',
        ]);
    }

    public function resetForRetry(): void
    {
        $this->update([
            'processed_at' => null,
            'processing_error' => null,
            'attempts' => 0,
        ]);
    }

    public function isDismissed(): bool
    {
        return $this->processed_at !== null && $this->processing_error !== null;
    }

    /**
     * Failed too often to be picked up again without a manual retry.
     */
    public function isExhausted(): bool
    {
        return $this->processed_at === null && $this->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereNull('processed_at')->where('attempts', '>', 0);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->whereNull('processed_at')->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExhausted(Builder $query): Builder
    {
        return $query->whereNull('processed_at')->where('attempts', '>=', self::MAX_ATTEMPTS);
    }
}
