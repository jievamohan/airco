<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailMessage;
use App\Models\Lead;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Verstuurt mail en legt elke verzending vast bij de lead, zodat het dashboard
 * precies laat zien wat er wanneer naar de klant is gegaan.
 */
class Mailer
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function send(?Lead $lead, string $to, string $template, Mailable $mailable): EmailMessage
    {
        $subject = $this->subjectOf($mailable);

        $message = EmailMessage::create([
            'lead_id' => $lead?->id,
            'direction' => 'outbound',
            'template' => $template,
            'to_address' => $to,
            'subject' => $subject,
            'status' => 'queued',
        ]);

        if ($this->settings->bool('agent.dry_run', false)) {
            $message->forceFill([
                'status' => 'skipped',
                'body_preview' => 'Proefmodus: mail is niet verzonden.',
            ])->save();

            return $message;
        }

        try {
            Mail::to($to)->send($mailable);
            $message->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        } catch (Throwable $e) {
            Log::error('Versturen van e-mail mislukt', ['template' => $template, 'exception' => $e->getMessage()]);
            $message->forceFill(['status' => 'failed', 'error_message' => 'De mailserver accepteerde het bericht niet.'])->save();
        }

        return $message;
    }

    private function subjectOf(Mailable $mailable): string
    {
        if (method_exists($mailable, 'envelope')) {
            /** @var object{subject?: string} $envelope */
            $envelope = $mailable->envelope();

            return (string) ($envelope->subject ?? '');
        }

        return '';
    }
}
