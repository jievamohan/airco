<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PriceSource;
use App\Models\CatalogItem;
use Illuminate\Database\Seeder;

/**
 * Materiaal, toeslagen en bijkomende posten.
 *
 * De apparatuur komt uit de prijslijsten van de leverancier; zie
 * [PriceListSeeder]. Wat hier staat is het materiaal eromheen, en daarvoor
 * hebben we nog geen inkooplijst. Deze bedragen zijn dus nog afgeleid uit
 * marktonderzoek — ze staan als `provisional` in de catalogus en het dashboard
 * laat dat zien, zodat niemand ze aanziet voor eigen cijfers.
 * Onderbouwing: docs/research/pricing-baseline.md.
 *
 * De seeder is idempotent en overschrijft nooit bestaande records: zodra de
 * ondernemer in het dashboard een prijs of normtijd aanpast, blijft die staan.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $margin = (float) config('agent.pricing.material_margin_pct', 60.0);
        $source = 'Voorlopig — afgeleid uit marktonderzoek, zie docs/research/pricing-baseline.md';

        $materials = [
            ['MAT-LEIDING-5M', 'Koelleidingset 1/4"x3/8" 5 m, geïsoleerd', 'set', 4200, 0],
            ['MAT-LEIDING-EXTRA', 'Extra koelleiding boven 5 meter', 'meter', 900, 9],
            ['MAT-BEUGEL', 'Trillingsvrije wandbeugel buitenunit', 'stuk', 3500, 0],
            ['MAT-CONDENS', 'Condensafvoer inclusief slang', 'set', 1800, 0],
            ['MAT-CONDENSPOMP', 'Condenspomp bij ontbrekend natuurlijk afschot', 'stuk', 11000, 45],
            ['MAT-KABELGOOT', 'Leidinggoot en elektrakabel', 'meter', 700, 6],
            ['MAT-KERNBORING', 'Kernboring gevel Ø 65 mm', 'stuk', 1200, 30],
            ['MAT-KLEIN', 'Klein materiaal, stikstof, vacuüm en bevestiging', 'post', 3000, 0],
            ['MAT-GROEP', 'Extra elektragroep in de meterkast', 'stuk', 9500, 120],
            ['MAT-DAKDOORVOER', 'Waterdicht afgewerkte dakdoorvoer', 'stuk', 6500, 60],
        ];

        foreach ($materials as [$sku, $name, $unit, $cost, $minutes]) {
            $this->upsert([
                'sku' => $sku,
                'kind' => 'material',
                'name' => $name,
                'unit' => $unit,
                'cost_cents' => $cost,
                'margin_pct' => $margin,
                'labour_minutes' => $minutes,
                'source_note' => $source,
            ]);
        }

        $this->upsert([
            'sku' => 'MAT-FGAS',
            'kind' => 'material',
            'name' => 'F-gassenregistratie en afvoerbijdrage',
            'unit' => 'post',
            'cost_cents' => 1500,
            'margin_pct' => 0.0,
            'labour_minutes' => 0,
            'source_note' => $source,
        ]);

        $this->upsert([
            'sku' => 'SUR-HOOGWERK',
            'kind' => 'surcharge',
            'name' => 'Toeslag gevelwerk op hoogte (verdieping 2 of hoger)',
            'unit' => 'post',
            'cost_cents' => 4500,
            'margin_pct' => $margin,
            'labour_minutes' => 90,
            'source_note' => $source,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(array $attributes): void
    {
        $sku = $attributes['sku'];
        unset($attributes['sku']);
        $attributes['price_source'] = PriceSource::Provisional->value;

        CatalogItem::firstOrCreate(['sku' => $sku], $attributes);
    }
}
