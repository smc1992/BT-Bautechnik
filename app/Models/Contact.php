<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'company_name',
        'salutation',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'street',
        'zip',
        'city',
        'vat_id',
        'notes',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->company_name)) {
            return $this->company_name . ($this->last_name ? ' (' . $this->last_name . ')' : '');
        }

        return trim(($this->salutation ? $this->salutation . ' ' : '') . $this->first_name . ' ' . $this->last_name);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'hausverwaltung' => 'Hausverwaltung',
            'bautraeger' => 'Bauträger',
            'subunternehmer' => 'Subunternehmer',
            default => 'Privatkunde',
        };
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return match ($this->type) {
            'hausverwaltung' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'bautraeger' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            'subunternehmer' => 'bg-purple-50 text-purple-700 border-purple-200',
            default => 'bg-blue-50 text-blue-700 border-blue-200',
        };
    }
}
