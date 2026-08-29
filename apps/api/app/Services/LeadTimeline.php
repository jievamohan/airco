<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadEvent;

/**
 * Enige plek waar timeline-regels ontstaan. Elke stap in de workflow schrijft
 * hier naartoe, zodat het dashboard een volledige en betrouwbare geschiedenis toont.
 */
class LeadTimeline
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Lead $lead,
        string $type,
        string $title,
        ?string $description = null,
        array $payload = [],
        string $actor = 'system',
        ?string $actorLabel = null,
    ): LeadEvent {
        return $lead->events()->create([
            'type' => $type,
            'actor' => $actor,
            'actor_label' => $actorLabel,
            'title' => $title,
            'description' => $description,
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => now(),
        ]);
    }
}
