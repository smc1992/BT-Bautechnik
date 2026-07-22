<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Defect extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'assigned_contact_id',
        'title',
        'location',
        'description',
        'deadline',
        'priority',
        'status',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'assigned_contact_id');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'in_bearbeitung' => 'bg-blue-50 text-blue-700 border-blue-200',
            'behoben' => 'bg-amber-50 text-amber-800 border-amber-300',
            'abgenommen' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            default => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }

    public function getPriorityBadgeClassAttribute(): string
    {
        return match ($this->priority) {
            'kritisch' => 'bg-rose-100 text-rose-900 border-rose-300 font-extrabold',
            'hoch' => 'bg-amber-100 text-amber-900 border-amber-300 font-bold',
            'niedrig' => 'bg-slate-100 text-slate-700 border-slate-200',
            default => 'bg-blue-50 text-blue-700 border-blue-200',
        };
    }
}
