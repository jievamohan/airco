<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\PriceSource;
use App\Models\CatalogItem;
use RuntimeException;

/**
 * Zet een prijslijst van de leverancier om in catalogusregels.
 *
 * Twee dingen die deze importeur nadrukkelijk niet doet:
 *
 * - Hij overschrijft nooit een regel die de ondernemer zelf heeft aangepast.
 *   Wie in het dashboard een inkoopprijs of normtijd invult, weet iets wat de
 *   prijslijst niet weet; dat mag een deploy niet ongedaan maken.
 * - Hij verzint geen prijzen. Een regel zonder netto bedrag ("op aanvraag")
 *   komt niet in de catalogus, want een offerte met een gok erin is erger dan
 *   een offerte die een post mist.
 */
class PriceListImporter
{
    /** De rekenklassen waarop de offerte een unit uitkiest. */
    private const CAPACITY_CLASSES = [2.0, 2.5, 3.5, 5.0, 7.1];

    /**
     * Hoeveel een unit onder een rekenklasse mag zitten en die klasse toch
     * bedienen. Een 3,4 kW-set is de 3,5 kW-klasse; hem een maat groter geven
     * maakt de offerte duurder zonder dat de klant er iets voor krijgt.
     */
    private const CLASS_TOLERANCE = 0.96;

    /**
     * Boven deze grens hoort een unit niet meer bij het woningwerk waar de
     * agent op rekent, en laten we de klasse leeg: dan kiest de offerte hem
     * nooit vanzelf, maar staat hij er wel voor een offerte met de hand.
     */
    private const MAX_CLASS_KW = 8.9;

    /**
     * Aantal aansluitingen per Mitsubishi-buitenunit. Dit staat niet in de
     * prijslijst maar in de productdocumentatie van MHI; het is dus een
     * afleiding, en dat staat ook in de bronvermelding van de regel.
     */
    private const MHI_SCM_PORTS = [40 => 2, 45 => 3, 50 => 3, 60 => 4, 71 => 4, 80 => 5, 100 => 6, 125 => 6];

    /** Normtijden per soort, in monteursminuten. Nog niet uit eigen nacalculatie. */
    private const LABOUR_MINUTES = [
        PriceListRegistry::KIND_SET => 330,
        PriceListRegistry::KIND_OUTDOOR => 390,
        PriceListRegistry::KIND_INDOOR => 180,
    ];

    public function __construct(private readonly PriceListRegistry $registry) {}

    /**
     * @return array{aangemaakt: int, bijgewerkt: int, overgeslagen: int, ongemoeid: int, waarschuwingen: list<string>}
     */
    public function import(PriceListDefinition $definition, bool $dryRun = false): array
    {
        $margin = (float) config('agent.pricing.equipment_margin_pct', 45.0);
        $result = ['aangemaakt' => 0, 'bijgewerkt' => 0, 'overgeslagen' => 0, 'ongemoeid' => 0, 'waarschuwingen' => []];
        $gezien = [];

        foreach ($this->rows($definition) as $regelnr => $row) {
            $sectie = $definition->section($row['blad'], $row['sectie']);

            if ($sectie === null) {
                $result['overgeslagen']++;
                $result['waarschuwingen'][] = sprintf(
                    'Regel %d: sectie "%s" op blad "%s" is niet ingedeeld en is overgeslagen.',
                    $regelnr,
                    $row['sectie'],
                    $row['blad'],
                );

                continue;
            }

            if (($sectie['skip'] ?? false) === true) {
                $result['overgeslagen']++;

                continue;
            }

            $sku = trim($row['artikelnummer']);

            if ($sku === '') {
                $result['overgeslagen']++;

                continue;
            }

            if (isset($gezien[$sku])) {
                // Komt voor: in de KAISAI-lijst staat één artikelnummer bij twee
                // kleuren. De eerste wint, en dit wordt gemeld in plaats van
                // stilletjes de ene prijs door de andere te vervangen.
                $result['overgeslagen']++;
                $result['waarschuwingen'][] = sprintf(
                    'Regel %d: artikelnummer %s stond al eerder in deze lijst ("%s"); deze regel is overgeslagen.',
                    $regelnr,
                    $sku,
                    $gezien[$sku],
                );

                continue;
            }

            $gezien[$sku] = $row['product'];

            $attributes = $this->attributes($definition, $sectie, $row, $margin);

            if ($dryRun) {
                $result[CatalogItem::where('sku', $sku)->exists() ? 'bijgewerkt' : 'aangemaakt']++;

                continue;
            }

            $item = CatalogItem::firstOrNew(['sku' => $sku]);

            if ($item->exists && $item->price_source === PriceSource::Dashboard) {
                // Eigen invoer wint van de prijslijst.
                $result['ongemoeid']++;

                continue;
            }

            $nieuw = ! $item->exists;
            $item->fill($attributes)->save();

            $result[$nieuw ? 'aangemaakt' : 'bijgewerkt']++;
        }

        return $result;
    }

