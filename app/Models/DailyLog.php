<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'contact_id',
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DailyLogShare::class);
    }
}
