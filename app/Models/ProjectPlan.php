<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'plan_number',
        'title',
        'category',
        'revision_index',
        'file_path',
        'file_name',
        'file_size',
        'file_mime',
        'plan_date',
        'uploaded_by',
        'notes',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'file_size' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
