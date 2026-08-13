<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    use HasUuids;

    protected $table = 'equipment';

    protected $fillable = [
        'inventory_number',
        'name',
        'category',
        'manufacturer',
        'model',
        'serial_number',
        'current_project_id',
        'status',
        'purchase_date',
        'purchase_price',
        'next_uvv_inspection',
        'next_tuev_inspection',
        'photo_path',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'next_uvv_inspection' => 'date',
        'next_tuev_inspection' => 'date',
    ];

    public function currentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'current_project_id');
    }
}
