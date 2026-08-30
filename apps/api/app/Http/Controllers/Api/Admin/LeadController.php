<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Resources\LeadDetailResource;
use App\Http\Resources\LeadListResource;
use App\Models\Lead;
use App\Services\LeadTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        // Op laatste aanvraag, niet op aanmaakdatum: een klant die het
        // formulier opnieuw invult wordt anders begraven onder leads waar niets
        // gebeurt. Voor een lead die maar één keer aanvroeg is het dezelfde
        // volgorde als eerst.
        $query = Lead::query()
            ->with('latestQuote')
            ->orderByRaw('COALESCE(last_request_at, created_at) DESC')
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->whereIn('status', explode(',', $filters['status']));
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('city', 'like', $term);
            });
        }

        return LeadListResource::collection(
            $query->paginate($filters['per_page'] ?? 25)->withQueryString(),
        );
    }

    public function show(string $uuid): LeadDetailResource
    {
        $lead = Lead::with(['events', 'calls', 'quotes.items', 'appointments', 'emails'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return new LeadDetailResource($lead);
    }

    public function update(UpdateLeadRequest $request, string $uuid, LeadTimeline $timeline): LeadDetailResource
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        $changes = $request->leadAttributes();

        $lead->fill($changes)->save();

        $timeline->record(
            $lead,
            'lead_updated',
            'Gegevens handmatig aangepast',
            implode(', ', array_keys($changes)),
            $changes,
            'user',
            (string) $request->user()?->name,
        );

        return new LeadDetailResource(
            $lead->fresh(['events', 'calls', 'quotes.items', 'appointments', 'emails']) ?? $lead,
        );
    }

    /**
     * Beschikbare statussen voor de filters in het dashboard.
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'statuses' => array_map(
                static fn (LeadStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                LeadStatus::cases(),
            ),
        ]);
    }
}
