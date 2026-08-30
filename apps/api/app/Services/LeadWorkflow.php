<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CallOutcome;
use App\Enums\CallPurpose;
use App\Enums\LeadStatus;
use App\Enums\QuoteKind;
use App\Models\Call;
use App\Models\Lead;
use App\Models\LeadSequenceRun;
use App\Models\Quote;
use App\Models\Sequence;
use App\Services\Voice\CallVariables;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * De toestandsmachine van een lead.
 *
 * Elke publieke methode is idempotent genoeg om vanuit het dashboard opnieuw
 * afgetrapt te worden: er wordt altijd een nieuwe poging vastgelegd in plaats
 * van bestaande historie te overschrijven.
 */
class LeadWorkflow
{
    public function __construct(
        private readonly SizingCalculator $sizing,
        private readonly QuoteBuilder $quotes,
        private readonly LeadTimeline $timeline,
        private readonly CallingWindow $callingWindow,
        private readonly SettingsRepository $settings,
        private readonly VoiceAgentClient $voice,
        private readonly CallVariables $variables,
        private readonly Notifier $notifier,
        private readonly PhoneNumber $phone,
    ) {}

    // -----------------------------------------------------------------
    // Stap 1 — verrijken
    // -----------------------------------------------------------------

    public function enrich(Lead $lead): Lead
    {
        $sizing = $this->sizing->forLead($lead);

        // De kwaliteitsklasse blijft bewust leeg tot iemand hem kiest of de
        // agent hem ophaalt. Hier de standaard invullen maakte van een
        // terugvaloptie een vastgelegde keuze: het dashboard toonde daarna
        // "Midden" alsof dat besloten was. De offerte valt zelf al terug op de
        // standaardklasse als er niets staat.
        $lead->forceFill([
            'estimated_kw' => $sizing['kw'],
            'recommended_system' => $sizing['system']->value,
        ])->save();

        $this->timeline->record(
            $lead,
            'lead_enriched',
            'Aanvraag verrijkt',
            sprintf(
                'Geadviseerd: %s van %s kW%s.',
                $sizing['system']->label(),
                number_format($sizing['kw'], 1, ',', '.'),
                $sizing['volume_m3'] !== null
                    ? sprintf(' op basis van %s m³ en %d W/m³', number_format($sizing['volume_m3'], 1, ',', '.'), $sizing['factor'])
                    : ' (geen ruimtemaat opgegeven)',
            ),
            ['kw' => $sizing['kw'], 'system' => $sizing['system']->value, 'volume_m3' => $sizing['volume_m3']],
        );

        $this->transition($lead, LeadStatus::Enriched);

        return $lead;
    }

    // -----------------------------------------------------------------
    // Stap 2 — bellen
    // -----------------------------------------------------------------

    /**
     * Legt een belpoging vast en plant hem binnen het belvenster in.
     */
    /**
     * @param  bool  $negeerBelvenster  Alleen voor een handmatige actie: het
     *                                  belvenster beschermt tegen een agent die
     *                                  uit zichzelf op zondagavond belt, niet
     *                                  tegen een mens die bewust op een knop
     *                                  drukt en weet wie er aan de lijn komt.
     */
    public function scheduleCall(Lead $lead, CallPurpose $purpose, ?Carbon $notBefore = null, bool $negeerBelvenster = false): ?Call
    {
        if (! $lead->isContactable()) {
            return null;
        }

        $number = $this->phone->normalise($lead->phone);

        if ($number === null) {
            $this->timeline->record($lead, 'call_skipped', 'Bellen niet mogelijk', 'Er is geen bruikbaar telefoonnummer bekend.');
            $this->startChase($lead);

            return null;
        }

        $max = $this->settings->int('agent.workflow.max_call_attempts', 4);

        if ($lead->call_attempts >= $max) {
            $this->markUnreachable($lead);

            return null;
        }

        $vanaf = $notBefore ?? now();
        $scheduledFor = $negeerBelvenster ? $vanaf : $this->callingWindow->nextOpening($vanaf);

        $call = $lead->calls()->create([
            'provider' => 'elevenlabs',
            'purpose' => $purpose->value,
            'attempt_no' => $lead->call_attempts + 1,
            'status' => 'queued',
            'to_number' => $number,
            'scheduled_for' => $scheduledFor,
            'ignores_calling_window' => $negeerBelvenster,
        ]);

        $this->timeline->record(
            $lead,
            'call_scheduled',
            $purpose->label().' ingepland',
            sprintf('Gepland op %s.', $scheduledFor->timezone($this->settings->string('agent.calendar.timezone', 'Europe/Amsterdam'))->format('d-m-Y H:i')),
            ['call_id' => $call->id, 'purpose' => $purpose->value],
        );

        $lead->forceFill(['next_action_at' => $scheduledFor])->save();

        return $call;
    }

