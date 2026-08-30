<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Appointment;
use App\Models\Lead;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentMail extends BaseMail
{
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly Appointment $appointment,
        private readonly string $ics,
    ) {}

    public function envelope(): Envelope
    {
        $start = $this->appointment->starts_at->timezone($this->appointment->timezone);

        return $this->envelopeFor(sprintf('Uw installatieafspraak op %s', $start->translatedFormat('l j F')));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.appointment',
            text: 'mail.text.appointment',
            with: [
                'lead' => $this->lead,
                'appointment' => $this->appointment,
                'company' => $this->company(),
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->ics, 'afspraak.ics')
                ->withMime('text/calendar'),
        ];
    }
}
