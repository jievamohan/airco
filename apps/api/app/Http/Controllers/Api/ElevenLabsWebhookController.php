<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CallOutcome;
use App\Enums\CallPurpose;
use App\Http\Controllers\Controller;
use App\Jobs\BookAppointmentJob;
use App\Jobs\SendQuoteJob;
use App\Models\Call;
use App\Services\LeadWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Ontvangt het resultaat van een gesprek van ElevenLabs.
 *
 * De handtekening is al gecontroleerd door de middleware; hier gaat het alleen
 * nog over het vertalen van de payload naar een uitkomst en de vervolgstap.
 */
class ElevenLabsWebhookController extends Controller
{
    public function __invoke(Request $request, LeadWorkflow $workflow): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        /** @var array<string, mixed> $data */
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $conversationId = isset($data['conversation_id']) ? (string) $data['conversation_id'] : null;
        $call = $this->resolveCall($conversationId, $data);

        if ($call === null) {
            Log::warning('Webhook zonder herkenbaar gesprek ontvangen', ['conversation_id' => $conversationId]);

            return response()->json(['message' => 'Gesprek niet gevonden.'], 404);
        }

        if ($call->status === 'completed') {
            return response()->json(['message' => 'Al verwerkt.']);
        }

        $collected = $this->collectedFields($data);
        $outcome = $this->resolveOutcome($data, $collected);

        $workflow->completeCall(
            $call,
            $outcome,
            $this->transcriptText($data),
            $this->summaryText($data),
            $collected,
        );

        $this->dispatchFollowUp($call->refresh(), $outcome, $collected);

        return response()->json(['message' => 'Verwerkt.']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCall(?string $conversationId, array $data): ?Call
    {
        if ($conversationId !== null) {
            $call = Call::with('lead')->where('conversation_id', $conversationId)->first();

            if ($call !== null) {
                return $call;
            }
        }

        // Sommige gesprekken komen terug met alleen het telefonie-id.
        $callSid = isset($data['call_sid']) ? (string) $data['call_sid'] : null;

        if ($callSid !== null) {
            return Call::with('lead')->where('provider_call_id', $callSid)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function collectedFields(array $data): array
    {
        $analysis = is_array($data['analysis'] ?? null) ? $data['analysis'] : [];
        $results = is_array($analysis['data_collection_results'] ?? null) ? $analysis['data_collection_results'] : [];

        $collected = [];

        foreach ($results as $key => $entry) {
            $value = is_array($entry) ? ($entry['value'] ?? null) : $entry;

            if ($value !== null && $value !== '') {
                $collected[(string) $key] = $value;
            }
        }

        return $collected;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $collected
     */
    private function resolveOutcome(array $data, array $collected): CallOutcome
    {
        // Expliciete uitkomst uit de agent wint van afgeleide signalen.
        if (isset($collected['outcome']) && is_string($collected['outcome'])) {
            $outcome = CallOutcome::tryFrom($collected['outcome']);

            if ($outcome !== null) {
                return $outcome;
            }
        }

        $status = strtolower((string) ($data['status'] ?? ''));

        if (in_array($status, ['no_answer', 'no-answer', 'busy', 'failed', 'canceled'], true)) {
            return match ($status) {
                'busy' => CallOutcome::Busy,
                'failed', 'canceled' => CallOutcome::Failed,
                default => CallOutcome::NoAnswer,
            };
        }

        $analysis = is_array($data['analysis'] ?? null) ? $data['analysis'] : [];
        $successful = $analysis['call_successful'] ?? null;

        $duration = (int) ($data['metadata']['call_duration_secs'] ?? $data['call_duration_secs'] ?? 0);

        if ($duration > 0 && $duration < 10) {
            return CallOutcome::NoAnswer;
        }

        if ($successful === 'failure') {
            return CallOutcome::Declined;
        }

        if (filter_var($collected['appointment_agreed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return CallOutcome::AppointmentBooked;
        }

        return $duration > 0 ? CallOutcome::Answered : CallOutcome::NoAnswer;
    }

    /**
     * Bepaalt wat er na het gesprek moet gebeuren.
     *
     * @param  array<string, mixed>  $collected
     */
    private function dispatchFollowUp(Call $call, CallOutcome $outcome, array $collected): void
    {
        if (! $outcome->reachedLead()) {
            return;
        }

        if ($outcome === CallOutcome::AppointmentBooked) {
            $preferred = isset($collected['appointment_start']) ? (string) $collected['appointment_start'] : null;
            BookAppointmentJob::dispatch($call->lead_id, $preferred);

            return;
        }

        if ($call->purpose === CallPurpose::Qualification && $outcome === CallOutcome::Answered) {
            SendQuoteJob::dispatch($call->lead_id);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transcriptText(array $data): ?string
    {
        $transcript = $data['transcript'] ?? null;

        if (is_string($transcript)) {
            return $transcript;
        }

        if (! is_array($transcript)) {
            return null;
        }

        $lines = [];

        foreach ($transcript as $turn) {
            if (! is_array($turn)) {
                continue;
            }

            $role = (string) ($turn['role'] ?? 'onbekend');
            $message = (string) ($turn['message'] ?? '');

            if ($message === '') {
                continue;
            }

            $lines[] = sprintf('%s: %s', $role === 'agent' ? 'Agent' : 'Klant', $message);
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function summaryText(array $data): ?string
    {
        $analysis = is_array($data['analysis'] ?? null) ? $data['analysis'] : [];
        $summary = $analysis['transcript_summary'] ?? null;

        return is_string($summary) && $summary !== '' ? $summary : null;
    }
}