    /**
     * Zet een ingeplande belpoging daadwerkelijk door naar de voice agent.
     */
    public function dispatchCall(Call $call): Call
    {
        $lead = $call->lead;

        if (! $lead->isContactable()) {
            $call->forceFill(['status' => 'failed', 'outcome' => CallOutcome::Failed->value, 'error_message' => 'Lead mag niet meer benaderd worden.'])->save();

            return $call;
        }

        $quote = $call->purpose === CallPurpose::Qualification ? null : $this->documentFor($lead, $call->purpose);
        $variables = $this->variables->build($lead, $call->purpose, $quote);

        $result = $this->voice->startCall($call, (string) $call->to_number, $variables);

        $call->forceFill([
            'status' => $result['accepted'] ? 'initiated' : 'failed',
            'provider_call_id' => $result['provider_call_id'],
            'conversation_id' => $result['conversation_id'],
            'started_at' => $result['accepted'] ? now() : null,
            'outcome' => $result['accepted'] ? null : CallOutcome::Failed->value,
            'error_message' => $result['accepted'] ? null : $result['message'],
        ])->save();

        if (! $result['accepted']) {
            $this->timeline->record($lead, 'call_failed', 'Bellen mislukt', $result['message'], ['call_id' => $call->id]);
            $this->startChase($lead);

            return $call;
        }

        $lead->forceFill([
            'call_attempts' => $lead->call_attempts + 1,
            'last_contact_at' => now(),
        ])->save();

        $this->timeline->record(
            $lead,
            'call_started',
            $call->purpose->label().' gestart',
            sprintf('Poging %d naar %s.', $call->attempt_no, (string) $call->to_number),
            ['call_id' => $call->id],
            'voice_agent',
        );

        if ($lead->status === LeadStatus::Enriched || $lead->status === LeadStatus::New) {
            $this->transition($lead, LeadStatus::Calling);
        }

        return $call;
    }

    // -----------------------------------------------------------------
    // Stap 3 — gespreksresultaat verwerken
    // -----------------------------------------------------------------

    /**
     * Verwerkt het resultaat van een gesprek en bepaalt de volgende stap.
     *
     * @param  array<string, mixed>  $collected  gestructureerde velden uit het gesprek
     */
    public function completeCall(Call $call, CallOutcome $outcome, ?string $transcript = null, ?string $summary = null, array $collected = []): Call
    {
        $lead = $call->lead;

        $call->forceFill([
            'status' => 'completed',
            'outcome' => $outcome->value,
            'ended_at' => now(),
            'transcript' => $transcript,
            'summary' => $summary,
            'collected' => $collected === [] ? null : $collected,
            'duration_seconds' => $call->started_at !== null ? max(0, now()->diffInSeconds($call->started_at, true)) : null,
        ])->save();

        if ($collected !== []) {
            $this->applyCollected($lead, $collected);
        }

        $this->timeline->record(
            $lead,
            'call_completed',
            $call->purpose->label().' afgerond',
            $summary ?? $outcome->label(),
            ['call_id' => $call->id, 'outcome' => $outcome->value],
            'voice_agent',
        );

        if (! $outcome->reachedLead()) {
            $this->startChase($lead);

            return $call;
        }

        $this->stopChase($lead, 'Lead heeft gereageerd.');

        return match (true) {
            $outcome === CallOutcome::DoNotContact => tap($call, fn () => $this->markDoNotContact($lead)),
            $outcome === CallOutcome::Declined => tap($call, fn () => $this->markLost($lead, 'Afgewezen tijdens gesprek.')),
            $call->purpose === CallPurpose::Qualification => tap($call, fn () => $this->onQualified($lead)),
            default => tap($call, fn () => $this->onConversionAnswered($lead, $call->purpose, $outcome)),
        };
    }

