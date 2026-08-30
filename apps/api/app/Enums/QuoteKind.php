<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Het verschil tussen wat we op afstand kunnen zeggen en wat we mogen beloven.
 *
 * Aan een offerte kan de klant rechten ontlenen: hij is een aanbod, en zodra de
 * klant hem aanvaardt staat de prijs vast. Wat wij na een telefoongesprek weten
 * — een ruimtemaat, een geschatte leidinglengte, "de buitenunit kan wel aan de
 * achtergevel" — is daarvoor te mager. Vandaar twee documenten: een
 * vrijblijvende prijsindicatie vooraf, en pas na de opname ter plaatse een
 * offerte die bindt.
 */
enum QuoteKind: string
{
    case Indication = 'indication';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Indication => 'Prijsindicatie',
            self::Final => 'Offerte',
        };
    }

    /** Zonder lidwoord bruikbaar midden in een zin. */
    public function noun(): string
    {
        return match ($this) {
            self::Indication => 'prijsindicatie',
            self::Final => 'offerte',
        };
    }

    /**
     * Of de klant er rechten aan kan ontlenen. Alleen de offerte is een aanbod.
     */
    public function isBinding(): bool
    {
        return $this === self::Final;
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Indication => 'IND',
            self::Final => 'OFF',
        };
    }
}
