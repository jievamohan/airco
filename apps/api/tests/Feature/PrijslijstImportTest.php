<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PriceSource;
use App\Models\CatalogItem;
use App\Services\Pricing\PriceListImporter;
use App\Services\Pricing\PriceListRegistry;
use Database\Seeders\PriceListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * De overstap van afgeleide marktcijfers naar echte inkoopprijzen.
 */
class PrijslijstImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    #[Test]
    public function alle_apparatuur_waarmee_gerekend_wordt_komt_uit_een_prijslijst(): void
    {
        $voorlopig = CatalogItem::query()
            ->active()
            ->where('kind', 'like', 'equipment%')
            ->where('price_source', PriceSource::Provisional->value)
            ->pluck('sku');

        $this->assertCount(0, $voorlopig, 'Nog voorlopige apparatuur actief: '.$voorlopig->implode(', '));
        $this->assertGreaterThan(100, CatalogItem::query()->active()->realPriced()->count());
    }

    #[Test]
    public function materiaal_blijft_voorlopig_want_daar_is_nog_geen_lijst_van(): void
    {
        $materiaal = CatalogItem::query()->active()->where('kind', 'material')->get();

        $this->assertNotEmpty($materiaal);

        foreach ($materiaal as $item) {
            $this->assertSame(PriceSource::Provisional, $item->price_source, (string) $item->sku);
        }
    }

    #[Test]
    public function een_vervangen_regel_gaat_op_vervallen_en_wordt_niet_weggegooid(): void
    {
        // Zoals de proof-of-concept-regels op productie staan: apparatuur met
        // een afgeleide prijs. Er wijzen offertes naar, dus weggooien mag niet.
        $poc = CatalogItem::create([
            'sku' => 'SET-35-MID',
            'kind' => 'equipment_set',
            'name' => 'Single split 3,5 kW — LG / Fujitsu-klasse',
            'tier' => 'mid',
            'capacity_kw' => 3.5,
            'capacity_class_kw' => 3.5,
            'unit' => 'set',
            'cost_cents' => 65000,
            'margin_pct' => 45.0,
            'labour_minutes' => 330,
            'price_source' => PriceSource::Provisional->value,
        ]);

        $this->seed(PriceListSeeder::class);

        $poc->refresh();
        $this->assertFalse($poc->active);
        $this->assertStringContainsString('Vervallen', (string) $poc->source_note);
    }

    #[Test]
    public function eigen_invoer_uit_het_dashboard_overleeft_een_nieuwe_import(): void
    {
        $item = CatalogItem::query()->realPriced()->where('kind', 'equipment_set')->firstOrFail();

        $item->forceFill([
            'cost_cents' => 12345,
            'labour_minutes' => 400,
            'price_source' => PriceSource::Dashboard->value,
        ])->save();

        $this->seed(PriceListSeeder::class);

        $item->refresh();
        $this->assertSame(12345, $item->cost_cents);
        $this->assertSame(400, $item->labour_minutes);
        $this->assertSame(PriceSource::Dashboard, $item->price_source);
    }

    #[Test]
    public function opnieuw_importeren_verandert_niets(): void
    {
        $voor = CatalogItem::query()->orderBy('sku')->get(['sku', 'cost_cents', 'active'])->toArray();

        $this->seed(PriceListSeeder::class);

        $na = CatalogItem::query()->orderBy('sku')->get(['sku', 'cost_cents', 'active'])->toArray();
        $this->assertSame($voor, $na);
    }

    #[Test]
    public function de_netto_inkoopprijs_komt_letterlijk_uit_de_lijst(): void
    {
        // KAISAI EVO 2,6 kW set: bruto 502,20 met 60% korting = netto 200,88.
        $evo = CatalogItem::query()->where('sku', 'KEV-09SET')->firstOrFail();

        $this->assertSame(20088, $evo->cost_cents);
        $this->assertSame(50220, $evo->list_price_cents);
        $this->assertSame(60.0, $evo->purchase_discount_pct);
        $this->assertSame('KAISAI', $evo->brand);
        $this->assertStringContainsString('KAISAI Prijslijst 2026-6', (string) $evo->price_list_ref);
    }

    #[Test]
    public function een_unit_bedient_de_rekenklasse_die_hij_aankan(): void
    {
        // De EVO-12 levert 3,4 kW; dat is de 3,5 kW-klasse en niet de 5,0.
        $evo = CatalogItem::query()->where('sku', 'KEV-12SET')->firstOrFail();
        $this->assertSame(3.4, $evo->capacity_kw);
        $this->assertSame(3.5, $evo->capacity_class_kw);

        // Mitsubishi zet het vermogen in het typenummer: SRK35 is 3,5 kW.
        $mhi = CatalogItem::query()->where('sku', 'QP950001')->firstOrFail();
        $this->assertSame(3.5, $mhi->capacity_kw);
        $this->assertSame(3.5, $mhi->capacity_class_kw);

        // Ver boven het woningwerk waar de agent op rekent: geen klasse, dus
        // wordt hij nooit vanzelf gekozen.
        $groot = CatalogItem::query()->where('sku', 'QP950026')->firstOrFail();
        $this->assertSame(10.0, $groot->capacity_kw);
        $this->assertNull($groot->capacity_class_kw);
    }

    #[Test]
    public function elke_multisplit_buitenunit_weet_hoeveel_aansluitingen_hij_heeft(): void
    {
        $buiten = CatalogItem::query()->active()->whereIn('series', ['mhi-scm', 'kaisai-multi'])->get();

        $this->assertNotEmpty($buiten);

        foreach ($buiten as $item) {
            $this->assertNotNull($item->ports, sprintf('%s heeft geen aantal aansluitingen.', (string) $item->sku));
            $this->assertGreaterThanOrEqual(2, $item->ports);
        }
    }

    #[Test]
    public function elk_artikel_uit_een_prijslijst_heeft_een_klasse_en_een_normtijd(): void
    {
        // Een lege klasse leest in het dashboard als "hier ontbreekt iets", en
        // een normtijd van nul telt in de offerte stilzwijgend als nul uur mee.
        // Komt er een prijslijst bij met een lijn die nog niet is ingedeeld,
        // dan hoort die hier op te vallen — niet pas op een offerte.
        $zonderKlasse = CatalogItem::query()->realPriced()->whereNull('tier')->pluck('sku');
        $this->assertCount(0, $zonderKlasse, 'Zonder kwaliteitsklasse: '.$zonderKlasse->implode(', '));

        $zonderNormtijd = CatalogItem::query()->realPriced()->where('labour_minutes', '<=', 0)->pluck('sku');
        $this->assertCount(0, $zonderNormtijd, 'Zonder normtijd: '.$zonderNormtijd->implode(', '));
    }

    #[Test]
    public function inbouwwerk_krijgt_meer_montagetijd_dan_een_wandunit(): void
    {
        $wand = CatalogItem::query()->where('sku', 'QP950001')->firstOrFail();      // SRK35ZS-WF set
        $cassette = CatalogItem::query()->where('sku', 'QP950043')->firstOrFail();  // FDTC25VH 60x60 set

        $this->assertGreaterThan(
            $wand->labour_minutes,
            $cassette->labour_minutes,
            'Een cassette in het plafond is meer werk dan een wandunit ophangen.',
        );
    }

    #[Test]
    public function de_normtijden_waarop_de_offerte_rekent_zijn_niet_verschoven(): void
    {
        // Deze drie zitten in elke offerte. Verandert er hier iets, dan
        // verandert elke prijs mee, en dat hoort een bewuste keuze te zijn.
        $reeks = static fn (string $kind, string $tier): int => CatalogItem::query()
            ->where('kind', $kind)
            ->where('series', config('agent.pricing.series.'.$tier.'.'.$kind))
            ->value('labour_minutes');

        $this->assertSame(330, $reeks('equipment_set', 'premium'));
        $this->assertSame(180, $reeks('equipment_indoor', 'premium'));
        $this->assertSame(390, $reeks('equipment_outdoor', 'budget'));
    }

    #[Test]
    public function de_import_meldt_een_dubbel_artikelnummer_in_plaats_van_het_stil_te_overschrijven(): void
    {
        $importer = app(PriceListImporter::class);
        $result = $importer->import(app(PriceListRegistry::class)->get('kaisai-2026'));

        $this->assertNotEmpty($result['waarschuwingen']);
        $this->assertStringContainsString('KKWR-24SET', implode(' ', $result['waarschuwingen']));
    }
}