    /**
     * @param  array{kind?: string, series?: string, tier?: string, label?: string, note?: string, kop: string}  $sectie
     * @param  array{blad: string, sectie: string, artikelnummer: string, product: string, bruto_eur: string, netto_eur: string, korting_pct: string}  $row
     * @return array<string, mixed>
     */
    private function attributes(PriceListDefinition $definition, array $sectie, array $row, float $margin): array
    {
        $kind = $sectie['kind'] ?? PriceListRegistry::KIND_ACCESSORY;
        $product = trim($row['product']);

        // Panelen staan tussen de cassette-units maar zijn toebehoren, geen unit.
        if (str_starts_with(mb_strtolower($product), 'paneel')) {
            $kind = PriceListRegistry::KIND_ACCESSORY;
        }

        $capacity = $this->capacity($product, $definition->modelNumbersCarryCapacity);
        $ports = $this->ports($kind, $product, $sectie['series'] ?? '');

        return [
            'kind' => $kind,
            'name' => $this->name($definition, $sectie, $product),
            'description' => $this->description($row, $sectie),
            'brand' => $definition->brand,
            'series' => $sectie['series'] ?? null,
            'tier' => $sectie['tier'] ?? null,
            'capacity_kw' => $capacity,
            'capacity_class_kw' => $this->capacityClass($kind, $capacity),
            'ports' => $ports,
            'unit' => $kind === PriceListRegistry::KIND_SET ? 'set' : 'stuk',
            'cost_cents' => (int) round((float) $row['netto_eur'] * 100),
            'list_price_cents' => (int) round((float) $row['bruto_eur'] * 100),
            'purchase_discount_pct' => $row['korting_pct'] === '' ? null : (float) $row['korting_pct'],
            'margin_pct' => $margin,
            'labour_minutes' => self::LABOUR_MINUTES[$kind] ?? 0,
            'active' => true,
            'price_source' => PriceSource::PriceList->value,
            'price_list_ref' => $definition->ref,
            'priced_at' => $definition->receivedAt,
            'source_note' => sprintf('Netto inkoopprijs uit %s, ontvangen %s.', $definition->ref, $definition->receivedAt),
        ];
    }

    /**
     * De omschrijving in de KAISAI-lijst is alleen het vermogen ("3,5kW SET
     * WIT"); zonder de sectie ervoor weet je niet welk model je voor je hebt.
     * Bij Mitsubishi staat het type al in de omschrijving en zou de kop er
     * alleen maar twee keer bij komen te staan.
     *
     * @param  array{label?: string, kop: string}  $sectie
     */
    private function name(PriceListDefinition $definition, array $sectie, string $product): string
    {
        if (! $definition->nameNeedsSection) {
            return mb_substr($this->squash($product), 0, 160);
        }

        // In deze lijst is de omschrijving een specificatieregel; alles achter
        // de eerste pipe zijn kolomresten ("5,3kW | 2iu | 18000btu"). Die staan
        // voluit in de omschrijving van de regel, dus hier mogen ze weg.
        $product = $this->squash(explode('|', $product)[0]);

        // De koptekst draagt soms nog kolomresten achter een pipe; alleen het
        // eerste deel is de naam van de lijn.
        $lijn = trim($sectie['label'] ?? explode('|', $sectie['kop'])[0]);

        if ($lijn !== '' && ! str_contains(mb_strtolower($product), mb_strtolower($lijn))) {
            $product = $lijn.' '.$product;
        }

        return mb_substr($this->squash($product), 0, 160);
    }

