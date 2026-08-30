<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CallPurpose;
use App\Enums\LeadStatus;
use App\Enums\QuoteKind;
use App\Http\Controllers\Controller;
use App\Jobs\BookAppointmentJob;
use App\Jobs\SendQuoteJob;
use App\Models\Lead;
use App\Services\AppointmentScheduler;
use App\Services\LeadIntake;
use App\Services\LeadTimeline;
use App\Services\LeadWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Elke stap van de workflow kan vanuit het dashboard opnieuw worden afgetrapt.
 * Er wordt nooit historie overschreven: een herstart levert altijd een nieuwe
 * poging, offerteversie of afspraak op.
 */
class LeadActionController extends Controller
{
    /** @var list<string> */
    private const ACTIONS = [
        'enrich', 'call_qualification', 'call_qualification_now', 'call_conversion', 'call_conversion_now',
        'call_close', 'call_close_now', 'send_indication', 'send_quote', 'book_survey', 'mark_surveyed',
        'book_appointment', 'start_chase', 'stop_chase', 'mark_lost', 'mark_won', 'reopen',
        'split_request',
    ];

    public function __invoke(Request $request, string $uuid, LeadWorkflow $workflow, LeadTimeline $timeline, AppointmentScheduler $scheduler): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:'.implode(',', self::ACTIONS)],
            'reason' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date', 'after:now'],
        ]);

        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        $action = $data['action'];

        $timeline->record(
            $lead,
            'action_triggered',
            'Stap handmatig gestart',
            $action,
            ['action' => $action],
            'user',
            (string) $request->user()?->name,
        );

        $message = match ($action) {
            'enrich' => $this->enrich($workflow, $lead),
            'call_qualification' => $this->call($workflow, $lead, CallPurpose::Qualification),
            'call_qualification_now' => $this->call($workflow, $lead, CallPurpose::Qualification, true),
            'call_conversion' => $this->call($workflow, $lead, CallPurpose::Conversion),
            'call_conversion_now' => $this->call($workflow, $lead, CallPurpose::Conversion, true),
            'call_close' => $this->call($workflow, $lead, CallPurpose::Close),
            'call_close_now' => $this->call($workflow, $lead, CallPurpose::Close, true),
            'send_indication' => $this->sendDocument($lead, QuoteKind::Indication, $workflow),
            'send_quote' => $this->sendDocument($lead, QuoteKind::Final, $workflow),
            'book_survey' => $this->bookAppointment($lead, $data['starts_at'] ?? null, $scheduler, 'survey'),
            'mark_surveyed' => $this->markSurveyed($workflow, $lead, $data['notes'] ?? null),
            'book_appointment' => $this->bookAppointment($lead, $data['starts_at'] ?? null, $scheduler, 'installation'),
            'start_chase' => $this->startChase($workflow, $lead),
            'stop_chase' => $this->stopChase($workflow, $lead, $data['reason'] ?? 'Handmatig gestopt.'),
            'mark_lost' => $this->markLost($workflow, $lead, $data['reason'] ?? 'Handmatig als verloren gemarkeerd.'),
            'mark_won' => $this->markWon($workflow, $lead),
            'split_request' => $this->splitRequest($lead),
            default => $this->reopen($workflow, $lead),
        };

        return response()->json(['message' => $message]);
    }

    private function enrich(LeadWorkflow $workflow, Lead $lead): string
    {
        $workflow->enrich($lead);

        return 'De aanvraag is opnieuw doorgerekend.';
    }

    private function call(LeadWorkflow $workflow, Lead $lead, CallPurpose $purpose, bool $negeerBelvenster = false): string
    {
        $call = $workflow->scheduleCall($lead, $purpose, null, $negeerBelvenster);

        if ($call === null) {
            return 'Er kon geen gesprek ingepland worden. Controleer het telefoonnummer en de status van de lead.';
        }

        if ($negeerBelvenster) {
            return sprintf('%s staat klaar en gaat bij de eerstvolgende tik de deur uit, buiten het belvenster om.', $purpose->label());
        }

        return sprintf('%s ingepland voor %s.', $purpose->label(), $call->scheduled_for?->format('d-m-Y H:i') ?? 'direct');
    }

    private function sendDocument(Lead $lead, QuoteKind $kind, LeadWorkflow $workflow): string
    {
        if ($lead->email === null) {
            return sprintf('Er is geen e-mailadres bekend, dus de %s kan niet verstuurd worden.', $kind->noun());
        }

        // De grens die het hele traject draagt: een offerte is een aanbod
        // waaraan de klant rechten ontleent, dus die gaat niet de deur uit op
        // basis van een telefoongesprek alleen.
        if ($kind->isBinding() && ! $workflow->surveyDone($lead)) {
            return 'Er is nog geen opname geweest. Plan die eerst in, of markeer hem als afgerond; daarna kan de offerte de deur uit.';
        }

        SendQuoteJob::dispatch($lead->id, $kind);

        return sprintf('Er wordt een nieuwe %s opgesteld en gemaild.', $kind->noun());
    }

    private function markSurveyed(LeadWorkflow $workflow, Lead $lead, ?string $notes): string
    {
        $workflow->markSurveyed($lead, $notes);

        return 'De opname staat als afgerond genoteerd; de offerte kan nu verstuurd worden.';
    }

    private function bookAppointment(Lead $lead, ?string $startsAt, AppointmentScheduler $scheduler, string $kind): string
    {
        BookAppointmentJob::dispatch($lead->id, $startsAt, $kind);

        $wat = $kind === 'survey' ? 'De opname' : 'De installatie';
        $moment = $scheduler->parseLocal($startsAt);

        return $moment !== null
            ? sprintf('%s wordt vastgelegd op %s.', $wat, $moment->format('d-m-Y H:i'))
            : sprintf('%s wordt op het eerstvolgende vrije moment vastgelegd.', $wat);
    }

    private function startChase(LeadWorkflow $workflow, Lead $lead): string
    {
        return $workflow->startChase($lead) !== null
            ? 'De opvolgcadans is gestart.'
            : 'De cadans kon niet gestart worden voor deze lead.';
    }

    private function stopChase(LeadWorkflow $workflow, Lead $lead, string $reason): string
    {
        $workflow->stopChase($lead, $reason);

        return 'De opvolgcadans is gestopt.';
    }

    private function markLost(LeadWorkflow $workflow, Lead $lead, string $reason): string
    {
        $workflow->markLost($lead, $reason);

        return 'De lead staat nu op verloren.';
    }

    private function markWon(LeadWorkflow $workflow, Lead $lead): string
    {
        $lead->forceFill(['won_at' => now()])->save();
        $workflow->transition($lead, LeadStatus::Won);

        return 'De lead staat nu op gewonnen.';
    }

    /**
     * Maakt van de laatste herhaalde aanvraag een eigen lead.
     *
     * Een herhaalde aanvraag met andere maten kan een correctie zijn of een
     * tweede klus in hetzelfde pand. Dat verschil kan alleen een mens zien, dus
     * beslist de code het niet — hij bewaart de aanvraag en zet hem klaar om af
     * te splitsen.
     */
    private function splitRequest(Lead $lead): string
    {
        $melding = $lead->events()
            ->where('type', 'lead_duplicate')
            ->latest('id')
            ->first();

        $aanvraag = $melding?->payload['aanvraag'] ?? null;

        if (! is_array($aanvraag) || $aanvraag === []) {
            return 'Er is geen herhaalde aanvraag bewaard om af te splitsen.';
        }

        $nieuw = app(LeadIntake::class)->splitFrom($lead, $aanvraag, (string) ($melding?->payload['source'] ?? $lead->source));

        return sprintf('De aanvraag staat nu als eigen lead: %s.', $nieuw->name);
    }

    private function reopen(LeadWorkflow $workflow, Lead $lead): string
    {
        $lead->forceFill([
            'do_not_contact' => false,
            'lost_at' => null,
            'lost_reason' => null,
            'call_attempts' => 0,
            'email_attempts' => 0,
        ])->save();

        $workflow->transition($lead, LeadStatus::Enriched);

        return 'De lead is heropend; de tellers staan weer op nul.';
    }
}
