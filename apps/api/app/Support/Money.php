<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nederlandse notatie voor bedragen, aantallen en tijdsduur. Staat op één plek
 * zodat een offerte, de mail erover en de pdf exact dezelfde getallen tonen.
 */
final class Money
{
    /** "€ 1.234,56" — met een vaste spatie, zodat het bedrag niet afbreekt. */
    public static function euro(int $cents): string
    {
        $formatted = number_format(abs($cents) / 100, 2, ',', '.');

        return ($cents < 0 ? '− € ' : '€ ').$formatted;
    }

    /**
     * "€ 351" — hele euro's. Voor bedragen die bij benadering zijn: centen
     * suggereren daar een precisie die er niet is.
     */
    public static function euroRound(int $cents): string
    {
        return ($cents < 0 ? '− € ' : '€ ').number_format(abs($cents) / 100, 0, ',', '.');
    }

    /** Aantallen zonder overbodige nullen: 1, 2,5 of 12,75. */
    public static function quantity(float $quantity): string
    {
        $formatted = number_format($quantity, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /** Percentages zonder overbodige nullen: 21% of 20,5%. */
    public static function percentage(float $percentage): string
    {
        return rtrim(rtrim(number_format($percentage, 1, ',', '.'), '0'), ',').'%';
    }

    /** Vermogen in kW met één decimaal: 3,5 kW. */
    public static function kilowatt(?float $kw): string
    {
        return number_format((float) $kw, 1, ',', '.').' kW';
    }

    /** Montageduur in hele en halve uren, zoals een monteur het zegt. */
    public static function hours(int $minutes): string
    {
        $hours = round($minutes / 60, 1);

        return number_format($hours, 1, ',', '.').' uur';
    }
}
