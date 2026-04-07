<?php

namespace App\Concerns;

use App\Models\EditHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEditHistory
{
    public function editHistories(): MorphMany
    {
        return $this->morphMany(EditHistory::class, 'editable');
    }
}
