<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function actualCosts(): HasMany
    {
        return $this->hasMany(ActualCost::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class)->orderBy('created_at', 'desc');
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(DailyLog::class)->orderBy('date', 'asc');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class)->orderBy('created_at', 'desc');
    }

    public function supplements(): HasMany
    {
        return $this->hasMany(Supplement::class)->orderBy('created_at', 'desc');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class)->orderBy('measurement_date', 'desc');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderBy('entry_date', 'desc');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ProjectPlan::class)->orderBy('created_at', 'desc');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'current_project_id');
    }
}
