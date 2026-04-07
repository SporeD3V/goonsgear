<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EditHistory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'editable_type',
        'editable_id',
        'field',
        'old_value',
        'new_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record a field change and prune old entries.
     */
    public static function recordChange(Model $subject, string $field, mixed $oldValue, mixed $newValue): self
    {
        $entry = static::query()->create([
            'user_id' => auth()->id(),
            'editable_type' => $subject->getMorphClass(),
            'editable_id' => $subject->getKey(),
            'field' => $field,
            'old_value' => is_bool($oldValue) ? ($oldValue ? '1' : '0') : ($oldValue !== null ? (string) $oldValue : null),
            'new_value' => is_bool($newValue) ? ($newValue ? '1' : '0') : ($newValue !== null ? (string) $newValue : null),
        ]);

        // Prune: keep only last 10 per subject+field
        $idsToKeep = static::query()
            ->where('editable_type', $subject->getMorphClass())
            ->where('editable_id', $subject->getKey())
            ->where('field', $field)
            ->latest('id')
            ->take(10)
            ->pluck('id');

        static::query()
            ->where('editable_type', $subject->getMorphClass())
            ->where('editable_id', $subject->getKey())
            ->where('field', $field)
            ->whereNotIn('id', $idsToKeep)
            ->delete();

        return $entry;
    }

    /**
     * Get the most recent change for a subject+field.
     */
    public static function lastChange(Model $subject, string $field): ?self
    {
        return static::query()
            ->where('editable_type', $subject->getMorphClass())
            ->where('editable_id', $subject->getKey())
            ->where('field', $field)
            ->latest('id')
            ->first();
    }

    /**
     * Check if any history exists for a subject+field.
     */
    public static function hasHistory(Model $subject, string $field): bool
    {
        return static::query()
            ->where('editable_type', $subject->getMorphClass())
            ->where('editable_id', $subject->getKey())
            ->where('field', $field)
            ->exists();
    }

    /**
     * Get fields with history for a subject.
     *
     * @return list<string>
     */
    public static function fieldsWithHistory(Model $subject): array
    {
        return static::query()
            ->where('editable_type', $subject->getMorphClass())
            ->where('editable_id', $subject->getKey())
            ->distinct()
            ->pluck('field')
            ->all();
    }
}
