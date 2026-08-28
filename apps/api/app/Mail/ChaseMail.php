<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use App\Models\Quote;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De opvolgmails uit de chase-cadans. Welke variant verstuurd wordt, bepaalt
 * de sequence-stap; de tekst per variant staat in resources/views/mail/chase.
 */
class ChaseMail extends BaseMail
{
    use SerializesModels;

    private const SUBJECTS = [
        'missed_call' => 'We hebben u geprobeerd te bellen',
        'quote_without_call' => 'Uw vrijblijvende prijsindicatie voor airconditioning',
        'last_chance' => 'Laatste bericht over uw aanvraag',
    ];

    public function __construct(
        public readonly Lead $lead,
        public readonly string $variant,
        public readonly ?Quote $quote = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECTS[$this->variant] ?? 'Over uw aanvraag bij KlimaatX');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.chase',
            with: [
                'lead' => $this->lead,
                'variant' => $this->variant,
                'quote' => $this->quote,
                'company' => config('agent.company'),
            ],
        );
    }
}
