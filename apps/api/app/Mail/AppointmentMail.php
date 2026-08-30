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
        return new Envelope(subject: $this->appointment->kind === 'survey'
            ? 'Bevestiging van de opname bij u thuis'
            : 'Bevestiging van uw installatieafspraak');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.appointment',
            with: [
                'lead' => $this->lead,
                'appointment' => $this->appointment,
                'company' => config('agent.company'),
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
