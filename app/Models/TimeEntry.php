<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'project_id',
        'entry_date',
        'start_time',
        'end_time',
        'break_minutes',
        'hours',
        'activity_type',
        'trade',
        'description',
        'status',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'hours' => 'decimal:2',
        'break_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
