<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Eén bron voor de bedrijfsgegevens die de klant onder ogen krijgt: het
 * briefpapier van de offerte, de afzender van elke mail en de organisator van
 * de agenda-uitnodiging.
 *
 * De waarden lopen via de instellingen, zodat wat de ondernemer in het
 * dashboard invult ook daadwerkelijk op de offerte staat. De config (en
 * daarmee .env) is het vangnet.
 */
class CompanyProfile
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly PhoneNumber $phone,
    ) {}

    /**
     * @return array{
     *     name: string, email: string, phone: string, phone_link: string,
     *     website: string, website_label: string, address: string,
     *     postcode: string, city: string, kvk: string, vat_number: string,
     *     address_line: string, legal_line: string
     * }
     */
    public function all(): array
    {
        $phone = $this->value('phone');
        $website = $this->value('website');

        $addressLine = trim(implode(', ', array_filter([
            $this->value('address'),
            trim($this->value('postcode').' '.$this->value('city')),
        ])));

        $legal = array_filter([
            $this->value('name'),
            $addressLine,
            $this->value('kvk') === '' ? '' : 'KvK '.$this->value('kvk'),
            $this->value('vat_number') === '' ? '' : 'Btw '.$this->value('vat_number'),
        ]);

        return [
            'name' => $this->value('name'),
            'email' => $this->value('email'),
            // Weergave zoals de ondernemer het nummer zelf noteert; alleen de
            // klikbare variant wordt genormaliseerd, anders belt de telefoon
            // een spatie mee.
            'phone' => $phone,
            'phone_link' => $this->phone->normalise($phone) ?? preg_replace('/[^\d+]/', '', $phone) ?? '',
            'website' => $website,
            'website_label' => (string) preg_replace('#^https?://(www\.)?|/$#i', '', $website),
            'address' => $this->value('address'),
            'postcode' => $this->value('postcode'),
            'city' => $this->value('city'),
            'kvk' => $this->value('kvk'),
            'vat_number' => $this->value('vat_number'),
            'address_line' => $addressLine,
            'legal_line' => implode(' · ', $legal),
        ];
    }

    private function value(string $key): string
    {
        return trim($this->settings->string('agent.company.'.$key));
    }
}
