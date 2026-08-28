<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CallPurpose;
use App\Mail\OwnerNotificationMail;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\EntryPriceCheck;
use App\Services\LeadWorkflow;
use App\Services\QuoteBuilder;
use App\Services\SettingsRepository;
use App\Services\Voice\CallVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * De geadverteerde vanaf-prijs en de margebewaking die erbij hoort.
 */
class EntryPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    /**
     * @param  array<string, string>  $values
     */
    private function configure(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::where('key', $key)->update(['value' => $value]);
        }

        app(SettingsRepository::class)->flush();
    }

    /** Kleinste denkbare klus: die valt binnen het instappakket. */
    private function instapLead(): Lead
    {
        return Lead::factory()->create([
            'space_size' => 12,
            'space_unit' => 'm2',
            'rooms_count' => 1,
            'building_year' => 2015,
            'pipe_length_m' => 5,
            'floor_level' => 0,
            'tier' => 'budget',
        ]);
    }

    #[Test]
    public function elke_offerte_legt_kostprijs_en_marge_vast(): void
    {
        $quote = app(QuoteBuilder::class)->createForLead($this->instapLead());

        $this->assertGreaterThan(0, $quote->cost_cents);
        $this->assertLessThan($quote->subtotal_cents, $quote->cost_cents);
        $this->assertSame(
            round(($quote->subtotal_cents - $quote->cost_cents) / $quote->subtotal_cents * 100, 2),
            $quote->margin_pct,
        );
        $this->assertFalse($quote->margin_warning);
    }

    #[Test]
    public function de_kostprijs_rekent_arbeid_tegen_het_kostentarief_niet_het_verkooptarief(): void
    {
        $calc = app(QuoteBuilder::class)->calculate($this->instapLead());

        $labour = array_values(array_filter($calc['lines'], static fn (array $l): bool => $l['kind'] === 'labour'));
        $this->assertCount(1, $labour);

        $uren = $labour[0]['quantity'];
        $materiaal = 0;

        foreach ($calc['lines'] as $line) {
            if ($line['kind'] !== 'labour') {
                $materiaal += (int) round($line['unit_cost_cents'] * $line['quantity']);
            }
        }

        // Kostprijs arbeid is € 65 per uur, verkoop € 75.
        $this->assertSame($materiaal + (int) round($uren * 6500), $calc['cost_cents']);
        $this->assertGreaterThan($calc['cost_cents'], $calc['subtotal_cents']);
    }

    #[Test]
    public function een_offerte_komt_nooit_onder_de_geadverteerde_vanaf_prijs(): void
    {
        // Vanaf-prijs ruim boven de normale uitkomst: de ondergrens moet binden.
        $this->configure(['agent.pricing.entry_price_cents' => '250000']);

        $calc = app(QuoteBuilder::class)->calculate($this->instapLead());

        $this->assertSame(250000, $calc['total_cents']);

        $skus = array_column($calc['lines'], 'sku');
        $this->assertContains('VANAF-PRIJS', $skus);
    }

    #[Test]
    public function het_totaal_landt_exact_op_de_advertentieprijs_zonder_afrondingscent(): void
    {
        // 899 / 1,21 komt niet rond uit; zonder vastpinnen wordt het € 899,01.
        $this->configure([
            'agent.pricing.entry_price_cents' => '89900',
            'agent.pricing.entry_package_enabled' => '1',
        ]);

        $calc = app(QuoteBuilder::class)->calculate($this->instapLead());

        $this->assertSame(89900, $calc['total_cents']);
        $this->assertSame($calc['subtotal_cents'] + $calc['vat_cents'], $calc['total_cents']);
    }

    #[Test]
    public function het_instappakket_topt_een_eenvoudige_klus_af_en_markeert_de_marge(): void
    {
        $this->configure([
            'agent.pricing.entry_price_cents' => '89900',
            'agent.pricing.entry_package_enabled' => '1',
        ]);

        $quote = app(QuoteBuilder::class)->createForLead($this->instapLead());

        $this->assertSame(89900, $quote->total_cents);
        $this->assertLessThan(0, $quote->margin_pct, 'Onder de kostprijs hoort de marge negatief te zijn.');
        $this->assertTrue($quote->margin_warning);
        $this->assertContains('INSTAPPAKKET', $quote->items->pluck('sku')->all());
    }

    #[Test]
    public function het_instappakket_geldt_niet_voor_een_klus_die_er_niet_onder_valt(): void
    {
        $this->configure([
            'agent.pricing.entry_price_cents' => '89900',
            'agent.pricing.entry_package_enabled' => '1',
        ]);

        $builder = app(QuoteBuilder::class);

        $groot = Lead::factory()->create(['space_size' => 34, 'space_unit' => 'm2', 'building_year' => 1998]);
        $lang = Lead::factory()->create(['space_size' => 12, 'space_unit' => 'm2', 'building_year' => 2015, 'pipe_length_m' => 14, 'tier' => 'budget']);
        $hoog = Lead::factory()->create(['space_size' => 12, 'space_unit' => 'm2', 'building_year' => 2015, 'pipe_length_m' => 5, 'floor_level' => 3, 'tier' => 'budget']);
        $pomp = Lead::factory()->create(['space_size' => 12, 'space_unit' => 'm2', 'building_year' => 2015, 'pipe_length_m' => 5, 'needs_condensate_pump' => true, 'tier' => 'budget']);

        foreach (['te groot' => $groot, 'lange leiding' => $lang, 'hoogwerk' => $hoog, 'condenspomp' => $pomp] as $reden => $lead) {
            $calc = $builder->calculate($lead);
            $this->assertNotSame(89900, $calc['total_cents'], sprintf('"%s" hoort niet onder het instappakket te vallen.', $reden));
            $this->assertNotContains('INSTAPPAKKET', array_column($calc['lines'], 'sku'), $reden);
        }
    }

    #[Test]
    public function het_instappakket_doet_niets_zolang_het_uitstaat(): void
    {
        $this->configure(['agent.pricing.entry_price_cents' => '89900']);

        $calc = app(QuoteBuilder::class)->calculate($this->instapLead());

        $this->assertGreaterThan(89900, $calc['total_cents']);
        $this->assertNotContains('INSTAPPAKKET', array_column($calc['lines'], 'sku'));
        $this->assertFalse($calc['margin_warning']);
    }

    #[Test]
    public function de_vanaf_prijs_check_rekent_door_of_de_advertentie_klopt(): void
    {
        $this->configure(['agent.pricing.entry_price_cents' => '89900']);

        $check = app(EntryPriceCheck::class)->run();

        $this->assertFalse($check['achievable']);
        $this->assertLessThan(0, $check['result_at_entry_price_cents']);
        $this->assertGreaterThan(89900, $check['advised_entry_price_cents']);
        $this->assertStringContainsString('onder de kostprijs', $check['message']);
    }

    #[Test]
    public function de_vanaf_prijs_check_keurt_een_haalbare_prijs_goed(): void
    {
        $this->configure(['agent.pricing.entry_price_cents' => '144900']);

        $check = app(EntryPriceCheck::class)->run();

        $this->assertTrue($check['achievable']);
        $this->assertGreaterThan(0, $check['result_at_entry_price_cents']);
        $this->assertGreaterThanOrEqual($check['minimum_margin_pct'], $check['margin_at_entry_price_pct']);
        $this->assertStringContainsString('haalbaar', $check['message']);
    }

    #[Test]
    public function de_adviesprijs_haalt_precies_de_margedrempel(): void
    {
        $this->configure(['agent.pricing.entry_price_cents' => '89900']);

        $advies = app(EntryPriceCheck::class)->run()['advised_entry_price_cents'];

        $this->configure(['agent.pricing.entry_price_cents' => (string) $advies]);
        $opnieuw = app(EntryPriceCheck::class)->run();

        $this->assertTrue($opnieuw['achievable'], 'De geadviseerde prijs hoort de drempel te halen.');
    }

    #[Test]
    public function een_offerte_onder_de_drempel_belandt_in_de_tijdlijn(): void
    {
        $this->configure([
            'agent.pricing.entry_price_cents' => '89900',
            'agent.pricing.entry_package_enabled' => '1',
        ]);

        $lead = $this->instapLead();
        app(LeadWorkflow::class)->buildQuote($lead);

        $this->assertContains('margin_warning', $lead->events()->pluck('type')->all());
    }

    #[Test]
    public function de_ondernemer_wordt_in_de_mail_gewaarschuwd_bij_een_te_lage_marge(): void
    {
        Mail::fake();

        $this->configure([
            'agent.pricing.entry_price_cents' => '89900',
            'agent.pricing.entry_package_enabled' => '1',
        ]);

        $lead = $this->instapLead();
        $workflow = app(LeadWorkflow::class);
        $quote = $workflow->buildQuote($lead);
        $workflow->markQuoteSent($lead, $quote);

        Mail::assertSent(OwnerNotificationMail::class, static function (OwnerNotificationMail $mail): bool {
            $waarschuwing = array_filter(
                $mail->lines,
                static fn (string $line): bool => str_contains($line, 'Let op') && str_contains($line, 'marge'),
            );

            return str_contains($mail->headline, 'onder de margedrempel') && $waarschuwing !== [];
        });
    }

    #[Test]
    public function een_gezonde_offerte_levert_geen_waarschuwing_in_de_mail(): void
    {
        Mail::fake();

        $lead = $this->instapLead();
        $workflow = app(LeadWorkflow::class);
        $workflow->markQuoteSent($lead, $workflow->buildQuote($lead));

        Mail::assertSent(OwnerNotificationMail::class, static function (OwnerNotificationMail $mail): bool {
            return ! str_contains($mail->headline, 'onder de margedrempel');
        });
    }

    #[Test]
    public function de_voice_agent_krijgt_de_vanaf_prijs_mee(): void
    {
        $this->configure(['agent.pricing.entry_price_cents' => '89900']);

        $variables = app(CallVariables::class)
            ->build($this->instapLead(), CallPurpose::Qualification);

        $this->assertSame('€ 899,00', $variables['vanaf_prijs']);
        $this->assertStringContainsString('apparaat', $variables['vanaf_prijs_dekking']);

        $this->configure(['agent.pricing.entry_package_enabled' => '1']);

        $metPakket = app(CallVariables::class)
            ->build($this->instapLead(), CallPurpose::Qualification);

        $this->assertStringContainsString('inclusief montage', $metPakket['vanaf_prijs_dekking']);
    }
}
