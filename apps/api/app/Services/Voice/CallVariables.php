<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Enums\CallPurpose;
use App\Models\Lead;
use App\Models\Quote;
use App\Services\SettingsRepository;

/**
 * Stelt de dynamische variabelen samen die de voice agent tijdens het gesprek
 * gebruikt. Alles is platte tekst: de agentprompt staat bij ElevenLabs en
 * verwijst met {{variabele}} naar deze sleutels.
 */
class CallVariables
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array<string, string>
     */
    public function build(Lead $lead, CallPurpose $purpose, ?Quote $quote = null): array
    {
        $bedrijf = $this->settings->string('agent.company.name', 'KlimaatX');

        $variables = [
            'gespreksdoel' => $purpose->objective(),
            'gesprekstype' => $purpose->value,
            'gespreksopening' => $this->opening($purpose, $bedrijf),
            'bedrijfsnaam' => $bedrijf,
            'bedrijf_telefoon' => $this->settings->string('agent.company.phone', ''),
            'klant_naam' => $lead->name,
            'klant_voornaam' => $this->firstName($lead->name),
            'klant_adres' => $lead->displayLocation(),
            'klant_email' => (string) $lead->email,
            'ruimte_omschrijving' => $this->spaceDescription($lead),
            'aantal_ruimtes' => (string) max(1, (int) $lead->rooms_count),
            'geadviseerd_systeem' => $lead->recommended_system?->label() ?? 'nog te bepalen',
            'geadviseerd_vermogen' => $lead->estimated_kw !== null
                ? number_format((float) $lead->estimated_kw, 1, ',', '.').' kW'
                : 'nog te bepalen',
            'gewenste_startdatum' => $lead->desired_start?->format('d-m-Y') ?? 'nog niet opgegeven',
            'opmerkingen_klant' => (string) $lead->notes,
            'ontbrekende_gegevens' => $this->missingFields($lead),
            'belpoging' => (string) ($lead->call_attempts + 1),
            'vanaf_prijs' => $this->euro($this->settings->int('agent.pricing.entry_price_cents', 89900)),
            'vanaf_prijs_dekking' => $this->settings->bool('agent.pricing.entry_package_enabled', false)
                ? 'Die vanaf-prijs geldt voor een eenvoudige installatie van de kleinste unit op de begane grond, inclusief montage en tot vijf meter leiding.'
                : 'Die vanaf-prijs geldt voor het apparaat; de montage wordt apart berekend en hangt af van de situatie ter plaatse.',
        ];

        if ($quote !== null) {
            $variables['offerte_nummer'] = $quote->number;
            $variables['offerte_bedrag'] = $this->euro($quote->total_cents);
            $variables['offerte_bedrag_excl_btw'] = $this->euro($quote->subtotal_cents);
            $variables['offerte_geldig_tot'] = $quote->valid_until?->format('d-m-Y') ?? '';
            $variables['montageduur'] = $this->duration($quote->onsite_minutes);
            $variables['korting_bij_direct_akkoord'] = $this->euro(
                min(
                    (int) round($quote->subtotal_cents * $this->settings->float('agent.pricing.direct_agreement_discount_pct', 3.0) / 100),
                    $this->settings->int('agent.pricing.direct_agreement_discount_max_cents', 15000),
                ),
            );
        }

        return array_map(static fn (string $value): string => $value === '' ? 'onbekend' : $value, $variables);
    }

    /**
     * De eerste zin van het gesprek. Die verschilt per gesprekstype, en de
     * voice agent kan daar zelf niet op sturen: zijn openingsbericht staat vast
     * bij de provider. Vandaar dat wij hem meegeven.
     *
     * Bevat bewust de melding dat het om een digitale assistent gaat en dat er
     * wordt opgenomen; dat hoort in de eerste zin, niet pas op navraag.
     */
    private function opening(CallPurpose $purpose, string $bedrijf): string
    {
        $aanhef = sprintf(
            'Goedendag, u spreekt met de digitale assistent van %s. Dit gesprek wordt opgenomen.',
            $bedrijf,
        );

        $reden = match ($purpose) {
            CallPurpose::Qualification => 'Ik bel over uw aanvraag voor airconditioning. Heeft u een paar minuten?',
            CallPurpose::Conversion => 'Ik bel over de offerte die u van ons heeft ontvangen. Schikt het u nu even?',
            CallPurpose::Chase => 'Ik probeer u te bereiken over uw aanvraag voor airconditioning. Komt het nu uit?',
            CallPurpose::Final => 'Ik bel een laatste keer over uw aanvraag voor airconditioning. Heeft u even?',
        };

        return $aanhef.' '.$reden;
    }

    private function missingFields(Lead $lead): string
    {
        $missing = [];

        if ($lead->space_size === null) {
            $missing[] = 'de afmeting van de ruimte';
        }

        if ($lead->rooms_count <= 1 && $lead->notes === null) {
            $missing[] = 'of er meerdere ruimtes gekoeld moeten worden';
        }

        if ($lead->building_year === null && $lead->insulation === null) {
            $missing[] = 'het bouwjaar of de isolatiekwaliteit van de woning';
        }

        if ($lead->floor_level === null) {
            $missing[] = 'op welke verdieping de binnenunit komt';
        }

        if ($lead->outdoor_unit_placement === null) {
            $missing[] = 'waar de buitenunit geplaatst kan worden';
        }

        if ($lead->pipe_length_m === null) {
            $missing[] = 'de geschatte afstand tussen binnen- en buitenunit';
        }

        if ($lead->desired_start === null) {
            $missing[] = 'wanneer de installatie ongeveer gewenst is';
        }

        return $missing === [] ? 'geen' : implode('; ', $missing);
    }

    private function spaceDescription(Lead $lead): string
    {
        if ($lead->space_size === null) {
            return 'niet opgegeven';
        }

        return sprintf(
            '%s %s',
            rtrim(rtrim(number_format((float) $lead->space_size, 1, ',', '.'), '0'), ','),
            $lead->space_unit === 'm3' ? 'm³' : 'm²',
        );
    }

    private function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? $name;
    }

    private function euro(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }

    private function duration(int $minutes): string
    {
        $hours = $minutes / 60;

        return rtrim(rtrim(number_format($hours, 1, ',', '.'), '0'), ',').' uur';
    }
}
