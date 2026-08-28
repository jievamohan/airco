<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\OwnerNotificationMail;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Quote;

/**
 * Alle notificaties naar de ondernemer lopen hierlangs, zodat er één plek is
 * waar bepaald wordt wat gemeld wordt en hoe dat in de timeline landt.
 */
class Notifier
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly LeadTimeline $timeline,
        private readonly SettingsRepository $settings,
    ) {}

    public function leadReceived(Lead $lead): void
    {
        $this->send($lead, 'Nieuwe aanvraag: '.$lead->name, [
            'Er is een nieuwe aanvraag binnengekomen via '.$this->sourceLabel($lead).'.',
            'De agent belt de klant zodra het belvenster open is.',
        ]);
    }

    public function leadQualified(Lead $lead): void
    {
        $this->send($lead, 'Gesprek gevoerd: '.$lead->name, [
            'De voice agent heeft de klant gesproken en de ontbrekende gegevens opgehaald.',
            'De offerte wordt nu opgesteld en verstuurd.',
        ]);
    }

    public function quoteSent(Lead $lead, Quote $quote): void
    {
        $this->send($lead, 'Offerte verstuurd: '.$lead->name, [
            sprintf('Offerte %s is gemaild: € %s incl. btw.', $quote->number, number_format($quote->total_cents / 100, 2, ',', '.')),
            sprintf('Geschatte montageduur op locatie: %s uur.', number_format($quote->onsite_minutes / 60, 1, ',', '.')),
            sprintf('Over %d minuten belt de agent na om de opdracht rond te maken.', $this->settings->int('agent.workflow.conversion_call_delay_minutes', 60)),
        ]);
    }

    public function appointmentBooked(Lead $lead, Appointment $appointment): void
    {
        $this->send($lead, 'Afspraak ingepland: '.$lead->name, [
            sprintf('De installatie staat gepland op %s.', $appointment->starts_at->timezone($appointment->timezone)->format('l j F Y \o\m H:i')),
            sprintf('Locatie: %s.', $appointment->location ?? $lead->displayLocation()),
            $appointment->provider === 'none'
                ? 'Er is nog geen agendakoppeling actief; de afspraak staat alleen in het dashboard.'
                : sprintf('De afspraak is toegevoegd aan de %s-agenda.', $appointment->provider === 'google' ? 'Google' : 'Apple'),
        ]);
    }

    public function leadUnreachable(Lead $lead): void
    {
        $this->send($lead, 'Geen contact gekregen: '.$lead->name, [
            'Alle bel- en mailpogingen zijn uitgevoerd zonder reactie.',
            'De lead staat nu op "onbereikbaar" en wordt niet meer automatisch benaderd.',
        ]);
    }

    public function leadLost(Lead $lead, string $reason): void
    {
        $this->send($lead, 'Lead verloren: '.$lead->name, [
            'Reden: '.$reason,
        ]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function send(Lead $lead, string $subject, array $lines): void
    {
        $to = $this->settings->string('agent.owner.email', (string) config('agent.owner.email'));

        if ($to === '') {
            return;
        }

        $this->mailer->send($lead, $to, 'owner_notification', new OwnerNotificationMail($lead, $subject, $lines));

        $lead->forceFill(['owner_notified_at' => now()])->save();

        $this->timeline->record($lead, 'owner_notified', 'Ondernemer geïnformeerd', $subject, ['to' => $to]);
    }

    private function sourceLabel(Lead $lead): string
    {
        return match ($lead->source) {
            'mailbox' => 'de mailbox',
            'web_form' => 'het formulier op de website',
            'api' => 'een externe koppeling',
            default => 'handmatige invoer',
        };
    }
}
