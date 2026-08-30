<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadListResource extends JsonResource
{
    /**
     * Publieke vorm van een lead in de lijst: alleen wat de tabel toont.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'city' => $this->city,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source,
            'estimated_kw' => $this->estimated_kw,
            'quote_total_cents' => $this->whenLoaded('latestQuote', fn () => $this->latestQuote?->total_cents),
            // Een richtbedrag en een aanbod zien er in een kolom identiek uit;
            // dit zegt welke van de twee er staat.
            'quote_binding' => $this->whenLoaded('latestQuote', fn () => $this->latestQuote?->isBinding()),
            'call_attempts' => $this->call_attempts,
            'next_action_at' => $this->next_action_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