    private function onQualified(Lead $lead): void
    {
        $this->transition($lead, LeadStatus::Qualified);
        $this->notifier->leadQualified($lead);
    }

    private function onConversionAnswered(Lead $lead, CallPurpose $purpose, CallOutcome $outcome): void
    {
        if ($outcome === CallOutcome::AppointmentBooked) {
            return; // afspraak wordt door BookAppointmentJob afgehandeld
        }

        if ($outcome === CallOutcome::CallbackRequested) {
            $this->timeline->record($lead, 'callback_requested', 'Terugbelverzoek', 'De klant wil op een later moment teruggebeld worden.');
            // Met hetzelfde doel terugbellen: wie op de offerte zat te wachten,
            // hoort niet opnieuw de prijsindicatie doorgenomen te krijgen.
            $this->scheduleCall($lead, $purpose === CallPurpose::Close ? CallPurpose::Close : CallPurpose::Conversion, now()->addDay());

            return;
        }

        $this->transition($lead, LeadStatus::FollowUp);
    }

    /**
     * Het bedrag dat in dít gesprek op tafel ligt: bij het afsluitgesprek de
     * offerte, daarvoor de prijsindicatie. Zonder dit onderscheid noemt de
     * agent na de opname alsnog het oude richtbedrag.
     */
    private function documentFor(Lead $lead, CallPurpose $purpose): ?Quote
    {
        if ($purpose === CallPurpose::Close) {
            return $lead->quotes()->where('kind', QuoteKind::Final->value)->latest('id')->first()
                ?? $lead->latestQuote()->first();
        }

        return $lead->quotes()->where('kind', QuoteKind::Indication->value)->latest('id')->first()
            ?? $lead->latestQuote()->first();
    }

    // -----------------------------------------------------------------
    // Stap 4 — prijsindicatie, opname, offerte
    // -----------------------------------------------------------------

    /**
     * Stelt een prijsindicatie of een offerte op.
     *
     * Het onderscheid is niet cosmetisch: aan een offerte kan de klant rechten
     * ontlenen, dus die hoort pas te ontstaan als iemand ter plaatse heeft
     * gekeken. Alles wat daarvoor de deur uit gaat, is een richtbedrag.
     */
    public function buildQuote(Lead $lead, QuoteKind $kind = QuoteKind::Indication): Quote
    {
        $quote = $this->quotes->createForLead($lead, $kind);

        $this->timeline->record(
            $lead,
            $kind->isBinding() ? 'quote_created' : 'indication_created',
            $kind->label().' opgesteld',
            sprintf('%s — € %s incl. btw.', $quote->number, number_format($quote->total_cents / 100, 2, ',', '.')),
            ['quote_id' => $quote->id, 'number' => $quote->number, 'kind' => $kind->value, 'total_cents' => $quote->total_cents],
        );

        if ($quote->margin_warning) {
            $this->timeline->record(
                $lead,
                'margin_warning',
                'Marge onder de drempel',
                sprintf(
                    '%s%% marge: € %s opbrengst op € %s kostprijs, beide excl. btw.',
                    number_format($quote->margin_pct, 1, ',', '.'),
                    number_format($quote->subtotal_cents / 100, 2, ',', '.'),
                    number_format($quote->cost_cents / 100, 2, ',', '.'),
                ),
                ['quote_id' => $quote->id, 'margin_pct' => $quote->margin_pct, 'cost_cents' => $quote->cost_cents],
            );
        }

        return $quote;
    }

    /**
     * De prijsindicatie is gemaild. Daarna belt de agent na om hem door te
     * nemen en een opname ter plaatse af te spreken — niet om de opdracht
     * rond te maken, want er ligt nog geen aanbod.
     */
    public function markIndicationSent(Lead $lead, Quote $quote): void
    {
        if (! $this->markSent($lead, $quote, 'indication_sent', 'Prijsindicatie gemaild')) {
            return;
        }

        $this->transition($lead, LeadStatus::Indicated);

        $delay = $this->settings->int('agent.workflow.conversion_call_delay_minutes', 60);
        $this->scheduleCall($lead, CallPurpose::Conversion, now()->addMinutes($delay));
    }

