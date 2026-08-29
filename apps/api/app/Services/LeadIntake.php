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
                $existing->fill(array_filter(
                    $attributes,
                    static fn (mixed $value, string $key): bool => $value !== null && $value !== '' && ($existing->{$key} === null || $existing->{$key} === ''),
                    ARRAY_FILTER_USE_BOTH,
                ))->save();

                $this->timeline->record($existing, 'lead_duplicate', 'Herhaalde aanvraag ontvangen', 'Via '.$source.'; bestaande lead aangevuld.');

                return ['lead' => $existing, 'created' => false];
            }

            $lead = Lead::create($attributes + [
                'source' => $source,
                'source_reference' => $sourceReference,
                'dedupe_hash' => $hash,
                'status' => 'new',
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
    private function dedupeHash(array $attributes): string
    {
        $email = isset($attributes['email']) ? strtolower(trim((string) $attributes['email'])) : '';
        $phone = isset($attributes['phone']) ? preg_replace('/\D/', '', (string) $attributes['phone']) ?? '' : '';

        return hash('sha256', $email.'|'.$phone);
    }
}
