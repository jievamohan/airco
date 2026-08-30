<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CallPurpose;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\BookAppointmentJob;
use App\Jobs\SendQuoteJob;
use App\Models\Lead;
use App\Services\AppointmentScheduler;
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
        'enrich', 'call_qualification', 'call_qualification_now', 'call_conversion', 'send_quote',
        'book_appointment', 'start_chase', 'stop_chase', 'mark_lost', 'mark_won', 'reopen',
    ];

    public function __invoke(Request $request, string $uuid, LeadWorkflow $workflow, LeadTimeline $timeline, AppointmentScheduler $scheduler): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:'.implode(',', self::ACTIONS)],
            'reason' => ['nullable', 'string', 'max:200'],
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
            'send_quote' => $this->sendQuote($lead),
            'book_appointment' => $this->bookAppointment($lead, $data['starts_at'] ?? null, $scheduler),
            'start_chase' => $this->startChase($workflow, $lead),
            'stop_chase' => $this->stopChase($workflow, $lead, $data['reason'] ?? 'Handmatig gestopt.'),
            'mark_lost' => $this->markLost($workflow, $lead, $data['reason'] ?? 'Handmatig als verloren gemarkeerd.'),
            'mark_won' => $this->markWon($workflow, $lead),
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

    private function sendQuote(Lead $lead): string
    {
        if ($lead->email === null) {
            return 'Er is geen e-mailadres bekend, dus de offerte kan niet verstuurd worden.';
        }

        SendQuoteJob::dispatch($lead->id);

        return 'Er wordt een nieuwe offerteversie opgesteld en gemaild.';
    }

    private function bookAppointment(Lead $lead, ?string $startsAt, AppointmentScheduler $scheduler): string
    {
        BookAppointmentJob::dispatch($lead->id, $startsAt);

        $moment = $scheduler->parseLocal($startsAt);

        return $moment !== null
            ? sprintf('Afspraak wordt vastgelegd op %s.', $moment->format('d-m-Y H:i'))
            : 'Afspraak wordt op het eerstvolgende vrije moment vastgelegd.';
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
