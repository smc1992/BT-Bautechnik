<?php

namespace App\Livewire;

use App\Models\DailyLogShare;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class PublicDailyLogApproval extends Component
{
    public string $token;
    public ?DailyLogShare $share = null;
    public string $approverName = '';
    public string $approverEmail = '';
    public string $inputPin = '';
    public bool $pinSent = false;
    public bool $isPinVerified = false;
    public string $pinMessage = '';
    public string $rejectionReason = '';
    public bool $isSubmitted = false;
    public bool $isRejected = false;

    public function mount(string $token)
    {
        $this->token = $token;
        $this->share = DailyLogShare::where('share_token', $token)->with('dailyLog.project')->firstOrFail();
        
        if ($this->share->approver_name) {
            $this->approverName = $this->share->approver_name;
        }

        if ($this->share->is_email_verified) {
            $this->isPinVerified = true;
        }
    }

    public function sendSecurityPin()
    {
        $this->validate([
            'approverName' => 'required|min:2',
            'approverEmail' => 'required|email',
        ], [
            'approverName.required' => 'Bitte geben Sie Ihren Namen an.',
            'approverEmail.required' => 'Bitte geben Sie Ihre E-Mail-Adresse für den Sicherheitscode an.',
            'approverEmail.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        ]);

        $pin = $this->share->generatePin();
        $this->share->update([
            'approver_name' => $this->approverName,
        ]);

        // Attempt to send email via Laravel Mailer
        try {
            Mail::raw("Guten Tag {$this->approverName},\n\nIhr 6-stelliger Sicherheitscode zur Freigabe des Bautagebuchs lautet:\n\n{$pin}\n\nDieser Code ist 15 Minuten gültig.\n\nMit freundlichen Grüßen,\nBT Bautechnik UG", function ($message) {
                $message->to($this->approverEmail)
                    ->subject('Ihr E-Mail-Sicherheitscode zur Bautagebuch-Freigabe');
            });
            $this->pinMessage = "📩 Sicherheitscode wurde an {$this->approverEmail} gesendet!";
        } catch (\Exception $e) {
            // Fallback info notice if SMTP is in dev log mode
            $this->pinMessage = "📩 Sicherheitscode wurde generiert: {$pin} (Test-Modus)";
        }

        $this->pinSent = true;
    }

    public function verifySecurityPin()
    {
        if (trim($this->inputPin) === $this->share->verification_pin) {
            $this->share->update(['is_email_verified' => true]);
            $this->isPinVerified = true;
            $this->pinMessage = '✓ E-Mail-Adresse erfolgreich per 2FA verifiziert!';
        } else {
            $this->addError('inputPin', 'Falscher Sicherheitscode. Bitte prüfen Sie Ihre E-Mails.');
        }
    }

    public function approveWithSignature(string $signature)
    {
        if (!$this->share || $this->share->status !== 'pending') {
            return;
        }

        if (!$this->isPinVerified) {
            $this->addError('approverEmail', 'Bitte verifizieren Sie zuerst Ihre E-Mail-Adresse mit dem Sicherheitscode.');
            return;
        }

        $this->validate([
            'approverName' => 'required|min:2',
        ]);

        $clientIp = request()->ip();
        $userAgent = request()->userAgent();
        $hash = $this->share->calculateDocumentHash($signature);

        $this->share->update([
            'status' => 'approved',
            'approver_name' => $this->approverName,
            'signature_data' => $signature,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'sha256_hash' => $hash,
            'approved_at' => now(),
        ]);

        $this->isSubmitted = true;
    }

    public function rejectShare()
    {
        if (!$this->share || $this->share->status !== 'pending') {
            return;
        }

        $this->validate([
            'approverName' => 'required|min:2',
            'rejectionReason' => 'required|min:5',
        ], [
            'approverName.required' => 'Bitte geben Sie Ihren Namen an.',
            'rejectionReason.required' => 'Bitte geben Sie den Grund der Ablehnung an.',
            'rejectionReason.min' => 'Der Grund muss mindestens 5 Zeichen enthalten.',
        ]);

        $this->share->update([
            'status' => 'rejected',
            'approver_name' => $this->approverName,
            'rejection_reason' => $this->rejectionReason,
            'client_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $this->isRejected = true;
    }

    public function render()
    {
        $settings = CompanySetting::getSettings();

        return view('livewire.public-daily-log-approval', [
            'log' => $this->share->dailyLog,
            'project' => $this->share->dailyLog->project,
            'settings' => $settings,
        ])->layout('layouts.public');
    }
}