    /** Dubbele spaties uit een prijslijst zijn opmaak, geen betekenis. */
    private function squash(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @param  array{blad: string, sectie: string, product: string}  $row
     * @param  array{note?: string}  $sectie
     */
    private function description(array $row, array $sectie): string
    {
        // De omschrijving uit de lijst gaat er voluit in, ook als de naam hem
        // heeft ingekort: dan is op de catalogpagina nog na te lezen wat er
        // precies besteld wordt.
        $delen = [sprintf('%s — %s', $row['blad'], $row['sectie']), $this->squash($row['product'])];

        if (isset($sectie['note'])) {
            $delen[] = $sectie['note'];
        }

        return mb_substr(implode(' · ', array_filter($delen)), 0, 500);
    }

    /**
     * Het vermogen uit de omschrijving, en anders uit het typenummer.
     */
    private function capacity(string $product, bool $modelNumbers): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*kW/i', $product, $m) === 1) {
            return round((float) str_replace(',', '.', $m[1]), 2);
        }

        // Geen afsluitende \b: in "SRK20ZS-WF" volgt op het getal meteen een
        // letter, en dan is er geen woordgrens om op te sluiten.
        if ($modelNumbers && preg_match('/\b(?:SRK|SRC|SCM|FDTC|FDE|FDT|SRF)\s?(\d{2,3})/i', $product, $m) === 1) {
            return round(((int) $m[1]) / 10, 2);
        }

        return null;
    }

    /**
     * De grootste rekenklasse die deze unit nog aankan.
     */
    private function capacityClass(string $kind, ?float $capacity): ?float
    {
        if ($capacity === null || $capacity > self::MAX_CLASS_KW) {
            return null;
        }

        // Een buitenunit van een multisplit wordt op aansluitingen gekozen,
        // niet op vermogen; een klasse zou daar alleen maar verwarren.
        if (! in_array($kind, [PriceListRegistry::KIND_SET, PriceListRegistry::KIND_INDOOR], true)) {
            return null;
        }

        $gevonden = null;

        foreach (self::CAPACITY_CLASSES as $class) {
            if ($capacity >= $class * self::CLASS_TOLERANCE) {
                $gevonden = $class;
            }
        }

        return $gevonden;
    }

    private function ports(string $kind, string $product, string $series): ?int
    {
        if ($kind !== PriceListRegistry::KIND_OUTDOOR) {
            return null;
        }

        // KAISAI zet het aantal binnenunits in de omschrijving: "5,3kW | 2iu".
        if (preg_match('/(\d+)\s*iu\b/i', $product, $m) === 1) {
            return (int) $m[1];
        }

        if ($series === 'mhi-scm' && preg_match('/\bSCM\s?(\d{2,3})/i', $product, $m) === 1) {
            return self::MHI_SCM_PORTS[(int) $m[1]] ?? null;
        }

        // SRC is de buitenunit van een single split: één binnenunit.
        if (str_starts_with($series, 'mhi-src')) {
            return 1;
        }

        return null;
    }

    /**
     * @return iterable<int, array{blad: string, sectie: string, artikelnummer: string, product: string, bruto_eur: string, netto_eur: string, korting_pct: string}>
     */
    private function rows(PriceListDefinition $definition): iterable
    {
        $path = $this->path($definition);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Prijslijst %s is niet te openen.', $path));
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if (! is_array($header)) {
                throw new RuntimeException(sprintf('Prijslijst %s is leeg.', $path));
            }

            $regelnr = 1;

            while (($row = fgetcsv($handle, escape: '')) !== false) {
                $regelnr++;

                if ($row === [null] || count($row) !== count($header)) {
                    continue;
                }

                /** @var array{blad: string, sectie: string, artikelnummer: string, product: string, bruto_eur: string, netto_eur: string, korting_pct: string} $combined */
                $combined = array_combine($header, array_map(static fn (?string $v): string => (string) $v, $row));

                yield $regelnr => $combined;
            }
        } finally {
            fclose($handle);
        }
    }

    public function path(PriceListDefinition $definition): string
    {
        return database_path('data/pricelists/'.$definition->file);
    }

    public function registry(): PriceListRegistry
    {
        return $this->registry;
    }
}
