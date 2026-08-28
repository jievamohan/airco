<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'space_size' => $this->space_size,
            'space_unit' => $this->space_unit,
            'rooms_count' => $this->rooms_count,
            'insulation' => $this->insulation,
            'building_year' => $this->building_year,
            'floor_level' => $this->floor_level,
            'wall_type' => $this->wall_type,
            'outdoor_unit_placement' => $this->outdoor_unit_placement,
            'pipe_length_m' => $this->pipe_length_m,
            'needs_condensate_pump' => $this->needs_condensate_pump,
            'needs_extra_group' => $this->needs_extra_group,
            'desired_start' => $this->desired_start?->toDateString(),
            'notes' => $this->notes,
            'estimated_kw' => $this->estimated_kw,
            'recommended_system' => $this->recommended_system?->value,
            'tier' => $this->tier?->value,
            'do_not_contact' => $this->do_not_contact,
            'call_attempts' => $this->call_attempts,
            'email_attempts' => $this->email_attempts,
            'next_action_at' => $this->next_action_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,
            'created_at' => $this->created_at?->toIso8601String(),

            'events' => $this->whenLoaded('events', fn () => $this->events->map(static fn ($event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'actor' => $event->actor,
                'title' => $event->title,
                'description' => $event->description,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])->all()),

            'calls' => $this->whenLoaded('calls', fn () => $this->calls->map(static fn ($call): array => [
                'id' => $call->id,
                'purpose' => $call->purpose->value,
                'purpose_label' => $call->purpose->label(),
                'attempt_no' => $call->attempt_no,
                'status' => $call->status,
                'outcome' => $call->outcome?->value,
                'outcome_label' => $call->outcome?->label(),
                'scheduled_for' => $call->scheduled_for?->toIso8601String(),
                'started_at' => $call->started_at?->toIso8601String(),
                'duration_seconds' => $call->duration_seconds,
                'summary' => $call->summary,
                'transcript' => $call->transcript,
            ])->all()),

            'quotes' => $this->whenLoaded('quotes', fn () => $this->quotes->map(static fn ($quote): array => [
                'id' => $quote->id,
                'number' => $quote->number,
                'version' => $quote->version,
                'status' => $quote->status,
                'total_cents' => $quote->total_cents,
                'subtotal_cents' => $quote->subtotal_cents,
                'vat_cents' => $quote->vat_cents,
                'onsite_minutes' => $quote->onsite_minutes,
                'valid_until' => $quote->valid_until?->toDateString(),
                'sent_at' => $quote->sent_at?->toIso8601String(),
                'items' => $quote->items->map(static fn ($item): array => [
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price_cents' => $item->unit_price_cents,
                    'line_total_cents' => $item->line_total_cents,
                ])->all(),
            ])->all()),

            'appointments' => $this->whenLoaded('appointments', fn () => $this->appointments->map(static fn ($appointment): array => [
                'id' => $appointment->id,
                'title' => $appointment->title,
                'provider' => $appointment->provider,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
                'status' => $appointment->status,
                'sync_error' => $appointment->sync_error,
            ])->all()),

            'emails' => $this->whenLoaded('emails', fn () => $this->emails->map(static fn ($email): array => [
                'id' => $email->id,
                'template' => $email->template,
                'subject' => $email->subject,
                'status' => $email->status,
                'sent_at' => $email->sent_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
