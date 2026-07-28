<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'contact_id',
        'worker_name',
        'worker_type',
        'date',
        'shift_type',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function getShiftLabelAttribute(): string
    {
        return match ($this->shift_type) {
            'vormittags' => 'Vormittags (07-12 Uhr)',
            'nachmittags' => 'Nachmittags (12-17 Uhr)',
            default => 'Ganztags (07-17 Uhr)',
        };
    }
}
