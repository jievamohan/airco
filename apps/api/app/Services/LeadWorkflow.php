<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CallOutcome;
use App\Enums\CallPurpose;
use App\Enums\LeadStatus;
use App\Models\Call;
use App\Models\Lead;
use App\Models\LeadSequenceRun;
use App\Models\Quote;
use App\Models\Sequence;
use App\Services\Voice\CallVariables;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Support\Carbon;

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

        $lead->forceFill([
            'estimated_kw' => $sizing['kw'],
            'recommended_system' => $sizing['system']->value,
            'tier' => $lead->tier->value ?? $this->settings->string('agent.pricing.default_tier', 'mid'),
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
    public function scheduleCall(Lead $lead, CallPurpose $purpose, ?Carbon $notBefore = null): ?Call
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

        $scheduledFor = $this->callingWindow->nextOpening($notBefore ?? now());

        $call = $lead->calls()->create([
            'provider' => 'elevenlabs',
            'purpose' => $purpose->value,
            'attempt_no' => $lead->call_attempts + 1,
            'status' => 'queued',
            'to_number' => $number,
            'scheduled_for' => $scheduledFor,
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

        $quote = $call->purpose === CallPurpose::Qualification ? null : $lead->latestQuote()->first();
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
            default => tap($call, fn () => $this->onConversionAnswered($lead, $outcome)),
        };
    }

    private function onQualified(Lead $lead): void
    {
        $this->transition($lead, LeadStatus::Qualified);
        $this->notifier->leadQualified($lead);
    }

    private function onConversionAnswered(Lead $lead, CallOutcome $outcome): void
    {
        if ($outcome === CallOutcome::AppointmentBooked) {
            return; // afspraak wordt door BookAppointmentJob afgehandeld
        }

        if ($outcome === CallOutcome::CallbackRequested) {
            $this->timeline->record($lead, 'callback_requested', 'Terugbelverzoek', 'De klant wil op een later moment teruggebeld worden.');
            $this->scheduleCall($lead, CallPurpose::Conversion, now()->addDay());

            return;
        }

        $this->transition($lead, LeadStatus::FollowUp);
    }

    // -----------------------------------------------------------------
    // Stap 4 — offerte
    // -----------------------------------------------------------------

    public function buildQuote(Lead $lead): Quote
    {
        $quote = $this->quotes->createForLead($lead);

        $this->timeline->record(
            $lead,
            'quote_created',
            'Offerte opgesteld',
            sprintf('%s — € %s incl. btw.', $quote->number, number_format($quote->total_cents / 100, 2, ',', '.')),
            ['quote_id' => $quote->id, 'number' => $quote->number, 'total_cents' => $quote->total_cents],
        );

        return $quote;
    }

    public function markQuoteSent(Lead $lead, Quote $quote): void
    {
        $quote->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

        $this->timeline->record(
            $lead,
            'quote_sent',
            'Offerte gemaild',
            sprintf('%s verstuurd naar %s.', $quote->number, (string) $lead->email),
            ['quote_id' => $quote->id],
        );

        $this->transition($lead, LeadStatus::Quoted);
        $this->notifier->quoteSent($lead, $quote);

        $delay = $this->settings->int('agent.workflow.conversion_call_delay_minutes', 60);
        $this->scheduleCall($lead, CallPurpose::Conversion, now()->addMinutes($delay));
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

        if (! $lead->status->isTerminal() && $lead->status !== LeadStatus::Quoted) {
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

        foreach ($map as $field => $type) {
            if (! array_key_exists($field, $collected) || $collected[$field] === null || $collected[$field] === '') {
                continue;
            }

            $value = $collected[$field];

            $updates[$field] = match ($type) {
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'date' => Carbon::parse((string) $value)->toDateString(),
                default => (string) $value,
            };
        }

        if (isset($collected['notes']) && is_string($collected['notes']) && $collected['notes'] !== '') {
            $updates['notes'] = trim(($lead->notes ?? '')."\n\nUit telefoongesprek: ".$collected['notes']);
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
