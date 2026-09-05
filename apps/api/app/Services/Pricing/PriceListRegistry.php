<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use RuntimeException;

/**
 * De prijslijsten die we van de leverancier hebben gekregen, en hoe de secties
 * daarin zich verhouden tot onze catalogus.
 *
 * De bestanden onder `database/data/pricelists/` zijn een letterlijke uitdraai
 * van de aangeleverde werkbladen: artikelnummer, sectie, omschrijving, bruto,
 * netto en korting. Er is bewust niets aan gerekend — de vertaalslag staat
 * hier, waar je hem kunt nalezen en aanpassen zonder de brondata aan te raken.
 *
 * Komt er een nieuwe lijst, dan zet je het nieuwe CSV-bestand ernaast en voeg
 * je hier een definitie toe. Bestaande artikelnummers krijgen dan de nieuwe
 * prijs; wat de ondernemer zelf heeft aangepast blijft staan.
 */
class PriceListRegistry
{
    public const KIND_SET = 'equipment_set';

    public const KIND_OUTDOOR = 'equipment_outdoor';

    public const KIND_INDOOR = 'equipment_indoor';

    public const KIND_HEATPUMP = 'equipment_heatpump';

    public const KIND_ACCESSORY = 'equipment_accessory';

    /** @return list<PriceListDefinition> */
    public function all(): array
    {
        return [$this->mhi2026(), $this->kaisai2026()];
    }

