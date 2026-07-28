<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DailyLogShare extends Model
{
    protected $fillable = [
        'daily_log_id',
        'share_token',
        'approver_name',
        'approver_role',
        'verification_pin',
        'pin_sent_at',
        'is_email_verified',
        'status',
        'rejection_reason',
        'signature_data',
        'client_ip',
        'user_agent',
        'sha256_hash',
        'approved_at',
        'expires_at',
    ];

    protected $casts = [
        'pin_sent_at' => 'datetime',
        'is_email_verified' => 'boolean',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public static function createShareToken(DailyLog $log, string $role = 'Architekt', ?string $name = null): self
    {
        return self::create([
            'daily_log_id' => $log->id,
            'share_token' => Str::random(32),
            'approver_role' => $role,
            'approver_name' => $name,
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function generatePin(): string
    {
        $pin = sprintf('%06d', mt_rand(100000, 999999));
        $this->update([
            'verification_pin' => $pin,
            'pin_sent_at' => now(),
        ]);
        return $pin;
    }

    public function calculateDocumentHash(string $signatureData): string
    {
        $log = $this->dailyLog;
        $payload = implode('|', [
            $this->share_token,
            $this->approver_name,
            $this->approver_role,
            $log->id,
            $log->date,
            $log->work_performed,
            $signatureData,
            $this->approved_at?->timestamp ?? time(),
        ]);
        return hash('sha256', $payload);
    }
}
