<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Measurement extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'measurement_number',
        'title',
        'measurement_date',
        'location_area',
        'status',
        'total_amount_net',
        'inspector_name',
        'client_representative',
        'notes',
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'total_amount_net' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MeasurementItem::class)->orderBy('position_index');
    }
}
