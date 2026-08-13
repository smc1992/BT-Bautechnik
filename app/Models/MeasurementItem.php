<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'measurement_id',
        'position_index',
        'item_code',
        'description',
        'unit',
        'length',
        'width',
        'height',
        'factor',
        'deduction',
        'quantity',
        'unit_price',
        'total_price',
        'room_or_axis',
    ];

    protected $casts = [
        'length' => 'decimal:3',
        'width' => 'decimal:3',
        'height' => 'decimal:3',
        'factor' => 'decimal:3',
        'deduction' => 'decimal:3',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }
}
