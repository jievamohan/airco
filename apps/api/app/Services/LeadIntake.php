<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Jobs\ProcessNewLeadJob;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Enige toegangspoort waardoor nieuwe leads het systeem binnenkomen: mailbox,
 * websiteformulier, externe koppeling en handmatige invoer.
 */
class LeadIntake
{
    public function __construct(
        private readonly LeadTimeline $timeline,
        private readonly PhoneNumber $phone,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{lead: Lead, created: bool}
     */
    public function capture(array $attributes, string $source, ?string $sourceReference = null): array
    {
        $attributes['phone'] = $this->phone->normalise(
            isset($attributes['phone']) ? (string) $attributes['phone'] : null,
        ) ?? ($attributes['phone'] ?? null);

        $hash = $this->dedupeHash($attributes);

        return DB::transaction(function () use ($attributes, $source, $sourceReference, $hash): array {
            if ($sourceReference !== null) {
                $bySource = Lead::where('source_reference', $sourceReference)->first();

                if ($bySource !== null) {
                    return ['lead' => $bySource, 'created' => false];
                }
            }

            // Een herhaalde aanvraag binnen 30 dagen is dezelfde lead, geen
            // nieuwe — maar alleen zolang die lead nog loopt. Een gewonnen klus
            // is af, een verloren lead heeft een vastgelegde reden, en aan
            // allebei nog een aanvraag plakken betekent dat er niets meer mee
            // gebeurt. Bij meerdere kandidaten wint de jongste.
            $existing = Lead::where('dedupe_hash', $hash)
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotIn('status', array_map(static fn (LeadStatus $s): string => $s->value, LeadStatus::terminal()))
                ->where('do_not_contact', false)
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $afwijkend = $this->afwijkendeWaarden($existing, $attributes);

                $existing->fill(array_filter(
                    $attributes,
                    static fn (mixed $value, string $key): bool => $value !== null && $value !== '' && ($existing->{$key} === null || $existing->{$key} === ''),
                    ARRAY_FILTER_USE_BOTH,
                ));

                // Waar de lijst op sorteert, en waaraan te zien is dat deze lead
                // net opnieuw van zich heeft laten horen.
                $existing->last_request_at = now();
                $existing->requests_count = $existing->requests_count + 1;
                $existing->save();

                $this->timeline->record(
                    $existing,
                    'lead_duplicate',
                    'Herhaalde aanvraag ontvangen',
                    $this->duplicaatTekst($source, $afwijkend),
                    // De hele aanvraag bewaren, niet alleen de samenvatting:
                    // zonder de ruwe waarden valt hem later niet af te splitsen
                    // naar een eigen lead.
                    [
                        'source' => $source,
                        'afwijkend' => $afwijkend['klus'],
                        'afwijkend_contact' => $afwijkend['contact'],
                        'aanvraag' => $attributes,
                    ],
                    'lead',
                );

                return ['lead' => $existing, 'created' => false];
            }

            $lead = Lead::create($attributes + [
                'source' => $source,
                'source_reference' => $sourceReference,
                'dedupe_hash' => $hash,
                'status' => 'new',
                'last_request_at' => now(),
            ]);

            $this->timeline->record(
                $lead,
                'lead_received',
                'Aanvraag ontvangen',
                'Binnengekomen via '.$source.'.',
                ['source' => $source, 'reference' => $sourceReference],
            );

            ProcessNewLeadJob::dispatch($lead->id);

            return ['lead' => $lead, 'created' => true];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    /**
     * Wat de klant nu opgeeft en afwijkt van wat er al staat, gesplitst naar
     * wat het betekent.
     *
     * De klus tegenspreken is een beslissing: correctie of tweede opdracht.
     * Een ander mailadres is dat niet — dat is nieuws, maar het verandert niets
     * aan wat er gebouwd moet worden. Ze op één hoop gooien maakte de melding
     * onleesbaar precies wanneer hij ertoe deed.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{klus: list<string>, contact: list<string>}
     */
    private function afwijkendeWaarden(Lead $bestaand, array $attributes): array
    {
        $klusVelden = [
            'address' => 'adres', 'postcode' => 'postcode', 'city' => 'plaats',
            'space_size' => 'ruimtemaat', 'space_unit' => 'eenheid', 'rooms_count' => 'aantal ruimtes',
            'insulation' => 'isolatie', 'building_year' => 'bouwjaar', 'floor_level' => 'verdieping',
            'desired_start' => 'gewenste startdatum',
        ];

        $contactVelden = ['name' => 'naam', 'email' => 'e-mailadres', 'phone' => 'telefoon'];

        return [
            'klus' => $this->verschillen($bestaand, $attributes, $klusVelden),
            'contact' => $this->verschillen($bestaand, $attributes, $contactVelden),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $velden
     * @return list<string>
     */
    private function verschillen(Lead $bestaand, array $attributes, array $velden): array
    {
        $verschillen = [];

        foreach ($velden as $veld => $label) {
            if (! array_key_exists($veld, $attributes)) {
                continue;
            }

            $nieuw = $attributes[$veld];
            $oud = $bestaand->{$veld};

            if ($nieuw === null || $nieuw === '' || $oud === null || $oud === '') {
                continue;
            }

            if ($this->zelfdeWaarde($veld, $nieuw, $oud)) {
                continue;
            }

            $verschillen[] = sprintf('%s (%s in plaats van %s)', $label, (string) $nieuw, (string) $oud);
        }

        return $verschillen;
    }

    /**
     * "1234 AB" en "1234AB" zijn dezelfde postcode, en "80" uit een formulier is
     * hetzelfde antwoord als 80.0 uit de kolom. Zulke schijnverschillen melden
     * leert iemand de melding negeren.
     */
    private function zelfdeWaarde(string $veld, mixed $nieuw, mixed $oud): bool
    {
        if (is_numeric($nieuw) && is_numeric($oud)) {
            return (float) $nieuw === (float) $oud;
        }

        $links = (string) $nieuw;
        $rechts = (string) $oud;

        if (in_array($veld, ['postcode', 'phone'], true)) {
            $links = strtoupper((string) preg_replace('/[\s-]/', '', $links));
            $rechts = strtoupper((string) preg_replace('/[\s-]/', '', $rechts));
        }

        return mb_strtolower(trim($links)) === mb_strtolower(trim($rechts));
    }

    /**
     * @param  array{klus: list<string>, contact: list<string>}  $afwijkend
     */
    private function duplicaatTekst(string $source, array $afwijkend): string
    {
        $delen = ['Via '.$source.'.'];

        if ($afwijkend['klus'] !== []) {
            $delen[] = 'De klus wijkt af van wat er al stond: '.implode(', ', $afwijkend['klus']).'.';
        }

        if ($afwijkend['contact'] !== []) {
            $delen[] = 'Nieuwe contactgegevens opgegeven: '.implode(', ', $afwijkend['contact']).'.';
        }

        if (count($delen) === 1) {
            return 'Via '.$source.'; dezelfde gegevens als eerder.';
        }

        $delen[] = 'Niets is overschreven — beoordeel zelf wat klopt.';

        return implode(' ', $delen);
    }

    /**
     * Zet een bewaarde herhaalde aanvraag als eigen lead neer.
     *
     * De ontdubbeling zou hem meteen weer terugvouwen, dus die wordt hier
     * overgeslagen: dit is een mens die zegt dat het een andere klus is. Beide
     * leads verwijzen naar elkaar, want het blijft dezelfde klant.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function splitFrom(Lead $bron, array $attributes, string $source): Lead
    {
        $attributes['phone'] = $this->phone->normalise(
            isset($attributes['phone']) ? (string) $attributes['phone'] : null,
        ) ?? ($attributes['phone'] ?? null);

        return DB::transaction(function () use ($bron, $attributes, $source): Lead {
            $lead = Lead::create($attributes + [
                'source' => $source,
                'dedupe_hash' => $this->dedupeHash($attributes),
                'status' => 'new',
                'last_request_at' => now(),
            ]);

            $this->timeline->record(
                $lead,
                'lead_received',
                'Aanvraag ontvangen',
                sprintf('Afgesplitst van de lead van %s: dit is een andere klus.', $bron->name),
                ['source' => $source, 'afgesplitst_van' => $bron->uuid],
            );

            $this->timeline->record(
                $bron,
                'lead_split',
                'Aanvraag afgesplitst',
                'De laatste herhaalde aanvraag staat nu als eigen lead.',
                ['naar' => $lead->uuid],
                'user',
            );

            ProcessNewLeadJob::dispatch($lead->id);

            return $lead;
        });
    }

    /**
     * Waaraan twee aanvragen dezelfde klus zijn.
     *
     * De klus zit op een adres en niet in een mailbox: de berekening, de
     * opname, de offerte en de montage horen allemaal bij één locatie. Vandaar
     * postcode plus huisnummer zodra we die hebben. Op contactgegevens sleutelen
     * ging twee kanten op mis — dezelfde woning met twee mailadressen werd twee
     * leads, en twee verschillende klussen van dezelfde persoon werden er één.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function dedupeHash(array $attributes): string
    {
        $adres = $this->adresSleutel($attributes);

        if ($adres !== null) {
            return hash('sha256', 'adres|'.$adres);
        }

        // Geen bruikbaar adres — via de mailbox komt dat voor. Dan is het
        // contact het beste wat we hebben.
        $email = isset($attributes['email']) ? strtolower(trim((string) $attributes['email'])) : '';
        $phone = isset($attributes['phone']) ? preg_replace('/\D/', '', (string) $attributes['phone']) ?? '' : '';

        return hash('sha256', 'contact|'.$email.'|'.$phone);
    }

    /**
     * Postcode plus huisnummer wijst in Nederland één adres aan. Het huisnummer
     * is het eerste getal in de straatregel; een toevoeging als "12-B" telt niet
     * mee, want die schrijft niet iedereen hetzelfde op.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function adresSleutel(array $attributes): ?string
    {
        $postcode = strtoupper((string) preg_replace('/\s+/', '', (string) ($attributes['postcode'] ?? '')));

        if (preg_match('/^\d{4}[A-Z]{2}$/', $postcode) !== 1) {
            return null;
        }

        if (preg_match('/\d+/', (string) ($attributes['address'] ?? ''), $treffer) !== 1) {
            return null;
        }

        return $postcode.'|'.$treffer[0];
    }
}
