<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Waar de prijs van een catalogusregel vandaan komt.
 *
 * Dit is het verschil tussen "we denken dat het ongeveer zo veel kost" en "dit
 * staat op de inkoopfactuur". Een offerte die op afgeleide cijfers rust mag je
 * niet met dezelfde stelligheid de deur uit doen, dus het staat per regel vast
 * en het dashboard laat het zien.
 */
enum PriceSource: string
{
    /** Afgeleid uit marktonderzoek — de tijdelijke set van de proof of concept. */
    case Provisional = 'provisional';

    /** Overgenomen uit een prijslijst van de leverancier. */
    case PriceList = 'pricelist';

    /** Door de ondernemer zelf in het dashboard ingevuld. */
    case Dashboard = 'dashboard';

    public function label(): string
    {
        return match ($this) {
            self::Provisional => 'Voorlopig',
            self::PriceList => 'Prijslijst',
            self::Dashboard => 'Eigen invoer',
        };
    }

    /**
     * Rust deze prijs op een echt cijfer uit de eigen bedrijfsvoering?
     */
    public function isReal(): bool
    {
        return $this !== self::Provisional;
    }
}
