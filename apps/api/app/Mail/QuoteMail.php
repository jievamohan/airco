<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\Tier;
use App\Models\Lead;
use App\Models\Quote;
use App\Services\QuoteBuilder;
use App\Support\Money;
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
        return $this->envelopeFor(sprintf(
            $this->quote->isBinding()
                ? 'Uw offerte voor airconditioning — %s'
                : 'Uw vrijblijvende prijsindicatie voor airconditioning — %s',
            $this->quote->number,
        ));
    }

    public function content(): Content
    {
        return new Content(
            view: $this->quote->isBinding() ? 'mail.quote' : 'mail.indication',
            text: $this->quote->isBinding() ? 'mail.text.quote' : 'mail.text.indication',
            with: [
                'lead' => $this->lead,
                'quote' => $this->quote,
                'items' => $this->quote->items,
                'company' => $this->company(),
                'klasse' => $this->gekozenKlasse(),
                'alternatieven' => $this->alternatieven(),
            ],
        );
    }

    /**
     * Wat dezelfde klus in een andere kwaliteitsklasse kost.
     *
     * Alleen bij de prijsindicatie: dat is het eerste moment waarop er een
     * bedrag ligt, en het enige moment waarop de klant rustig kan kiezen. De
     * offerte na de opname is een aanbod; daar hoort geen keuzemenu bij.
     *
     * @return list<string>
     */
    private function alternatieven(): array
    {
        if ($this->quote->isBinding()) {
            return [];
        }

        try {
            $alternatieven = app(QuoteBuilder::class)->alternatives($this->lead, $this->gekozenKlasse());
        } catch (\RuntimeException) {
            // Een onvolledige catalogus mag de mail niet tegenhouden.
            return [];
        }

        // Als kant-en-klare regels, want dit staat in twee sjablonen — html en
        // platte tekst — en die horen niet allebei hun eigen zinnetje te maken.
        return array_map(static fn (array $a): string => sprintf(
            '%s — %s %s (%s)',
            $a['tier']->label(),
            Money::euroRound(abs($a['verschil_cents'])),
            $a['verschil_cents'] < 0 ? 'minder' : 'meer',
            $a['merk'],
        ), $alternatieven);
    }

    /** De kolom bewaart de klasse als string, niet als enum. */
    private function gekozenKlasse(): Tier
    {
        return Tier::tryFrom((string) $this->quote->tier) ?? Tier::Mid;
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
