<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;

/**
 * Rekent door of de geadverteerde vanaf-prijs nog te maken is met de huidige
 * catalogus.
 *
 * Het antwoord op "kunnen we dit blijven adverteren?" verandert zodra de
 * inkoopprijzen of de normtijden wijzigen. Daarom is dit een berekening en geen
 * eenmalige notitie: het dashboard toont hem naast de catalogus.
 */
class EntryPriceCheck
{
    public function __construct(
        private readonly QuoteBuilder $quotes,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @return array{
     *     entry_price_cents: int,
     *     entry_package_enabled: bool,
     *     cheapest_total_cents: int,
     *     cheapest_cost_cents: int,
     *     margin_at_entry_price_pct: float,
     *     result_at_entry_price_cents: int,
     *     achievable: bool,
     *     minimum_margin_pct: float,
     *     break_even_total_cents: int,
     *     advised_entry_price_cents: int,
     *     message: string
     * }
     */
    public function run(): array
    {
        $entryPrice = $this->settings->int('agent.pricing.entry_price_cents', 89900);
        $vatRate = $this->settings->float('agent.pricing.vat_rate', 21.0);
        $minimumMargin = $this->settings->float('agent.pricing.minimum_margin_pct', 15.0);
        $packageEnabled = $this->settings->bool('agent.pricing.entry_package_enabled', false);

        // De goedkoopst mogelijke klus: kleinste ruimte, voordelige klasse,
        // standaard leidinglengte, begane grond, geen extra voorzieningen.
        $calc = $this->quotes->calculate($this->cheapestLead());

        $cost = $calc['cost_cents'];
        $entryExVat = (int) round($entryPrice / (1 + $vatRate / 100));
        $result = $entryExVat - $cost;
        $marginAtEntry = $entryExVat > 0 ? round($result / $entryExVat * 100, 2) : 0.0;

        // Wat zou de vanaf-prijs moeten zijn om de margedrempel te halen?
        $advisedExVat = $minimumMargin < 100
            ? (int) ceil($cost / (1 - $minimumMargin / 100))
            : $cost;
        $advised = (int) round($advisedExVat * (1 + $vatRate / 100));

        $achievable = $marginAtEntry >= $minimumMargin;

        return [
            'entry_price_cents' => $entryPrice,
            'entry_package_enabled' => $packageEnabled,
            'cheapest_total_cents' => $calc['total_cents'],
            'cheapest_cost_cents' => $cost,
            'margin_at_entry_price_pct' => $marginAtEntry,
            'result_at_entry_price_cents' => $result,
            'achievable' => $achievable,
            'minimum_margin_pct' => $minimumMargin,
            'break_even_total_cents' => (int) round($cost * (1 + $vatRate / 100)),
            'advised_entry_price_cents' => $advised,
            'message' => $this->message($achievable, $entryPrice, $result, $marginAtEntry, $advised),
        ];
    }

    private function cheapestLead(): Lead
    {
        // Niet opslaan: dit is een rekenvoorbeeld, geen lead.
        return new Lead([
            'name' => 'Rekenvoorbeeld instapklus',
            'space_size' => 12,
            'space_unit' => 'm2',
            'rooms_count' => 1,
            'building_year' => 2015,
            'pipe_length_m' => 5,
            'floor_level' => 0,
            'tier' => 'budget',
        ]);
    }

    private function message(bool $achievable, int $entryPrice, int $result, float $margin, int $advised): string
    {
        $euro = static fn (int $cents): string => '€ '.number_format($cents / 100, 2, ',', '.');

        if ($achievable) {
            return sprintf(
                'De vanaf-prijs van %s is haalbaar: op de goedkoopst mogelijke klus houdt u %s over (%s%% marge).',
                $euro($entryPrice),
                $euro($result),
                number_format($margin, 1, ',', '.'),
            );
        }

        if ($result < 0) {
            return sprintf(
                'De vanaf-prijs van %s ligt onder de kostprijs: elke instapklus kost u %s. Vanaf %s haalt u de margedrempel.',
                $euro($entryPrice),
                $euro(abs($result)),
                $euro($advised),
            );
        }

        return sprintf(
            'De vanaf-prijs van %s levert %s%% marge en blijft daarmee onder de drempel. Vanaf %s haalt u die wel.',
            $euro($entryPrice),
            number_format($margin, 1, ',', '.'),
            $euro($advised),
        );
    }
}
