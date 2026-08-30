<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CallOutcome;
use App\Enums\CallPurpose;
use App\Enums\QuoteKind;
use App\Models\Lead;
use App\Services\LeadWorkflow;
use App\Services\QuoteBuilder;
use App\Services\Voice\CallVariables;
use App\Services\Voice\FakeVoiceAgentClient;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Het gespreksscript van de voice agent staat bij ElevenLabs, niet in deze
 * repository. De afspraken erover wel: welke variabelen wij meesturen en welke
 * velden wij terugverwachten.
 *
 * Deze test bewaakt dat contract. Hernoemt iemand een veld in de code, dan
 * faalt hij hier in plaats van stilletjes in een echt telefoongesprek.
 */
class VoiceAgentPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        $this->app->instance(VoiceAgentClient::class, new FakeVoiceAgentClient);
    }

    private function document(): string
    {
        $pad = realpath(base_path('../../docs/runbooks/voice-agent-prompt.md'));

        $this->assertNotFalse($pad, 'Het promptdocument is verplaatst of verwijderd.');

        return (string) file_get_contents($pad);
    }

    /**
     * @return list<string>
     */
    private function variabelenInDePrompt(): array
    {
        $blokken = explode('```text', $this->document());
        $this->assertArrayHasKey(1, $blokken, 'Het promptblok ontbreekt in het document.');

        $prompt = explode('```', $blokken[1])[0];
        preg_match_all('/\{\{([a-z_]+)\}\}/', $prompt, $treffers);

        return array_values(array_unique($treffers[1]));
    }

    /**
     * @return list<string>
     */
    private function veldenInHetDocument(): array
    {
        $sectie = explode('## 5.', explode('## 4. Dataverzameling', $this->document())[1])[0];
        preg_match_all('/^\| `([a-z_]+)`/m', $sectie, $treffers);

        return array_values(array_unique($treffers[1]));
    }

    #[Test]
    public function elke_variabele_in_de_prompt_wordt_ook_echt_meegestuurd(): void
    {
        $lead = Lead::factory()->create(['status' => 'quoted']);
        $quote = app(QuoteBuilder::class)->createForLead($lead);

        $beschikbaar = app(CallVariables::class)->build($lead, CallPurpose::Conversion, $quote);

        foreach ($this->variabelenInDePrompt() as $variabele) {
            $this->assertArrayHasKey(
                $variabele,
                $beschikbaar,
                sprintf('De prompt gebruikt {{%s}}, maar die sturen we niet mee.', $variabele),
            );
        }
    }

    #[Test]
    public function geen_enkele_meegestuurde_variabele_is_leeg(): void
    {
        $lead = Lead::factory()->create(['status' => 'quoted']);
        $quote = app(QuoteBuilder::class)->createForLead($lead);

        foreach (app(CallVariables::class)->build($lead, CallPurpose::Conversion, $quote) as $naam => $waarde) {
            $this->assertNotSame('', $waarde, sprintf('Variabele %s is leeg; dat wordt een gat in het gesprek.', $naam));
        }
    }

    #[Test]
    public function de_openingszin_meldt_de_digitale_assistent_en_de_opname(): void
    {
        $lead = Lead::factory()->create();

        foreach (CallPurpose::cases() as $purpose) {
            $opening = app(CallVariables::class)->build($lead, $purpose)['gespreksopening'];

            $this->assertStringContainsString('digitale assistent', $opening, $purpose->value);
            $this->assertStringContainsString('opgenomen', $opening, $purpose->value);
        }
    }

    #[Test]
    public function de_gedocumenteerde_velden_worden_ook_echt_overgenomen(): void
    {
        $lead = Lead::factory()->create([
            'status' => 'calling',
            'rooms_count' => 1,
            'building_year' => null,
            'pipe_length_m' => null,
        ]);

        $call = $lead->calls()->create([
            'provider' => 'elevenlabs',
            'purpose' => CallPurpose::Qualification->value,
            'attempt_no' => 1,
            'status' => 'initiated',
            'to_number' => '+31612345678',
            'started_at' => now(),
        ]);

        // Precies de waarden die het script de agent laat terugsturen.
        $verzameld = [
            'rooms_count' => 3,
            'space_size' => 42.5,
            'space_unit' => 'm2',
            'building_year' => 1975,
            'insulation' => 'poor',
            'floor_level' => 1,
            'wall_type' => 'spouwmuur',
            'outdoor_unit_placement' => 'plat dak',
            'pipe_length_m' => 11,
            'needs_condensate_pump' => true,
            'needs_extra_group' => true,
            'desired_start' => '2026-10-01',
            'email' => 'nieuw@example.nl',
            'tier' => 'premium',
            'notes' => 'Kat in huis, graag bellen voor aankomst.',
        ];

        app(LeadWorkflow::class)->completeCall($call, CallOutcome::Answered, null, null, $verzameld);

        $lead->refresh();

        $this->assertSame(3, $lead->rooms_count);
        $this->assertSame(42.5, $lead->space_size);
        $this->assertSame('m2', $lead->space_unit);
        $this->assertSame(1975, $lead->building_year);
        $this->assertSame('poor', $lead->insulation);
        $this->assertSame(1, $lead->floor_level);
        $this->assertSame('spouwmuur', $lead->wall_type);
        $this->assertSame('plat dak', $lead->outdoor_unit_placement);
        $this->assertSame(11, $lead->pipe_length_m);
        $this->assertTrue($lead->needs_condensate_pump);
        $this->assertTrue($lead->needs_extra_group);
        $this->assertSame('2026-10-01', $lead->desired_start?->toDateString());
        $this->assertSame('nieuw@example.nl', $lead->email);
        $this->assertSame('premium', $lead->tier?->value);
        $this->assertStringContainsString('Kat in huis', (string) $lead->notes);

        // Alle technische velden uit het document moeten hierboven getoetst zijn.
        $stuurvelden = ['outcome', 'appointment_agreed', 'appointment_start'];
        $technisch = array_diff($this->veldenInHetDocument(), $stuurvelden);

        foreach ($technisch as $veld) {
            $this->assertArrayHasKey(
                $veld,
                $verzameld,
                sprintf('Het document noemt veld "%s", maar deze test dekt het niet af.', $veld),
            );
        }
    }

    #[Test]
    public function elk_gesprekstype_heeft_een_eigen_blok_in_het_script(): void
    {
        $blokken = explode('```text', $this->document());
        $prompt = explode('```', $blokken[1])[0];

        foreach (CallPurpose::cases() as $purpose) {
            $this->assertStringContainsString(
                '## Als gesprekstype = '.$purpose->value,
                $prompt,
                sprintf('Gesprekstype "%s" bestaat in de code maar niet in het script; de agent verzint dan zelf wat hij doet.', $purpose->value),
            );
        }
    }

    #[Test]
    public function het_afsluitgesprek_krijgt_het_offertebedrag_en_het_conversiegesprek_het_richtbedrag(): void
    {
        $lead = Lead::factory()->create(['status' => 'quoted']);
        $indicatie = app(QuoteBuilder::class)->createForLead($lead);
        $offerte = app(QuoteBuilder::class)->createForLead($lead, QuoteKind::Final);
        $offerte->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

        $conversie = app(CallVariables::class)->build($lead, CallPurpose::Conversion, $indicatie);
        $afsluiting = app(CallVariables::class)->build($lead, CallPurpose::Close, $offerte);

        $this->assertSame($indicatie->number, $conversie['indicatie_nummer']);
        $this->assertSame($offerte->number, $conversie['offerte_nummer'], 'Ook in het conversiegesprek moet de set compleet zijn.');
        $this->assertSame($offerte->number, $afsluiting['offerte_nummer']);
        $this->assertSame($indicatie->number, $afsluiting['indicatie_nummer']);
    }

    #[Test]
    public function de_uitkomsten_uit_het_document_bestaan_als_uitkomst(): void
    {
        $sectie = explode('| `appointment_agreed`', explode('## 4. Dataverzameling', $this->document())[1])[0];
        preg_match_all('/`([a-z_]+)` \(/', $sectie, $treffers);

        $this->assertNotEmpty($treffers[1], 'Het document noemt geen uitkomsten meer.');

        foreach ($treffers[1] as $uitkomst) {
            $this->assertNotNull(
                CallOutcome::tryFrom($uitkomst),
                sprintf('Het document noemt uitkomst "%s", die de code niet kent.', $uitkomst),
            );
        }
    }
}
