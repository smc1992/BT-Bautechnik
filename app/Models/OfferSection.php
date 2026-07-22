<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferSection extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfferItem::class);
    }
}
