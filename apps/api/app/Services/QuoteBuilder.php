<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QuoteKind;
use App\Enums\SystemType;
use App\Enums\Tier;
use App\Models\CatalogItem;
use App\Models\Lead;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bouwt een offerte op uit de catalogus.
 *
 * Alle bedragen zijn integer centen exclusief btw tot het eindtotaal;
 * er wordt nergens met floats gerekend aan geld.
 */
class QuoteBuilder
{
    /** Aantal meter koelleiding dat in de basisprijs zit. */
    private const INCLUDED_PIPE_M = 5;

    public function __construct(
        private readonly SizingCalculator $sizing,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Berekent de offerte zonder hem op te slaan. Wordt ook gebruikt om de
     * voice agent tijdens het gesprek een prijsindicatie te laten noemen.
     *
     * @return array{
     *     lines: list<array{kind: string, sku: string|null, description: string, quantity: float, unit: string, unit_cost_cents: int, margin_pct: float, unit_price_cents: int, line_total_cents: int, labour_minutes: int, catalog_item_id: int|null}>,
     *     subtotal_cents: int, vat_cents: int, total_cents: int, discount_cents: int,
     *     cost_cents: int, margin_pct: float, margin_warning: bool,
     *     vat_rate: float, labour_minutes: int, onsite_minutes: int,
     *     system: SystemType, tier: Tier, total_kw: float, assumptions: list<string>
     * }
     */
    public function calculate(Lead $lead, ?Tier $tier = null): array
    {
        $sizing = $this->sizing->forLead($lead);
        // Een expliciete klasse hoort alleen bij het doorrekenen van een
        // alternatief; normaal komt hij van de lead, en anders van de standaard.
        $tier ??= $lead->tier ?? Tier::from($this->settings->string('agent.pricing.default_tier', 'mid'));
        $system = $sizing['system'];
        $units = max(1, $sizing['indoor_units']);
        $kw = $sizing['kw'];

        $assumptions = [];
        $lines = [];
        $labourMinutes = 0;

        if ($lead->space_size === null) {
            $assumptions[] = 'Geen ruimtemaat opgegeven; gerekend met een standaard woonkamer en 3,5 kW.';
        } else {
            $assumptions[] = sprintf(
                'Koellast berekend op %s m³ × %d W/m³ = %s kW per ruimte.',
                number_format((float) ($sizing['volume_m3'] ?? 0), 1, ',', '.'),
                $sizing['factor'],
                number_format($kw, 1, ',', '.'),
            );
        }

        // --- Apparatuur ---
        if ($system === SystemType::SingleSplit) {
            $set = $this->findEquipment('equipment_set', $tier, $kw);
            $lines[] = $this->line($set, 1.0, sprintf('%s — single split, buiten- en binnenunit', $set->name));
            $labourMinutes += $set->labour_minutes;
        } else {
            $outdoor = $this->findOutdoor($tier, $units);
            $lines[] = $this->line($outdoor, 1.0, sprintf('%s — %d aansluitingen', $outdoor->name, $outdoor->ports ?? $units));
            $labourMinutes += $outdoor->labour_minutes;

            $indoor = $this->findEquipment('equipment_indoor', $tier, $kw);
            $lines[] = $this->line($indoor, (float) $units, $indoor->name);
            $labourMinutes += $indoor->labour_minutes * $units;

            $assumptions[] = sprintf('Multisplit met %d binnenunits omdat er %d ruimtes zijn opgegeven.', $units, $units);
        }

        // --- Basismaterialen, per binnenunit ---
        foreach (['MAT-LEIDING-5M', 'MAT-BEUGEL', 'MAT-CONDENS', 'MAT-KERNBORING', 'MAT-KLEIN'] as $sku) {
            $item = $this->bySku($sku);
            $qty = $sku === 'MAT-BEUGEL' ? 1.0 : (float) $units;
            $lines[] = $this->line($item, $qty);
            $labourMinutes += (int) round($item->labour_minutes * $qty);
        }

        // --- Extra leidinglengte ---
        $pipe = $lead->pipe_length_m;

        if ($pipe === null) {
            $assumptions[] = sprintf('Uitgegaan van %d meter koelleiding per binnenunit; meerlengte wordt nagerekend bij de opname.', self::INCLUDED_PIPE_M);
        } elseif ($pipe > self::INCLUDED_PIPE_M) {
            $extra = (float) ($pipe - self::INCLUDED_PIPE_M) * $units;
            $item = $this->bySku('MAT-LEIDING-EXTRA');
            $lines[] = $this->line($item, $extra);
            $labourMinutes += (int) round($item->labour_minutes * $extra);

            $goot = $this->bySku('MAT-KABELGOOT');
            $lines[] = $this->line($goot, $extra);
            $labourMinutes += (int) round($goot->labour_minutes * $extra);
        }

        // --- Situatie-afhankelijke posten ---
        if ($lead->needs_condensate_pump) {
            $item = $this->bySku('MAT-CONDENSPOMP');
            $lines[] = $this->line($item, (float) $units);
            $labourMinutes += $item->labour_minutes * $units;
        }

        if ($lead->needs_extra_group) {
            $item = $this->bySku('MAT-GROEP');
            $lines[] = $this->line($item, 1.0);
            $labourMinutes += $item->labour_minutes;
        }

        if (($lead->floor_level ?? 0) >= 2) {
            $item = $this->bySku('SUR-HOOGWERK');
            $lines[] = $this->line($item, 1.0);
            $labourMinutes += $item->labour_minutes;
            $assumptions[] = 'Toeslag gevelwerk op hoogte omdat de buitenunit op de tweede verdieping of hoger komt.';
        }

        $fgas = $this->bySku('MAT-FGAS');
        $lines[] = $this->line($fgas, 1.0);

        // --- Arbeid ---
        $labourRate = $this->settings->int('agent.pricing.labour_sell_rate_cents', 7500);
        $labourHours = round($labourMinutes / 60, 2);
        $lines[] = [
            'kind' => 'labour',
            'sku' => 'ARBEID',
            'description' => 'Montage, inbedrijfstelling en oplevering',
            'quantity' => $labourHours,
            'unit' => 'uur',
            'unit_cost_cents' => $labourRate,
            'margin_pct' => 0.0,
            'unit_price_cents' => $labourRate,
            'line_total_cents' => (int) round($labourHours * $labourRate),
            'labour_minutes' => 0,
            'catalog_item_id' => null,
        ];

        $subtotal = array_sum(array_column($lines, 'line_total_cents'));
        $vatRate = $this->settings->float('agent.pricing.vat_rate', 21.0);

        // --- Geadverteerde vanaf-prijs ---
        // De advertentie noemt een bedrag inclusief btw; hier rekenen we in
        // dezelfde termen, zodat de ondergrens precies is wat de klant leest.
        $entryPrice = $this->settings->int('agent.pricing.entry_price_cents', 89900);
        $entryPriceExVat = (int) round($entryPrice / (1 + $vatRate / 100));

        // Landt de offerte op de advertentieprijs, dan wordt het totaal daaraan
        // vastgepind en volgt de btw uit het verschil. Anders komt er door
        // afronding een cent bij en klopt het bedrag in de advertentie niet meer.
        $pinnedTotal = null;

        if ($subtotal < $entryPriceExVat) {
            $shortfall = $entryPriceExVat - $subtotal;

            $lines[] = [
                'kind' => 'surcharge',
                'sku' => 'VANAF-PRIJS',
                'description' => sprintf('Aanpassing naar de vanaf-prijs van %s', $this->euro($entryPrice)),
                'quantity' => 1.0,
                'unit' => 'post',
                'unit_cost_cents' => 0,
                'margin_pct' => 0.0,
                'unit_price_cents' => $shortfall,
                'line_total_cents' => $shortfall,
                'labour_minutes' => 0,
                'catalog_item_id' => null,
            ];

            $subtotal = $entryPriceExVat;
            $pinnedTotal = $entryPrice;
            $assumptions[] = sprintf('Opgehoogd naar de geadverteerde vanaf-prijs van %s.', $this->euro($entryPrice));
        }

        // --- Instappakket: een eenvoudige klus voor de actieprijs ---
        if ($this->qualifiesForEntryPackage($lead, $kw, $system, $subtotal, $entryPriceExVat)) {
            $reduction = $subtotal - $entryPriceExVat;

            $lines[] = [
                'kind' => 'discount',
                'sku' => 'INSTAPPAKKET',
                'description' => sprintf('Instappakket: actieprijs %s inclusief montage', $this->euro($entryPrice)),
                'quantity' => 1.0,
                'unit' => 'post',
                'unit_cost_cents' => 0,
                'margin_pct' => 0.0,
                'unit_price_cents' => -$reduction,
                'line_total_cents' => -$reduction,
                'labour_minutes' => 0,
                'catalog_item_id' => null,
            ];

            $subtotal = $entryPriceExVat;
            $pinnedTotal = $entryPrice;
            $assumptions[] = sprintf('Actieprijs instappakket: %s inclusief montage.', $this->euro($entryPrice));
        }

        $vat = $pinnedTotal !== null
            ? $pinnedTotal - $subtotal
            : (int) round($subtotal * $vatRate / 100);

        $crew = max(1, $this->settings->int('agent.pricing.crew_size', 2));
        $travelBuffer = $this->settings->int('agent.calendar.travel_buffer_minutes', 30);
        $onsite = max(120, (int) (ceil($labourMinutes / $crew / 30) * 30)) + $travelBuffer;

        $cost = $this->costOf($lines, $labourMinutes);
        $margin = $subtotal > 0 ? round(($subtotal - $cost) / $subtotal * 100, 2) : 0.0;
        $minimumMargin = $this->settings->float('agent.pricing.minimum_margin_pct', 15.0);

        return [
            'lines' => $lines,
            'subtotal_cents' => $subtotal,
            'vat_cents' => $vat,
            'total_cents' => $subtotal + $vat,
            'discount_cents' => 0,
            'cost_cents' => $cost,
            'margin_pct' => $margin,
            'margin_warning' => $margin < $minimumMargin,
            'vat_rate' => $vatRate,
            'labour_minutes' => $labourMinutes,
            'onsite_minutes' => $onsite,
            'system' => $system,
            'tier' => $tier,
            'total_kw' => $kw * ($system === SystemType::MultiSplit ? $units : 1),
            'assumptions' => $assumptions,
        ];
    }

    /**
     * Slaat de berekening op als nieuwe versie voor de lead.
     *
     * Standaard is dat een prijsindicatie: die mag op afstand de deur uit. Een
     * offerte is een aanbod waaraan de klant rechten ontleent, en die hoort
     * pas te ontstaan als iemand de situatie ter plaatse heeft gezien.
     */
    public function createForLead(Lead $lead, QuoteKind $kind = QuoteKind::Indication): Quote
    {
        $calc = $this->calculate($lead);

        if (! $kind->isBinding()) {
            $calc['assumptions'][] = 'Richtbedrag op basis van de opgegeven gegevens. De definitieve prijs staat pas vast na de opname ter plaatse; aan deze indicatie kunnen geen rechten worden ontleend.';
        }

        return DB::transaction(function () use ($lead, $calc, $kind): Quote {
            $version = (int) $lead->quotes()->max('version') + 1;
            $validDays = $this->settings->int('agent.workflow.quote_valid_days', 21);

            $quote = $lead->quotes()->create([
                'number' => $this->nextNumber($lead, $kind, $version),
                'version' => $version,
                'kind' => $kind->value,
                'status' => 'draft',
                'public_token' => Str::random(48),
                'system_type' => $calc['system']->value,
                'tier' => $calc['tier']->value,
                'total_kw' => $calc['total_kw'],
                'subtotal_cents' => $calc['subtotal_cents'],
                'vat_rate' => $calc['vat_rate'],
                'vat_cents' => $calc['vat_cents'],
                'total_cents' => $calc['total_cents'],
                'discount_cents' => $calc['discount_cents'],
                'cost_cents' => $calc['cost_cents'],
                'margin_pct' => $calc['margin_pct'],
                'margin_warning' => $calc['margin_warning'],
                'labour_minutes' => $calc['labour_minutes'],
                'onsite_minutes' => $calc['onsite_minutes'],
                'assumptions' => $calc['assumptions'],
                'valid_until' => now()->addDays($validDays)->toDateString(),
            ]);

            foreach ($calc['lines'] as $sort => $line) {
                $quote->items()->create([
                    'catalog_item_id' => $line['catalog_item_id'],
                    'kind' => $line['kind'],
                    'sku' => $line['sku'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_cost_cents' => $line['unit_cost_cents'],
                    'margin_pct' => $line['margin_pct'],
                    'unit_price_cents' => $line['unit_price_cents'],
                    'line_total_cents' => $line['line_total_cents'],
                    'labour_minutes' => $line['labour_minutes'],
                    'sort' => $sort,
                ]);
            }

            return $quote->fresh(['items']) ?? $quote;
        });
    }

    /**
     * Past de kortings-post toe die de voice agent tijdens het conversiegesprek
     * mag aanbieden bij direct akkoord.
     */
    public function applyDirectAgreementDiscount(Quote $quote): Quote
    {
        $pct = $this->settings->float('agent.pricing.direct_agreement_discount_pct', 3.0);
        $max = $this->settings->int('agent.pricing.direct_agreement_discount_max_cents', 15000);

        if ($pct <= 0 || $quote->discount_cents < 0) {
            return $quote;
        }

        $discount = min((int) round($quote->subtotal_cents * $pct / 100), $max);

        if ($discount <= 0) {
            return $quote;
        }

        $quote->items()->create([
            'kind' => 'discount',
            'sku' => 'KORTING-DIRECT',
            'description' => sprintf('Korting bij direct akkoord (%s%%)', rtrim(rtrim(number_format($pct, 1, ',', ''), '0'), ',')),
            'quantity' => 1,
            'unit' => 'post',
            'unit_cost_cents' => 0,
            'margin_pct' => 0,
            'unit_price_cents' => -$discount,
            'line_total_cents' => -$discount,
            'labour_minutes' => 0,
            'sort' => 999,
        ]);

        $subtotal = $quote->subtotal_cents - $discount;
        $vat = (int) round($subtotal * $quote->vat_rate / 100);
        $margin = $subtotal > 0 ? round(($subtotal - $quote->cost_cents) / $subtotal * 100, 2) : 0.0;

        $quote->forceFill([
            'discount_cents' => -$discount,
            'subtotal_cents' => $subtotal,
            'vat_cents' => $vat,
            'total_cents' => $subtotal + $vat,
            'margin_pct' => $margin,
            'margin_warning' => $margin < $this->settings->float('agent.pricing.minimum_margin_pct', 15.0),
        ])->save();

        return $quote;
    }

    /**
     * Kostprijs van de offerte, exclusief btw: inkoop van apparatuur en
     * materiaal plus de kostprijs van de monteursuren. Kortingen en toeslagen
     * kosten niets, dus die tellen niet mee.
     *
     * @param  list<array{kind: string, unit_cost_cents: int, quantity: float}>  $lines
     */
    private function costOf(array $lines, int $labourMinutes): int
    {
        $materials = 0;

        foreach ($lines as $line) {
            if ($line['kind'] === 'labour') {
                continue;
            }

            $materials += (int) round($line['unit_cost_cents'] * $line['quantity']);
        }

        $labourRate = $this->settings->int('agent.pricing.labour_cost_rate_cents', 6500);

        return $materials + (int) round($labourMinutes / 60 * $labourRate);
    }

    /**
     * Een instapklus is een enkele binnenunit in de kleinste vermogensklasse,
     * op de begane grond, met een standaard leidinglengte en zonder extra
     * voorzieningen. Alleen dan mag de actieprijs gelden.
     */
    private function qualifiesForEntryPackage(Lead $lead, float $kw, SystemType $system, int $subtotal, int $entryPriceExVat): bool
    {
        if (! $this->settings->bool('agent.pricing.entry_package_enabled', false)) {
            return false;
        }

        if ($subtotal <= $entryPriceExVat) {
            return false; // al goedkoper dan de actieprijs
        }

        if ($system !== SystemType::SingleSplit) {
            return false;
        }

        if ($kw > $this->settings->float('agent.pricing.entry_package_max_kw', 2.5)) {
            return false;
        }

        if (($lead->pipe_length_m ?? self::INCLUDED_PIPE_M) > self::INCLUDED_PIPE_M) {
            return false;
        }

        if (($lead->floor_level ?? 0) >= 2) {
            return false;
        }

        return ! $lead->needs_condensate_pump && ! $lead->needs_extra_group;
    }

    private function euro(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }

    private function nextNumber(Lead $lead, QuoteKind $kind, int $version): string
    {
        return sprintf('%s-%s-%04d-%d', $kind->numberPrefix(), now()->format('Y'), $lead->id, $version);
    }

    /**
     * @return array{kind: string, sku: string|null, description: string, quantity: float, unit: string, unit_cost_cents: int, margin_pct: float, unit_price_cents: int, line_total_cents: int, labour_minutes: int, catalog_item_id: int|null}
     */
    private function line(CatalogItem $item, float $quantity, ?string $description = null): array
    {
        $unitPrice = $item->sellPriceCents();

        return [
            'kind' => str_starts_with($item->kind, 'equipment') ? 'equipment' : $item->kind,
            'sku' => $item->sku,
            'description' => $description ?? $item->name,
            'quantity' => round($quantity, 2),
            'unit' => $item->unit,
            'unit_cost_cents' => $item->cost_cents,
            'margin_pct' => $item->margin_pct,
            'unit_price_cents' => $unitPrice,
            'line_total_cents' => (int) round($unitPrice * $quantity),
            'labour_minutes' => $item->labour_minutes,
            'catalog_item_id' => $item->id,
        ];
    }

    /**
     * De productlijn die bij deze kwaliteitsklasse hoort, per soort.
     *
     * Staat in `config/agent.php` onder `pricing.series` en niet op de
     * catalogusregel zelf: de buitenunit van een multisplit wordt door twee
     * klassen gedeeld, en een regel kan maar één klasse dragen.
     */
    private function series(Tier $tier, string $kind): ?string
    {
        $map = $this->settings->get('agent.pricing.series', []);
        $series = is_array($map) ? ($map[$tier->value][$kind] ?? null) : null;

        return is_string($series) && $series !== '' ? $series : null;
    }

    /**
     * De regels die voor deze klasse in aanmerking komen: de ingestelde
     * productlijn, en zolang die er niet is alles wat op de klasse zelf staat.
     * Dat laatste is het gedrag van vóór de echte prijslijsten; zo blijft een
     * catalogus zonder lijnindeling gewoon werken.
     *
     * @return Builder<CatalogItem>
     */
    private function equipmentQuery(string $kind, Tier $tier): Builder
    {
        $series = $this->series($tier, $kind);

        $query = CatalogItem::query()->active()->where('kind', $kind);

        return $series !== null
            ? $query->where('series', $series)
            : $query->where('tier', $tier->value);
    }

    /**
     * De kleinste unit die de gevraagde vermogensklasse nog aankan.
     *
     * Er wordt op `capacity_class_kw` gezocht en niet op het echte vermogen:
     * een 3,4 kW-set bedient de 3,5 kW-klasse, en op het echte vermogen zou de
     * offerte daar onnodig een maat overheen gaan.
     */
    private function findEquipment(string $kind, Tier $tier, float $kw): CatalogItem
    {
        $item = $this->equipmentQuery($kind, $tier)
            ->whereNotNull('capacity_class_kw')
            ->where('capacity_class_kw', '>=', $kw)
            ->orderBy('capacity_class_kw')
            ->orderBy('cost_cents')
            ->first();

        return $item ?? $this->fallback($kind, $tier);
    }

    private function findOutdoor(Tier $tier, int $ports): CatalogItem
    {
        $item = $this->equipmentQuery('equipment_outdoor', $tier)
            ->where('ports', '>=', $ports)
            ->orderBy('ports')
            ->orderBy('cost_cents')
            ->first();

        return $item ?? $this->fallback('equipment_outdoor', $tier);
    }

    /**
     * Niets gevonden binnen de klasse: dan de grootste uit dezelfde lijn.
     *
     * Bewust geen uitwijk naar een andere lijn — bij een multisplit moeten
     * buiten- en binnenunit van hetzelfde merk zijn, en een offerte die niet
     * komt is beter dan een offerte met twee merken door elkaar.
     */
    private function fallback(string $kind, Tier $tier): CatalogItem
    {
        $item = $this->equipmentQuery($kind, $tier)
            ->orderByDesc('capacity_class_kw')
            ->orderByDesc('capacity_kw')
            ->orderByDesc('ports')
            ->first();

        if ($item === null) {
            throw new \RuntimeException(sprintf('Geen catalogusregel gevonden voor %s in klasse %s.', $kind, $tier->value));
        }

        return $item;
    }

    private function bySku(string $sku): CatalogItem
    {
        $item = CatalogItem::where('sku', $sku)->first();

        if ($item === null) {
            throw new \RuntimeException(sprintf('Catalogusregel %s ontbreekt.', $sku));
        }

        return $item;
    }

    /**
     * Wat dezelfde klus in de andere kwaliteitsklassen kost.
     *
     * Alleen het merkniveau van de apparatuur verschilt; materiaal, montage en
     * uren blijven gelijk. Dat maakt het verschil klein genoeg om te noemen —
     * en dat is precies waarom de klant het wil weten.
     *
     * @return list<array{tier: Tier, merk: string, total_cents: int, verschil_cents: int}>
     */
    public function alternatives(Lead $lead, Tier $gekozen): array
    {
        $basis = $this->calculate($lead, $gekozen)['total_cents'];
        $alternatieven = [];

        foreach (Tier::cases() as $tier) {
            if ($tier === $gekozen) {
                continue;
            }

            try {
                $calc = $this->calculate($lead, $tier);
            } catch (\RuntimeException) {
                // Staat er voor deze klasse niets in de catalogus, dan noemen we
                // hem liever niet dan met een verzonnen bedrag.
                continue;
            }

            $alternatieven[] = [
                'tier' => $tier,
                'merk' => $this->merkVan($calc),
                'total_cents' => $calc['total_cents'],
                'verschil_cents' => $calc['total_cents'] - $basis,
            ];
        }

        usort($alternatieven, static fn (array $a, array $b): int => $a['verschil_cents'] <=> $b['verschil_cents']);

        return $alternatieven;
    }

    /**
     * Het merkniveau staat op de apparatuurregel, en die staat vooraan.
     *
     * @param  array{lines: list<array{catalog_item_id: int|null}>, ...}  $calc
     */
    private function merkVan(array $calc): string
    {
        $id = $calc['lines'][0]['catalog_item_id'] ?? null;

        return $id === null ? '' : (string) CatalogItem::find($id)?->brand;
    }
}