    /**
     * De offerte is gemaild. Die bindt, dus hierna gaat het gesprek nog maar
     * over één ding: akkoord en een installatiedatum.
     */
    public function markQuoteSent(Lead $lead, Quote $quote): void
    {
        if (! $this->markSent($lead, $quote, 'quote_sent', 'Offerte gemaild')) {
            return;
        }

        $this->transition($lead, LeadStatus::Quoted);

        $delay = $this->settings->int('agent.workflow.close_call_delay_minutes', 60);
        $this->scheduleCall($lead, CallPurpose::Close, now()->addMinutes($delay));
    }

    /**
     * Legt vast dat het bezoek is geweest. Pas daarna mag er een offerte uit:
     * de aannames uit het telefoongesprek zijn dan vervangen door wat er
     * werkelijk staat.
     */
    public function markSurveyed(Lead $lead, ?string $notes = null): void
    {
        if ($notes !== null && trim($notes) !== '') {
            $lead->forceFill(['notes' => trim(($lead->notes ?? '')."\n\nUit de opname: ".trim($notes))])->save();
        }

        $lead->appointments()
            ->where('kind', 'survey')
            ->where('status', 'scheduled')
            ->update(['status' => 'completed']);

        $this->timeline->record(
            $lead,
            'survey_completed',
            'Opname ter plaatse afgerond',
            $notes !== null && trim($notes) !== '' ? trim($notes) : 'De situatie is ter plaatse bekeken; de offerte kan opgesteld worden.',
        );

        $this->transition($lead, LeadStatus::Surveyed);
        $this->notifier->surveyCompleted($lead);
    }

    /**
     * Of er een bindende offerte uit mag. Zolang niemand ter plaatse is
     * geweest, is een offerte een belofte op basis van een telefoongesprek.
     */
    public function surveyDone(Lead $lead): bool
    {
        if (in_array($lead->status, [LeadStatus::Surveyed, LeadStatus::Quoted, LeadStatus::AppointmentScheduled, LeadStatus::Won], true)) {
            return true;
        }

        return $lead->appointments()
            ->where('kind', 'survey')
            ->whereIn('status', ['completed', 'confirmed'])
            ->exists();
    }

    /**
     * Het gedeelde deel van "verstuurd": vastleggen, melden, en bepalen of de
     * lead hierdoor nog een stap in de funnel opschuift.
     *
     * @return bool of de lead de bijbehorende status en het nabelgesprek krijgt
     */
    private function markSent(Lead $lead, Quote $quote, string $eventType, string $eventTitle): bool
    {
        $quote->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

        $this->timeline->record(
            $lead,
            $eventType,
            $eventTitle,
            sprintf('%s verstuurd naar %s.', $quote->number, (string) $lead->email),
            ['quote_id' => $quote->id, 'kind' => $quote->kind->value],
        );

        $this->notifier->quoteSent($lead, $quote);

        // Een lead die de installatie al rond heeft, hoort niet terug de
        // verkoopfunnel in: een herzien document naar een geboekte klant mag
        // geen nieuw verkoopgesprek uitlokken.
        if (in_array($lead->status, [LeadStatus::AppointmentScheduled, LeadStatus::Won], true)) {
            $this->timeline->record(
                $lead,
                'quote_resent',
                sprintf('Herziene %s bij een geboekte klant', $quote->kind->noun()),
                'De status blijft staan en er wordt geen gesprek ingepland.',
                ['quote_id' => $quote->id],
            );

            return false;
        }

        return true;
    }

    // -----------------------------------------------------------------
    // Chase-cadans
    // -----------------------------------------------------------------