    public function get(string $key): PriceListDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->key === $key) {
                return $definition;
            }
        }

        throw new RuntimeException(sprintf('Onbekende prijslijst "%s".', $key));
    }

    /**
     * Mitsubishi Heavy Industries — de premiumlijn.
     */
    private function mhi2026(): PriceListDefinition
    {
        $set = self::KIND_SET;
        $indoor = self::KIND_INDOOR;
        $outdoor = self::KIND_OUTDOOR;

        return new PriceListDefinition(
            key: 'mhi-2026',
            file: 'mhi-2026.csv',
            supplier: 'Airco Techniek B.V.',
            brand: 'Mitsubishi Heavy Industries',
            ref: 'MHI Prijslijst 2026, versie 0526',
            receivedAt: '2026-09-03',
            modelNumbersCarryCapacity: true,
            sections: [
                'MHI Single split' => [
                    'Mitsubishi onderdelen' => ['kind' => self::KIND_ACCESSORY, 'series' => 'mhi-onderdelen'],
                    'SRK-ZS-WF | Wandunits sets | WIT | incl WiFi' => ['kind' => $set, 'series' => 'mhi-srk-zs-wf', 'tier' => 'premium'],
                    'SRK-ZS-WFT | Wandunits sets | TITANIUM' => ['kind' => $set, 'series' => 'mhi-srk-zs-wft'],
                    'SRK-ZSX-WF | Wandunits sets | WIT' => ['kind' => $set, 'series' => 'mhi-srk-zsx-wf'],
                    'SRK-ZSX-WFT | Wandunits sets | TITANIUM' => ['kind' => $set, 'series' => 'mhi-srk-zsx-wft'],
                    'SRK-ZS-BLACK | Wandunits sets | BLACK' => ['kind' => $set, 'series' => 'mhi-srk-zs-black'],
                    'SRK-ZTL-W | Wandunits sets' => ['kind' => $set, 'series' => 'mhi-srk-ztl-w'],
                    'SRF-ZS-W | Vloerunits sets' => ['kind' => $set, 'series' => 'mhi-srf-zs-w-set'],
                    'FDTC-VH |Cassette 60x60 sets' => ['kind' => $set, 'series' => 'mhi-fdtc-set'],
                    'FDE-VH | Plafondonderbouw sets' => ['kind' => $set, 'series' => 'mhi-fde-set'],
                    'FDT-VH |Cassette 95x95 sets' => ['kind' => $set, 'series' => 'mhi-fdt-set'],
                ],
                'MHI Multi split' => [
                    'SCM | Multisplit buitenunits' => ['kind' => $outdoor, 'series' => 'mhi-scm'],
                    // SRC is de buitenunit van een single split: één aansluiting.
                    // Hij staat in de catalogus om losse vervanging te kunnen
                    // offreren, niet om een multisplit mee te bouwen.
                    'SRC-ZS-W | Buitenunits' => ['kind' => $outdoor, 'series' => 'mhi-src-zs-w'],
                    'SRC-ZSX-W | Buitenunits' => ['kind' => $outdoor, 'series' => 'mhi-src-zsx-w'],
                    'SRK-ZS-WF | Wandunits | WIT' => ['kind' => $indoor, 'series' => 'mhi-srk-zs-wf', 'tier' => 'premium'],
                    'SRK-ZS-WFT | Wandunits | TITANIUM' => ['kind' => $indoor, 'series' => 'mhi-srk-zs-wft'],
                    'SRK-ZSX-WF | Wandunits | WIT' => ['kind' => $indoor, 'series' => 'mhi-srk-zsx-wf'],
                    'SRK-ZSX-WFT | Wandunits | TITANIUM' => ['kind' => $indoor, 'series' => 'mhi-srk-zsx-wft'],
                    'SRK-ZS-BLACK | Wandunits | BLACK' => ['kind' => $indoor, 'series' => 'mhi-srk-zs-black'],
                    // Dezelfde artikelnummers als de ZTL-sets hierboven, en de
                    // koptekst zegt er zelf bij dat ze niet op een multisplit
                    // passen. Eén keer opnemen is genoeg.
                    'SRK-ZTL-W | Wandunits | NIET GESCHIKT VOOR MULTISPLIT!' => ['skip' => true],
                    'SRF-ZS-W | Vloerunits' => ['kind' => $indoor, 'series' => 'mhi-srf-zs-w'],
                    'FDTC | 60x60 | Cassette units' => ['kind' => $indoor, 'series' => 'mhi-fdtc'],
                ],
            ],
        );
    }

    /**
     * KAISAI — de voordelige en de middenklasse.
     */
    private function kaisai2026(): PriceListDefinition
    {
        $set = self::KIND_SET;
        $indoor = self::KIND_INDOOR;
        $heatpump = self::KIND_HEATPUMP;

        return new PriceListDefinition(
            key: 'kaisai-2026',
            file: 'kaisai-2026.csv',
            supplier: 'Airco Techniek B.V.',
            brand: 'KAISAI',
            ref: 'KAISAI Prijslijst 2026-6, versie 0526',
            receivedAt: '2026-09-03',
            nameNeedsSection: true,
            sections: [
                'KAISAI Single split' => [
                    'KAISAI EVO' => ['kind' => $set, 'series' => 'kaisai-evo', 'tier' => 'budget'],
                    'KAISAI FLY*' => ['kind' => $set, 'series' => 'kaisai-fly'],
                    'KAISAI FLY+' => ['kind' => $set, 'series' => 'kaisai-fly-plus'],
                    'KAISAI ICE WHITE' => ['kind' => $set, 'series' => 'kaisai-ice-white', 'tier' => 'mid'],
                    'KAISAI ICE BLACK' => ['kind' => $set, 'series' => 'kaisai-ice-black'],
                    'KAISAI PRO-HEAT+ WHITE' => ['kind' => $set, 'series' => 'kaisai-pro-heat-plus-white'],
                    'KAISAI PRO-HEAT+ BLACK' => ['kind' => $set, 'series' => 'kaisai-pro-heat-plus-black'],
                    'KAISAI GEO*' => ['kind' => $set, 'series' => 'kaisai-geo'],
                    'KAISAI GEO+ WHITE' => ['kind' => $set, 'series' => 'kaisai-geo-plus-white'],
                    'KAISAI GEO+ GREY' => ['kind' => $set, 'series' => 'kaisai-geo-plus-grey'],
                    'KAISAI NORDIC*' => ['kind' => $set, 'series' => 'kaisai-nordic'],
                    'KAISAI ART WHITE' => ['kind' => $set, 'series' => 'kaisai-art-white'],
                    'KAISAI ART BLACK' => ['kind' => $set, 'series' => 'kaisai-art-black'],
                    'KAISAI WAND & VLOER*' => ['kind' => $set, 'series' => 'kaisai-wand-vloer-set'],
                    'KAISAI PLAFOND ONDERBOUW' => ['kind' => $set, 'series' => 'kaisai-plafond'],
                    'KAISAI SLIM DUCT*' => ['kind' => $set, 'series' => 'kaisai-slim-duct-set'],
                    'KAISAI CASSETTE 60x60 KCA4U' => ['kind' => $set, 'series' => 'kaisai-cassette-60-set'],
                    'KAISAI CASSETTE 90x90 KCD' => ['kind' => $set, 'series' => 'kaisai-cassette-90-set'],
                ],
                'KAISAI Multisplit' => [
                    'MULTI-SPLIT OUTDOOR UNITS' => ['kind' => self::KIND_OUTDOOR, 'series' => 'kaisai-multi', 'label' => 'KAISAI multisplit buitenunit'],
                    'KAISAI FLY' => ['kind' => $indoor, 'series' => 'kaisai-fly'],
                    'KAISAI FLY+' => ['kind' => $indoor, 'series' => 'kaisai-fly-plus', 'tier' => 'budget'],
                    'KAISAI ICE WHITE' => ['kind' => $indoor, 'series' => 'kaisai-ice-white', 'tier' => 'mid'],
                    'KAISAI ICE BLACK' => ['kind' => $indoor, 'series' => 'kaisai-ice-black'],
                    'KAISAI GEO' => ['kind' => $indoor, 'series' => 'kaisai-geo'],
                    'KAISAI WAND & VLOER' => ['kind' => $indoor, 'series' => 'kaisai-wand-vloer'],
                    'KAISAI CASSETTE 60x60 KCA4U' => ['kind' => $indoor, 'series' => 'kaisai-cassette-60'],
                    'KAISAI SLIM DUCT' => ['kind' => $indoor, 'series' => 'kaisai-slim-duct'],
                ],
                'KAISAI Warmtepompen' => [
                    'Monoblock 6-10kW' => ['label' => 'KAISAI monoblock warmtepomp', 'kind' => $heatpump, 'series' => 'kaisai-monoblock-khoa'],
                    'Monoblock 8-10kW' => ['label' => 'KAISAI monoblock warmtepomp', 'kind' => $heatpump, 'series' => 'kaisai-monoblock-khon'],
                    'Monoblock 12kW*' => ['label' => 'KAISAI monoblock warmtepomp', 'kind' => $heatpump, 'series' => 'kaisai-monoblock-khy'],
                    'Monoblock 22-30kW' => ['label' => 'KAISAI monoblock warmtepomp', 'kind' => $heatpump, 'series' => 'kaisai-monoblock-khc'],
                    'Artic Power 65-140kW' => ['label' => 'KAISAI Artic Power warmtepomp', 'kind' => $heatpump, 'series' => 'kaisai-artic-power'],
                    'Hydraulic module' => ['label' => 'KAISAI hydraulische module', 'kind' => self::KIND_ACCESSORY, 'series' => 'kaisai-warmtepomp-toebehoren'],
                    'DHW heating tank' => ['label' => 'KAISAI boilervat', 'kind' => self::KIND_ACCESSORY, 'series' => 'kaisai-warmtepomp-toebehoren'],
                    'KAISAI X LITE' => ['kind' => self::KIND_ACCESSORY, 'series' => 'kaisai-warmtepomp-toebehoren'],
                ],
            ],
        );
    }
}
