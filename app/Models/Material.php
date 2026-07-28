<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit',
        'unit_price',
        'supplier',
        'notes',
        'last_price_update',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'last_price_update' => 'datetime',
    ];
}
