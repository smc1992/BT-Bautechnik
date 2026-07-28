<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class InvoiceItemTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'unit',
        'unit_price',
        'vat_rate',
        'category',
    ];
}
