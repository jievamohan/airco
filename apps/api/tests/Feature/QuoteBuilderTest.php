<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SystemType;
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
    public function een_standaard_woonkamer_valt_binnen_de_marktrange(): void
    {
        $lead = Lead::factory()->create(['space_size' => 32, 'space_unit' => 'm2', 'building_year' => 1998]);

        $calc = app(QuoteBuilder::class)->calculate($lead);

        // Marktrange voor een 3,5 kW single split incl. montage: EUR 1.600 - 2.800 incl. btw.
        $this->assertGreaterThan(160000, $calc['total_cents']);
        $this->assertLessThan(280000, $calc['total_cents']);
        $this->assertSame(SystemType::SingleSplit, $calc['system']);
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

        $skus = array_column($calc['lines'], 'sku');
        $this->assertNotEmpty(array_filter($skus, static fn (?string $sku): bool => str_starts_with((string) $sku, 'OUT-')));

        $indoor = array_values(array_filter($calc['lines'], static fn (array $line): bool => str_starts_with((string) $line['sku'], 'IN-')));
        $this->assertCount(1, $indoor);
        $this->assertSame(3.0, $indoor[0]['quantity']);
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
    public function een_kleine_klus_wordt_opgehoogd_naar_de_minimale_opdrachtwaarde(): void
    {
        $lead = Lead::factory()->create(['space_size' => 12, 'space_unit' => 'm2', 'tier' => 'budget', 'building_year' => 2020]);

        $calc = app(QuoteBuilder::class)->calculate($lead);

        $this->assertGreaterThanOrEqual(95000, $calc['subtotal_cents']);
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
