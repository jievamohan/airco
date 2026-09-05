<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PriceSource;
use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Services\EntryPriceCheck;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Beheer van prijzen en normtijden.
 *
 * De catalogus kent drie soorten herkomst, en het overzicht laat per regel zien
 * welke: een netto inkoopprijs uit een leveranciersprijslijst, een bedrag dat
 * de ondernemer zelf heeft ingevuld, of een voorlopige afleiding uit
 * marktonderzoek. Dat laatste is wat er nog vervangen moet worden.
 */
class CatalogController extends Controller
{
    /** @var list<string> */
    private const EDITABLE = ['name', 'description', 'brand', 'cost_cents', 'margin_pct', 'labour_minutes', 'active'];

    public function index(Request $request, SettingsRepository $settings, EntryPriceCheck $check): JsonResponse
    {
        $filters = $request->validate([
            'kind' => ['nullable', 'string'],
            'tier' => ['nullable', 'in:budget,mid,premium'],
            'price_source' => ['nullable', 'in:provisional,pricelist,dashboard'],
            'active' => ['nullable', 'boolean'],
        ]);

        // Zonder filter alleen wat er nu meetelt. Vervallen regels blijven
        // bestaan omdat oude offertes ernaar wijzen, maar wie ze wil zien
        // vraagt er expliciet om.
        $active = array_key_exists('active', $filters) && $filters['active'] !== null
            ? (bool) $filters['active']
            : true;

        $query = CatalogItem::query()->orderBy('kind')->orderBy('tier')->orderBy('capacity_kw')->orderBy('sku');

        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        if (! empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        if (! empty($filters['price_source'])) {
            $query->where('price_source', $filters['price_source']);
        }

        $query->where('active', $active);

        return response()->json([
            'items' => $query->get()->map(static fn (CatalogItem $item): array => [
                'id' => $item->id,
                'sku' => $item->sku,
                'kind' => $item->kind,
                'name' => $item->name,
                'brand' => $item->brand,
                'series' => $item->series,
                'tier' => $item->tier?->value,
                'capacity_kw' => $item->capacity_kw,
                'capacity_class_kw' => $item->capacity_class_kw,
                'ports' => $item->ports,
                'unit' => $item->unit,
                'cost_cents' => $item->cost_cents,
                'list_price_cents' => $item->list_price_cents,
                'purchase_discount_pct' => $item->purchase_discount_pct,
                'margin_pct' => $item->margin_pct,
                'sell_price_cents' => $item->sellPriceCents(),
                'labour_minutes' => $item->labour_minutes,
                'active' => $item->active,
                'source_note' => $item->source_note,
                'price_source' => $item->price_source->value,
                'price_source_label' => $item->price_source->label(),
                'price_is_real' => $item->price_source->isReal(),
                'price_list_ref' => $item->price_list_ref,
                'priced_at' => $item->priced_at?->toDateString(),
            ])->all(),
            'herkomst' => $this->herkomst(),
            'pricing' => [
                'vat_rate' => $settings->float('agent.pricing.vat_rate', 21.0),
                'labour_sell_rate_cents' => $settings->int('agent.pricing.labour_sell_rate_cents', 7500),
                'labour_cost_rate_cents' => $settings->int('agent.pricing.labour_cost_rate_cents', 6500),
                'crew_size' => $settings->int('agent.pricing.crew_size', 2),
                'entry_price_cents' => $settings->int('agent.pricing.entry_price_cents', 89900),
                'entry_package_enabled' => $settings->bool('agent.pricing.entry_package_enabled', false),
                'minimum_margin_pct' => $settings->float('agent.pricing.minimum_margin_pct', 15.0),
                'default_tier' => $settings->string('agent.pricing.default_tier', 'mid'),
            ],
            'entry_price_check' => $check->run(),
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

        // Zodra de ondernemer een prijs of tijd wijzigt, is noch de prijslijst
        // noch de marktafleiding nog de bron van waarheid. Vanaf dat moment
        // laat een import deze regel met rust.
        if (array_intersect(array_keys($data), ['cost_cents', 'margin_pct', 'labour_minutes']) !== []) {
            $item->source_note = 'Aangepast in het dashboard op '.now()->format('d-m-Y');
            $item->price_source = PriceSource::Dashboard;
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
                'price_source' => $item->price_source->value,
                'price_source_label' => $item->price_source->label(),
                'price_is_real' => $item->price_source->isReal(),
            ],
        ]);
    }

    /**
     * Hoeveel van de catalogus op echte cijfers rust. Dat is de vraag achter
     * "kunnen we hierop offreren?", en het antwoord verandert met elke
     * prijslijst die erbij komt.
     *
     * @return array{echt: int, voorlopig: int, vervallen: int, prijslijsten: list<string>}
     */
    private function herkomst(): array
    {
        return [
            'echt' => CatalogItem::query()->active()->realPriced()->count(),
            'voorlopig' => CatalogItem::query()->active()->where('price_source', PriceSource::Provisional->value)->count(),
            'vervallen' => CatalogItem::query()->where('active', false)->count(),
            'prijslijsten' => CatalogItem::query()
                ->whereNotNull('price_list_ref')
                ->distinct()
                ->orderBy('price_list_ref')
                ->pluck('price_list_ref')
                ->all(),
        ];
    }
}