    public function startChase(Lead $lead): ?LeadSequenceRun
    {
        if (! $lead->isContactable()) {
            return null;
        }

        $sequence = Sequence::where('key', Sequence::CHASE)->where('active', true)->first();

        if ($sequence === null) {
            return null;
        }

        $run = LeadSequenceRun::firstOrNew([
            'lead_id' => $lead->id,
            'sequence_id' => $sequence->id,
        ]);

        if ($run->exists && $run->status === 'active') {
            return $run; // loopt al
        }

        $firstStep = $sequence->steps()->where('active', true)->orderBy('position')->first();

        if ($firstStep === null) {
            return null;
        }

        $run->forceFill([
            'next_position' => $firstStep->position,
            'status' => 'active',
            'next_run_at' => now()->addMinutes($firstStep->delay_minutes),
            'completed_at' => null,
            'stop_reason' => null,
        ])->save();

        $this->timeline->record(
            $lead,
            'chase_started',
            'Opvolging gestart',
            sprintf('Volgende stap "%s" over %d minuten.', $firstStep->label, $firstStep->delay_minutes),
            ['sequence_run_id' => $run->id],
        );

        // Een lead met een bedrag of een bezoek in de agenda staat verder dan
        // "in opvolging"; die status zou het verloop terugdraaien.
        if (! $lead->status->isTerminal() && ! in_array($lead->status, LeadStatus::inSalesTraject(), true)) {
            $this->transition($lead, LeadStatus::FollowUp);
        }

        return $run;
    }

    public function stopChase(Lead $lead, string $reason): void
    {
        $runs = $lead->sequenceRuns()->where('status', 'active')->get();

        foreach ($runs as $run) {
            $run->forceFill(['status' => 'stopped', 'stop_reason' => $reason, 'next_run_at' => null])->save();
        }

        if ($runs->isNotEmpty()) {
            $this->timeline->record($lead, 'chase_stopped', 'Opvolging gestopt', $reason);
        }
    }

    // -----------------------------------------------------------------
    // Eindstatussen
    // -----------------------------------------------------------------

    public function markUnreachable(Lead $lead): void
    {
        $this->stopChase($lead, 'Maximaal aantal pogingen bereikt.');
        $this->transition($lead, LeadStatus::Unreachable);
        $this->timeline->record($lead, 'lead_unreachable', 'Lead onbereikbaar', 'Alle bel- en mailpogingen zijn uitgevoerd zonder reactie.');
        $this->notifier->leadUnreachable($lead);
    }

    public function markLost(Lead $lead, string $reason): void
    {
        $this->stopChase($lead, $reason);
        $lead->forceFill(['lost_at' => now(), 'lost_reason' => $reason])->save();
        $this->transition($lead, LeadStatus::Lost);
        $this->notifier->leadLost($lead, $reason);
    }

    public function markDoNotContact(Lead $lead): void
    {
        $this->stopChase($lead, 'Klant wil niet benaderd worden.');
        $lead->forceFill(['do_not_contact' => true])->save();
        $this->transition($lead, LeadStatus::DoNotContact);
        $this->timeline->record($lead, 'do_not_contact', 'Niet meer benaderen', 'De klant heeft aangegeven geen contact meer te willen.');
    }

    public function transition(Lead $lead, LeadStatus $status): void
    {
        if ($lead->status === $status) {
            return;
        }

        $from = $lead->status;
        $lead->forceFill(['status' => $status->value])->save();

        $this->timeline->record(
            $lead,
            'status_changed',
            sprintf('Status: %s', $status->label()),
            sprintf('Van "%s" naar "%s".', $from->label(), $status->label()),
            ['from' => $from->value, 'to' => $status->value],
        );
    }

    /**
     * Neemt gegevens over die de voice agent tijdens het gesprek heeft opgehaald.
     * Alleen bekende velden worden overgenomen, en alleen als ze een waarde hebben.
     *
     * @param  array<string, mixed>  $collected
     */
    /**
     * Waarden waarvan de kolom of de workflow maar een paar mogelijkheden kent.
     * De prompt vraagt de agent hier `m2` of `good` terug te geven, maar een
     * gesprek is geen formulier: hij kwam een keer met "vierkante meter", en
     * dat past niet in een kolom van drie tekens.
     *
     * @var array<string, array<string, string>>
     */
    private const TOEGESTAAN = [
        'space_unit' => [
            'm2' => 'm2', 'm²' => 'm2', 'vierkante meter' => 'm2', 'vierkantemeter' => 'm2',
            'm3' => 'm3', 'm³' => 'm3', 'kubieke meter' => 'm3', 'kubiekemeter' => 'm3',
        ],
        'insulation' => [
            'good' => 'good', 'goed' => 'good',
            'average' => 'average', 'gemiddeld' => 'average', 'standaard' => 'average',
            'poor' => 'poor', 'matig' => 'poor', 'slecht' => 'poor',
        ],
        'tier' => [
            'budget' => 'budget', 'voordelig' => 'budget',
            'mid' => 'mid', 'midden' => 'mid',
            'premium' => 'premium',
        ],
    ];

