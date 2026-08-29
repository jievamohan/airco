<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogItem;
use Illuminate\Database\Seeder;

/**
 * Voorlopige catalogus, afgeleid uit openbaar marktonderzoek.
 * Volledige onderbouwing en bronnen: docs/research/pricing-baseline.md.
 *
 * De seeder is idempotent en overschrijft nooit bestaande records: zodra de
 * ondernemer in het dashboard een prijs of normtijd aanpast, blijft die staan.
 */
class CatalogSeeder extends Seeder
{
    private const EQUIPMENT_MARGIN = 45.0;

    private const MATERIAL_MARGIN = 60.0;

    /** Inkoopprijs in centen per kW-klasse en kwaliteitsklasse. */
    private const SINGLE_SPLIT_SETS = [
        // kW  => [budget, mid, premium]
        '2.0' => [36000, 52000, 73000],
        '2.5' => [38000, 56000, 78000],
        '3.5' => [45000, 65000, 90000],
        '5.0' => [62000, 85000, 115000],
        '7.1' => [85000, 115000, 150000],
    ];

    /** Inkoopprijs in centen per aantal aansluitingen. */
    private const MULTI_OUTDOOR = [
        2 => [70000, 90000, 110000],
        3 => [95000, 125000, 155000],
        4 => [125000, 160000, 200000],
        5 => [160000, 205000, 255000],
    ];

    /** Inkoopprijs in centen voor losse binnenunits. */
    private const INDOOR_UNITS = [
        '2.0' => [21000, 28000, 34000],
        '2.5' => [22000, 30000, 36000],
        '3.5' => [26000, 35000, 43000],
        '5.0' => [34000, 46000, 57000],
    ];

    private const TIERS = ['budget', 'mid', 'premium'];

    private const TIER_BRANDS = [
        'budget' => 'Gree / Haier-klasse',
        'mid' => 'LG / Fujitsu-klasse',
        'premium' => 'Daikin / Mitsubishi-klasse',
    ];

    public function run(): void
    {
        $source = 'Voorlopig — afgeleid uit marktonderzoek, zie docs/research/pricing-baseline.md';

        foreach (self::SINGLE_SPLIT_SETS as $kw => $prices) {
            foreach (self::TIERS as $index => $tier) {
                $this->upsert([
                    'sku' => sprintf('SET-%s-%s', str_replace('.', '', (string) $kw), strtoupper($tier)),
                    'kind' => 'equipment_set',
                    'name' => sprintf('Single split %s kW — %s', str_replace('.', ',', (string) $kw), self::TIER_BRANDS[$tier]),
                    'brand' => self::TIER_BRANDS[$tier],
                    'tier' => $tier,
                    'capacity_kw' => (float) $kw,
                    'unit' => 'set',
                    'cost_cents' => $prices[$index],
                    'margin_pct' => self::EQUIPMENT_MARGIN,
                    'labour_minutes' => 330, // basis single split 6,0 uur minus de kernboring, die een eigen regel is
                    'source_note' => $source,
                ]);
            }
        }

        foreach (self::MULTI_OUTDOOR as $ports => $prices) {
            foreach (self::TIERS as $index => $tier) {
                $this->upsert([
                    'sku' => sprintf('OUT-%dP-%s', $ports, strtoupper($tier)),
                    'kind' => 'equipment_outdoor',
                    'name' => sprintf('Multisplit buitenunit %d aansluitingen — %s', $ports, self::TIER_BRANDS[$tier]),
                    'brand' => self::TIER_BRANDS[$tier],
                    'tier' => $tier,
                    'ports' => $ports,
                    'unit' => 'stuk',
                    'cost_cents' => $prices[$index],
                    'margin_pct' => self::EQUIPMENT_MARGIN,
                    'labour_minutes' => 390, // basis multisplit buitenunit 7,0 uur minus de kernboring
                    'source_note' => $source,
                ]);
            }
        }

        foreach (self::INDOOR_UNITS as $kw => $prices) {
            foreach (self::TIERS as $index => $tier) {
                $this->upsert([
                    'sku' => sprintf('IN-%s-%s', str_replace('.', '', (string) $kw), strtoupper($tier)),
                    'kind' => 'equipment_indoor',
                    'name' => sprintf('Binnenunit wandmodel %s kW — %s', str_replace('.', ',', (string) $kw), self::TIER_BRANDS[$tier]),
                    'brand' => self::TIER_BRANDS[$tier],
                    'tier' => $tier,
                    'capacity_kw' => (float) $kw,
                    'unit' => 'stuk',
                    'cost_cents' => $prices[$index],
                    'margin_pct' => self::EQUIPMENT_MARGIN,
                    'labour_minutes' => 180, // per binnenunit 3,5 uur minus de kernboring
                    'source_note' => $source,
                ]);
            }
        }

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
                'margin_pct' => self::MATERIAL_MARGIN,
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
            'margin_pct' => self::MATERIAL_MARGIN,
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

        CatalogItem::firstOrCreate(['sku' => $sku], $attributes);
    }
}
