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
        'customer_number',
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

    protected static function booted(): void
    {
        static::creating(function (Contact $contact) {
            if (empty($contact->customer_number)) {
                $contact->customer_number = static::generateNextCustomerNumber();
            }
        });
    }

    public static function generateNextCustomerNumber(): string
    {
        $year = date('Y');
        $prefix = "KD-{$year}-";
        $last = static::where('customer_number', 'like', "{$prefix}%")
            ->orderBy('customer_number', 'desc')
            ->first();

        if ($last && preg_match('/KD-\d{4}-(\d+)/', $last->customer_number, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function actualCosts(): HasMany
    {
        return $this->hasMany(ActualCost::class);
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
