<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use InvalidArgumentException;

/**
 * Beschrijft één prijslijst van een leverancier: waar het bestand staat, van
 * wie hij is, en wat elke sectie in dat bestand voorstelt.
 *
 * De prijzen komen ongewijzigd uit de lijst; wat hier staat is de vertaling
 * naar onze catalogus. Die twee bewust uit elkaar houden scheelt bij de
 * volgende lijst: dan verandert het CSV-bestand en blijft deze indeling staan.
 */
final class PriceListDefinition
{
    /**
     * @param  array<string, array<string, array{kind?: string, series?: string, tier?: string, skip?: bool, label?: string, note?: string}>>  $sections
     *                                                                                                                                                    Per blad, per sectiekop uit de prijslijst. `skip` laat een sectie weg,
     *                                                                                                                                                    `label` vervangt de koptekst als naam van de productlijn.
     * @param  bool  $modelNumbersCarryCapacity
     *                                           Mitsubishi zet het vermogen in het typenummer (SRK35 = 3,5 kW) en niet
     *                                           altijd in de omschrijving. KAISAI doet dat niet, en daar zou dezelfde
     *                                           regel op artikelcodes stuklopen.
     * @param  bool  $nameNeedsSection
     *                                  In de KAISAI-lijst staat in de omschrijving alleen het vermogen
     *                                  ("3,5kW SET WIT"); welk model je voor je hebt staat in de sectiekop.
     *                                  Bij Mitsubishi staat het model al in de omschrijving zelf.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $file,
        public readonly string $supplier,
        public readonly string $brand,
        public readonly string $ref,
        public readonly string $receivedAt,
        public readonly array $sections,
        public readonly bool $modelNumbersCarryCapacity = false,
        public readonly bool $nameNeedsSection = false,
    ) {
        if ($key === '' || $file === '') {
            throw new InvalidArgumentException('Een prijslijst heeft een sleutel en een bestandsnaam nodig.');
        }
    }

    /**
     * De indeling van een sectie, met de koptekst erbij: die is de naam van de
     * productlijn zolang er geen `label` is opgegeven.
     *
     * @return array{kind?: string, series?: string, tier?: string, skip?: bool, label?: string, note?: string, kop: string}|null
     */
    public function section(string $sheet, string $section): ?array
    {
        $found = $this->sections[$sheet][$section] ?? null;

        return $found === null ? null : $found + ['kop' => $section];
    }
}
