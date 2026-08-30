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
        $company = $this->company();

        return $this->envelopeFor(sprintf('[%s] %s', $company['name'], $this->headline));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.owner-notification',
            text: 'mail.text.owner-notification',
            with: [
                'lead' => $this->lead,
                'headline' => $this->headline,
                'lines' => $this->lines,
                'company' => $this->company(),
            ],
        );
    }
}
