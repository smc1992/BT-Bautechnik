<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentChatMessage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'tools' => 'array',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AgentChat::class, 'agent_chat_id');
    }
}
