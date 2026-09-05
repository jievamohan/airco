<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SystemType;
use App\Models\CatalogItem;
use App\Models\Lead;
use App\Services\QuoteBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    #[Test]
    public function een_standaard_woonkamer_blijft_binnen_een_verdedigbare_bandbreedte(): void
    {
        $lead = Lead::factory()->create(['space_size' => 32, 'space_unit' => 'm2', 'building_year' => 1998]);

        $calc = app(QuoteBuilder::class)->calculate($lead);

        // Ondergrens is de geadverteerde vanaf-prijs, bovengrens de bovenkant
        // van de marktrange voor een 3,5 kW single split inclusief montage
        // (EUR 2.800 incl. btw, zie docs/research/pricing-baseline.md).
        //
        // Met de echte inkoopprijzen landt deze klus onder in die range en
        // niet meer middenin: het marktonderzoek ging uit van een fors hogere
        // inkoop dan wat de leverancier nu rekent. Dat is geen fout in de
        // berekening maar ruimte in de opslag — `pricing.equipment_margin_pct`
        // is de knop, en die zet de ondernemer zelf.
        $this->assertGreaterThanOrEqual(89900, $calc['total_cents']);
        $this->assertLessThan(280000, $calc['total_cents']);
        $this->assertSame(SystemType::SingleSplit, $calc['system']);
    }

    #[Test]
    public function de_apparatuur_op_een_offerte_komt_uit_een_echte_prijslijst(): void
    {
        $lead = Lead::factory()->create(['space_size' => 32, 'space_unit' => 'm2', 'building_year' => 1998]);

        $calc = app(QuoteBuilder::class)->calculate($lead);
        $apparatuur = array_filter($calc['lines'], static fn (array $l): bool => $l['kind'] === 'equipment');

        $this->assertNotEmpty($apparatuur);

        foreach ($apparatuur as $line) {
            $item = CatalogItem::findOrFail($line['catalog_item_id']);
            $this->assertTrue(
                $item->price_source->isReal(),
                sprintf('Apparatuurregel %s rekent nog met een voorlopige prijs.', (string) $item->sku),
            );
        }
    }

    #[Test]
    public function de_btw_wordt_over_het_subtotaal_berekend(): void
    {
        $lead = Lead::factory()->create();

        $calc = app(QuoteBuilder::class)->calculate($lead);

        $this->assertSame((int) round($calc['subtotal_cents'] * 0.21), $calc['vat_cents']);
        $this->assertSame($calc['subtotal_cents'] + $calc['vat_cents'], $calc['total_cents']);
    }

    #[Test]
    public function extra_leidinglengte_verhoogt_prijs_en_montagetijd(): void
    {
        $kort = Lead::factory()->create(['pipe_length_m' => 5]);
        $lang = Lead::factory()->create(['pipe_length_m' => 15]);

        $builder = app(QuoteBuilder::class);
        $a = $builder->calculate($kort);
        $b = $builder->calculate($lang);

        $this->assertGreaterThan($a['total_cents'], $b['total_cents']);
        $this->assertGreaterThan($a['labour_minutes'], $b['labour_minutes']);
    }

    #[Test]
    public function een_multisplit_krijgt_een_buitenunit_en_meerdere_binnenunits(): void
    {
        $lead = Lead::factory()->create(['space_size' => 90, 'space_unit' => 'm2', 'rooms_count' => 3]);

        $calc = app(QuoteBuilder::class)->calculate($lead);

        $this->assertSame(SystemType::MultiSplit, $calc['system']);

        $items = CatalogItem::query()
            ->whereIn('id', array_filter(array_column($calc['lines'], 'catalog_item_id')))
            ->get()
            ->keyBy('id');

        // Arbeid en toeslagen hebben geen catalogusregel; die vallen hier weg.
        $soort = static fn (array $l): ?string => $items->get($l['catalog_item_id'])?->kind;

        $buiten = array_values(array_filter($calc['lines'], static fn (array $l): bool => $soort($l) === 'equipment_outdoor'));
        $this->assertCount(1, $buiten);
        $this->assertGreaterThanOrEqual(3, $items->get($buiten[0]['catalog_item_id'])->ports);

        $indoor = array_values(array_filter($calc['lines'], static fn (array $l): bool => $soort($l) === 'equipment_indoor'));
        $this->assertCount(1, $indoor);
        $this->assertSame(3.0, $indoor[0]['quantity']);

        // Buiten- en binnenunit van een multisplit moeten van hetzelfde merk
        // zijn; anders passen ze fysiek niet op elkaar.
        $this->assertSame(
            $items->get($buiten[0]['catalog_item_id'])->brand,
            $items->get($indoor[0]['catalog_item_id'])->brand,
        );
    }

    #[Test]
    public function de_montageduur_op_locatie_houdt_rekening_met_de_ploeggrootte(): void
    {
        $lead = Lead::factory()->create();

        $calc = app(QuoteBuilder::class)->calculate($lead);

        // 6 monteursuren bij 2 monteurs = 3 uur, plus 30 minuten reis- en opruimtijd.
        $this->assertSame(360, $calc['labour_minutes']);
        $this->assertSame(210, $calc['onsite_minutes']);
    }

    #[Test]
    public function een_kleine_klus_komt_niet_onder_de_geadverteerde_vanaf_prijs(): void
    {
        $lead = Lead::factory()->create(['space_size' => 12, 'space_unit' => 'm2', 'tier' => 'budget', 'building_year' => 2020]);

        $calc = app(QuoteBuilder::class)->calculate($lead);

        // De vanaf-prijs staat standaard op € 899 incl. btw; zie EntryPriceTest
        // voor het gedrag van de ondergrens en het instappakket zelf.
        $this->assertGreaterThanOrEqual(89900, $calc['total_cents']);
    }

    #[Test]
    public function een_opgeslagen_offerte_bevat_alle_regels_en_een_uniek_nummer(): void
    {
        $lead = Lead::factory()->create();

        $quote = app(QuoteBuilder::class)->createForLead($lead);

        $this->assertSame(1, $quote->version);
        $this->assertNotEmpty($quote->public_token);
        $this->assertGreaterThan(0, $quote->items->count());
        $this->assertSame(
            $quote->subtotal_cents,
            (int) $quote->items->sum('line_total_cents'),
        );

        $tweede = app(QuoteBuilder::class)->createForLead($lead->refresh());
        $this->assertSame(2, $tweede->version);
        $this->assertNotSame($quote->number, $tweede->number);
    }

    #[Test]
    public function de_kortingsregel_verlaagt_subtotaal_btw_en_totaal(): void
    {
        $lead = Lead::factory()->create();
        $builder = app(QuoteBuilder::class);
        $quote = $builder->createForLead($lead);
        $voor = $quote->total_cents;

        $na = $builder->applyDirectAgreementDiscount($quote);

        $this->assertLessThan($voor, $na->total_cents);
        $this->assertLessThan(0, $na->discount_cents);
        $this->assertSame((int) round($na->subtotal_cents * 0.21), $na->vat_cents);
    }
}
