<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyVisit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'visitor_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
