<?php

declare(strict_types=1);

namespace App\Services;

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

            // Een herhaalde aanvraag binnen 30 dagen is dezelfde lead, geen nieuwe.
            $existing = Lead::where('dedupe_hash', $hash)
                ->where('created_at', '>=', now()->subDays(30))
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
                    $afwijkend === []
                        ? 'Via '.$source.'; dezelfde gegevens als eerder.'
                        : 'Via '.$source.'. Afwijkend van wat er al stond: '.implode(', ', $afwijkend).'. Die waarden zijn niet overgenomen — beoordeel zelf wat klopt.',
                    ['source' => $source, 'afwijkend' => $afwijkend],
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
     * Wat de klant nu opgeeft en afwijkt van wat er al staat.
     *
     * Zulke waarden worden bewust niet overgenomen: wat er staat komt vaak uit
     * een telefoongesprek en is nagevraagd. Ze stil weggooien is wel het
     * verkeerde uiterste — dan vult iemand het formulier opnieuw in met een
     * grotere ruimte en merkt niemand het.
     *
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function afwijkendeWaarden(Lead $bestaand, array $attributes): array
    {
        $labels = [
            'name' => 'naam', 'email' => 'e-mailadres', 'phone' => 'telefoon',
            'address' => 'adres', 'postcode' => 'postcode', 'city' => 'plaats',
            'space_size' => 'ruimtemaat', 'space_unit' => 'eenheid', 'rooms_count' => 'aantal ruimtes',
            'insulation' => 'isolatie', 'building_year' => 'bouwjaar', 'floor_level' => 'verdieping',
            'desired_start' => 'gewenste startdatum', 'notes' => 'opmerking',
        ];

        $afwijkend = [];

        foreach ($labels as $veld => $label) {
            if (! array_key_exists($veld, $attributes)) {
                continue;
            }

            $nieuw = $attributes[$veld];
            $oud = $bestaand->{$veld};

            if ($nieuw === null || $nieuw === '' || $oud === null || $oud === '') {
                continue;
            }

            // Losjes vergelijken: "80" uit een formulier en 80.0 uit de kolom
            // zijn hetzelfde antwoord.
            if ((string) $nieuw === (string) $oud || (is_numeric($nieuw) && is_numeric($oud) && (float) $nieuw === (float) $oud)) {
                continue;
            }

            $afwijkend[] = sprintf('%s (%s in plaats van %s)', $label, (string) $nieuw, (string) $oud);
        }

        return $afwijkend;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dedupeHash(array $attributes): string
    {
        $email = isset($attributes['email']) ? strtolower(trim((string) $attributes['email'])) : '';
        $phone = isset($attributes['phone']) ? preg_replace('/\D/', '', (string) $attributes['phone']) ?? '' : '';

        return hash('sha256', $email.'|'.$phone);
    }
}
