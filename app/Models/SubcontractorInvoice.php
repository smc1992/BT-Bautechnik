<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractorInvoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'contact_id',
        'invoice_number',
        'invoice_date',
        'amount_net',
        'tax_mode',
        'status',
        'description',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'paid' => 'bg-blue-50 text-blue-700 border-blue-200',
            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
            default => 'bg-amber-50 text-amber-800 border-amber-300',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'Freigegeben',
            'paid' => 'Bezahlt',
            'rejected' => 'Abgelehnt',
            default => 'In Prüfung',
        };
    }
}
