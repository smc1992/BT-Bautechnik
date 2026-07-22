<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'date',
        'weather',
        'temperature',
        'workers_count',
        'work_performed',
        'special_occurrences',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
