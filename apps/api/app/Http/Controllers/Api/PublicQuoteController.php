<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\LeadTimeline;
use Illuminate\Http\JsonResponse;

/**
 * Publieke, op token beveiligde weergave van een offerte.
 * Toont alleen wat de klant nodig heeft om de offerte te beoordelen.
 */
class PublicQuoteController extends Controller
{
    public function show(string $token, LeadTimeline $timeline): JsonResponse
    {
        $quote = Quote::with(['items', 'lead'])->where('public_token', $token)->first();

        if ($quote === null) {
            return response()->json(['message' => 'Niet gevonden.'], 404);
        }

        if ($quote->viewed_at === null) {
            $quote->forceFill(['viewed_at' => now(), 'status' => $quote->status === 'sent' ? 'viewed' : $quote->status])->save();
            $timeline->record($quote->lead, 'quote_viewed', $quote->kind->label().' bekeken', $quote->number);
        }

        return response()->json([
            'number' => $quote->number,
            'status' => $quote->status,
            'kind' => $quote->kind->value,
            'kind_label' => $quote->kind->label(),
            // Of de klant er rechten aan kan ontlenen. De publieke pagina hoort
            // daar geen twijfel over te laten: een indicatie is geen aanbod.
            'binding' => $quote->isBinding(),
            'customer_name' => $quote->lead->name,
            'system' => $quote->system_type,
            'total_kw' => $quote->total_kw,
            'onsite_hours' => round($quote->onsite_minutes / 60, 1),
            'valid_until' => $quote->valid_until?->toDateString(),
            'vat_rate' => $quote->vat_rate,
            'subtotal_cents' => $quote->subtotal_cents,
            'vat_cents' => $quote->vat_cents,
            'total_cents' => $quote->total_cents,
            'assumptions' => $quote->assumptions ?? [],
            'items' => $quote->items->map(static fn ($item): array => [
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'line_total_cents' => $item->line_total_cents,
            ])->all(),
        ]);
    }
}
