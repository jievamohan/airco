<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use App\Models\Quote;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteMail extends BaseMail
{
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly Quote $quote,
        private readonly ?string $pdf = null,
    ) {}

    public function envelope(): Envelope
    {
        return $this->envelopeFor(sprintf('Uw offerte voor airconditioning — %s', $this->quote->number));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.quote',
            text: 'mail.text.quote',
            with: [
                'lead' => $this->lead,
                'quote' => $this->quote,
                'items' => $this->quote->items,
                'company' => $this->company(),
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if ($this->pdf === null) {
            return [];
        }

        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->quote->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
