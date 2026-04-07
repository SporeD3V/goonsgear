<?php

namespace App\Concerns;

use App\Models\AdminActivityLog;
use App\Models\EditHistory;
use Illuminate\Database\Eloquent\Model;

trait TracksAdminChanges
{
    /**
     * Log an admin activity.
     *
     * @param  array<string, mixed>|null  $properties
     */
    protected function logActivity(string $action, Model $subject, string $description, ?array $properties = null): void
    {
        AdminActivityLog::log($action, $subject, $description, $properties);
    }

    /**
     * Record edit history for changed fields on an update.
     *
     * @param  array<string, mixed>  $newValues
     * @param  list<string>  $trackedFields
     */
    protected function recordFieldChanges(Model $subject, array $newValues, array $trackedFields): void
    {
        foreach ($trackedFields as $field) {
            if (! array_key_exists($field, $newValues)) {
                continue;
            }

            $oldValue = $subject->getOriginal($field);
            $newValue = $newValues[$field];

            // Normalize for comparison
            $oldStr = is_bool($oldValue) ? ($oldValue ? '1' : '0') : (string) ($oldValue ?? '');
            $newStr = is_bool($newValue) ? ($newValue ? '1' : '0') : (string) ($newValue ?? '');

            if ($oldStr !== $newStr) {
                EditHistory::recordChange($subject, $field, $oldValue, $newValue);
            }
        }
    }
}
