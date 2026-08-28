<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerNotificationMail extends BaseMail
{
    use SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly Lead $lead,
        public readonly string $headline,
        public readonly array $lines,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[KlimaatX] '.$this->headline);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.owner-notification',
            with: [
                'lead' => $this->lead,
                'headline' => $this->headline,
                'lines' => $this->lines,
            ],
        );
    }
}
