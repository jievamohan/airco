<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Beheer van prijzen en normtijden. Hier vervangt de ondernemer de voorlopige
 * marktcijfers door de eigen inkoop- en calculatiegegevens.
 */
class CatalogController extends Controller
{
    /** @var list<string> */
    private const EDITABLE = ['name', 'description', 'brand', 'cost_cents', 'margin_pct', 'labour_minutes', 'active'];

    public function index(Request $request, SettingsRepository $settings): JsonResponse
    {
        $filters = $request->validate([
            'kind' => ['nullable', 'string'],
            'tier' => ['nullable', 'in:budget,mid,premium'],
        ]);

        $query = CatalogItem::query()->orderBy('kind')->orderBy('tier')->orderBy('capacity_kw')->orderBy('sku');

        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        if (! empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        return response()->json([
            'items' => $query->get()->map(static fn (CatalogItem $item): array => [
                'id' => $item->id,
                'sku' => $item->sku,
                'kind' => $item->kind,
                'name' => $item->name,
                'brand' => $item->brand,
                'tier' => $item->tier?->value,
                'capacity_kw' => $item->capacity_kw,
                'ports' => $item->ports,
                'unit' => $item->unit,
                'cost_cents' => $item->cost_cents,
                'margin_pct' => $item->margin_pct,
                'sell_price_cents' => $item->sellPriceCents(),
                'labour_minutes' => $item->labour_minutes,
                'active' => $item->active,
                'source_note' => $item->source_note,
            ])->all(),
            'pricing' => [
                'vat_rate' => $settings->float('agent.pricing.vat_rate', 21.0),
                'labour_sell_rate_cents' => $settings->int('agent.pricing.labour_sell_rate_cents', 7500),
                'crew_size' => $settings->int('agent.pricing.crew_size', 2),
                'minimum_job_cents' => $settings->int('agent.pricing.minimum_job_cents', 95000),
                'default_tier' => $settings->string('agent.pricing.default_tier', 'mid'),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $unknown = array_diff(array_keys($request->all()), self::EDITABLE);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Onbekende velden in het verzoek: '.implode(', ', $unknown).'.',
            ]);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:120'],
            'cost_cents' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'margin_pct' => ['sometimes', 'numeric', 'min:0', 'max:500'],
            'labour_minutes' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $item = CatalogItem::findOrFail($id);
        $item->fill($data);

        // Zodra de ondernemer een prijs of tijd wijzigt, is de marktafleiding
        // niet meer de bron van waarheid.
        if (array_intersect(array_keys($data), ['cost_cents', 'margin_pct', 'labour_minutes']) !== []) {
            $item->source_note = 'Aangepast in het dashboard op '.now()->format('d-m-Y');
        }

        $item->save();

        return response()->json([
            'item' => [
                'id' => $item->id,
                'sku' => $item->sku,
                'cost_cents' => $item->cost_cents,
                'margin_pct' => $item->margin_pct,
                'sell_price_cents' => $item->sellPriceCents(),
                'labour_minutes' => $item->labour_minutes,
                'active' => $item->active,
                'source_note' => $item->source_note,
            ],
        ]);
    }
}