    /**
     * Lengte van de vrije tekstkolommen, zodat een uitgebreid antwoord de
     * opslag niet laat klappen.
     *
     * @var array<string, int>
     */
    private const MAX_LENGTE = [
        'wall_type' => 255,
        'outdoor_unit_placement' => 255,
        'email' => 190,
    ];

    /**
     * Zet één antwoord uit het gesprek om naar iets wat de kolom aankan.
     * `null` betekent: niet te gebruiken, sla dit veld over.
     */
    private function normaliseerVeld(string $field, string $type, mixed $value): mixed
    {
        if (isset(self::TOEGESTAAN[$field])) {
            $sleutel = mb_strtolower(trim((string) $value));

            return self::TOEGESTAAN[$field][$sleutel] ?? null;
        }

        return match ($type) {
            'int' => is_numeric($value) ? (int) $value : null,
            'float' => is_numeric($value) ? (float) $value : null,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'date' => $this->normaliseerDatum($value),
            default => mb_substr(trim((string) $value), 0, self::MAX_LENGTE[$field] ?? 255),
        };
    }

    private function normaliseerDatum(mixed $value): ?string
    {
        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            // "ergens in het najaar" is een prima antwoord aan de telefoon en
            // een onbruikbare datum in de database.
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $collected
     */
    private function applyCollected(Lead $lead, array $collected): void
    {
        $map = [
            'rooms_count' => 'int',
            'space_size' => 'float',
            'space_unit' => 'string',
            'building_year' => 'int',
            'insulation' => 'string',
            'floor_level' => 'int',
            'wall_type' => 'string',
            'outdoor_unit_placement' => 'string',
            'pipe_length_m' => 'int',
            'needs_condensate_pump' => 'bool',
            'needs_extra_group' => 'bool',
            'desired_start' => 'date',
            'email' => 'string',
            'tier' => 'string',
        ];

        $updates = [];
        $genegeerd = [];

        foreach ($map as $field => $type) {
            if (! array_key_exists($field, $collected) || $collected[$field] === null || $collected[$field] === '') {
                continue;
            }

            $waarde = $this->normaliseerVeld($field, $type, $collected[$field]);

            if ($waarde === null) {
                $genegeerd[] = $field;

                continue;
            }

            $updates[$field] = $waarde;
        }

        if (isset($collected['notes']) && is_string($collected['notes']) && $collected['notes'] !== '') {
            $updates['notes'] = trim(($lead->notes ?? '')."\n\nUit telefoongesprek: ".$collected['notes']);
        }

        if ($genegeerd !== []) {
            // Zichtbaar maken wat er niet doorkwam. Stil weglaten betekent dat
            // iemand later een leeg veld ziet en niet weet of het niet gevraagd
            // is of niet begrepen.
            $this->timeline->record(
                $lead,
                'lead_field_ignored',
                'Antwoord uit het gesprek niet overgenomen',
                implode(', ', $genegeerd).' — de agent gaf iets terug dat hier niet in past.',
                ['velden' => $genegeerd],
                'voice_agent',
            );
        }

        if ($updates === []) {
            return;
        }

        $lead->forceFill($updates)->save();

        // Sizing opnieuw bepalen met de nieuwe gegevens.
        $sizing = $this->sizing->forLead($lead->fresh() ?? $lead);
        $lead->forceFill([
            'estimated_kw' => $sizing['kw'],
            'recommended_system' => $sizing['system']->value,
        ])->save();

        $this->timeline->record(
            $lead,
            'lead_updated',
            'Gegevens bijgewerkt uit gesprek',
            implode(', ', array_keys($updates)),
            $updates,
            'voice_agent',
        );
    }
}
