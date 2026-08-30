<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Enums\CallPurpose;
use App\Enums\QuoteKind;
use App\Enums\Tier;
use App\Models\Lead;
use App\Models\Quote;
use App\Services\QuoteBuilder;
use App\Services\SettingsRepository;
use App\Support\Money;
use RuntimeException;

/**
 * Stelt de dynamische variabelen samen die de voice agent tijdens het gesprek
 * gebruikt. Alles is platte tekst: de agentprompt staat bij ElevenLabs en
 * verwijst met {{variabele}} naar deze sleutels.
 */
class CallVariables
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly QuoteBuilder $quotes,
    ) {}

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
            'opname_duur' => $this->minutes(max(15, $this->settings->int('agent.calendar.survey_minutes', 45))),
            'prijs_voorbehoud' => 'Een prijsindicatie is een richtbedrag; de prijs staat pas vast in de offerte die na de opname ter plaatse volgt.',
            'vanaf_prijs_dekking' => $this->settings->bool('agent.pricing.entry_package_enabled', false)
                ? 'Die vanaf-prijs geldt voor een eenvoudige installatie van de kleinste unit op de begane grond, inclusief montage en tot vijf meter leiding.'
                : 'Die vanaf-prijs geldt voor het apparaat; de montage wordt apart berekend en hangt af van de situatie ter plaatse.',
        ];

        if ($quote !== null) {
            // Twee documenten, twee sets variabelen. De agent hoort bij een
            // conversiegesprek het richtbedrag te noemen en bij een
            // afsluitgesprek het bedrag uit de offerte; door ze uit elkaar te
            // houden kan hij ze niet verwisselen.
            $indicatie = $quote->isBinding() ? $this->laatste($lead, QuoteKind::Indication) : $quote;
            $offerte = $quote->isBinding() ? $quote : $this->laatste($lead, QuoteKind::Final, true);

            $variables += $this->documentVariables('indicatie', $indicatie);
            $variables += $this->documentVariables('offerte', $offerte);

            $variables['montageduur'] = $this->duration($quote->onsite_minutes);
            $variables['uitvoeringen'] = $this->uitvoeringen($lead, $quote);
            // De korting hoort bij het bedrag dat de klant ook echt kan
            // aanvaarden; ligt er nog geen offerte, dan rekenen we hem uit over
            // wat er nu ligt zodat de agent nooit met een leeg getal staat.
            $kortingBasis = $offerte instanceof Quote ? $offerte->subtotal_cents : $quote->subtotal_cents;

            $variables['korting_bij_direct_akkoord'] = $this->euro(
                min(
                    (int) round($kortingBasis * $this->settings->float('agent.pricing.direct_agreement_discount_pct', 3.0) / 100),
                    $this->settings->int('agent.pricing.direct_agreement_discount_max_cents', 15000),
                ),
            );
        }

        return array_map(static fn (string $value): string => $value === '' ? 'onbekend' : $value, $variables);
    }

    /**
     * Wat dezelfde klus in een andere kwaliteitsklasse kost, als één zin die
     * de agent kan uitspreken.
     *
     * "Te duur" is aan de telefoon zelden een nee; vaak is het een vraag naar
     * een lichtere uitvoering. Dat antwoord is beter dan korting: de marge
     * blijft staan en de klant krijgt een echte keuze.
     */
    private function uitvoeringen(Lead $lead, Quote $quote): string
    {
        try {
            $alternatieven = $this->quotes->alternatives($lead, Tier::tryFrom((string) $quote->tier) ?? Tier::Mid);
        } catch (RuntimeException) {
            $alternatieven = [];
        }

        if ($alternatieven === []) {
            return 'geen andere uitvoering beschikbaar';
        }

        return implode('; ', array_map(static fn (array $a): string => sprintf(
            '%s, %s, ongeveer %s %s',
            $a['tier']->label(),
            $a['merk'],
            Money::euroRound(abs($a['verschil_cents'])),
            $a['verschil_cents'] < 0 ? 'goedkoper' : 'duurder',
        ), $alternatieven));
    }

    /**
     * De laatst opgestelde prijsindicatie of offerte van deze lead.
     */
    private function laatste(Lead $lead, QuoteKind $kind, bool $alleenVerstuurd = false): ?Quote
    {
        $query = $lead->quotes()->where('kind', $kind->value);

        if ($alleenVerstuurd) {
            $query->whereNotNull('sent_at');
        }

        return $query->latest('id')->first();
    }

    /**
     * De variabelen van één document. Ontbreekt het, dan zegt de waarde dat
     * ook — de agent krijgt liever "nog niet verstuurd" te lezen dan een leeg
     * veld waar hij zelf iets van maakt.
     *
     * @return array<string, string>
     */
    private function documentVariables(string $prefix, ?Quote $document): array
    {
        if ($document === null) {
            return [
                $prefix.'_nummer' => 'nog niet verstuurd',
                $prefix.'_bedrag' => 'nog niet bekend',
                $prefix.'_bedrag_excl_btw' => 'nog niet bekend',
                $prefix.'_geldig_tot' => 'nog niet bekend',
            ];
        }

        return [
            $prefix.'_nummer' => $document->number,
            $prefix.'_bedrag' => $this->euro($document->total_cents),
            $prefix.'_bedrag_excl_btw' => $this->euro($document->subtotal_cents),
            $prefix.'_geldig_tot' => $document->valid_until?->format('d-m-Y') ?? 'geen einddatum',
        ];
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
            CallPurpose::Conversion => 'Ik bel over de prijsindicatie die u van ons heeft ontvangen. Schikt het u nu even?',
            CallPurpose::Close => 'Ik bel over de offerte die u na ons bezoek heeft ontvangen. Schikt het u nu even?',
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

    /**
     * Een opname duurt drie kwartier. "0,8 uur" is dan het soort antwoord dat
     * je alleen van een computer krijgt.
     */
    private function minutes(int $minutes): string
    {
        return $minutes < 90
            ? sprintf('ongeveer %d minuten', $minutes)
            : 'ongeveer '.$this->duration($minutes);
    }

    private function duration(int $minutes): string
    {
        $hours = $minutes / 60;

        return rtrim(rtrim(number_format($hours, 1, ',', '.'), '0'), ',').' uur';
    }
}
