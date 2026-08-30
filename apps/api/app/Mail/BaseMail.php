<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\CompanyProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

abstract class BaseMail extends Mailable
{
    use Queueable;

    /**
     * De bedrijfsgegevens zoals de klant ze in elke mail terugziet. Worden bij
     * het renderen opgehaald en niet in de constructor: een mail die in de
     * wachtrij staat, hoort met de actuele gegevens de deur uit te gaan.
     *
     * @return array<string, string>
     */
    protected function company(): array
    {
        return app(CompanyProfile::class)->all();
    }

    /**
     * Afzendernaam en antwoordadres volgen de bedrijfsgegevens, zodat een
     * naamswijziging in het dashboard ook in de inbox van de klant landt. Het
     * afzenderadres blijft dat van de mailserver: daar staat SPF op ingericht.
     */
    protected function envelopeFor(string $subject): Envelope
    {
        $company = $this->company();
        $from = (string) config('mail.from.address');

        return new Envelope(
            from: $from === '' || $company['name'] === '' ? null : new Address($from, $company['name']),
            replyTo: $company['email'] === '' ? [] : [new Address($company['email'], $company['name'])],
            subject: $subject,
        );
    }
}
