<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplement extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'supplement_number',
        'title',
        'description',
        'reason',
        'amount_net',
        'vat_rate',
        'amount_gross',
        'status',
        'submission_date',
        'approval_date',
        'created_by',
        'attachments',
        'notes',
    ];

    protected $casts = [
        'amount_net' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'amount_gross' => 'decimal:2',
        'submission_date' => 'date',
        'approval_date' => 'date',
        'attachments' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
