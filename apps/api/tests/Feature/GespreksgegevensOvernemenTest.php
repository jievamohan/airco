<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\LeadWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Wat de agent aan de telefoon hoort, is geen formulierinvoer. Eén antwoord dat
 * niet in een kolom past mag de rest van het gesprek niet meenemen in zijn val:
 * op productie kostte "vierkante meter" in een kolom van drie tekens het hele
 * gesprek — bouwjaar, isolatie, verdieping, leidinglengte en de notitie.
 */
class GespreksgegevensOvernemenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    /**
     * @param  array<string, mixed>  $collected
     */
    private function pasToe(Lead $lead, array $collected): void
    {
        $methode = new ReflectionMethod(LeadWorkflow::class, 'applyCollected');
        $methode->invoke(app(LeadWorkflow::class), $lead, $collected);
    }

    #[Test]
    public function een_onbruikbaar_antwoord_kost_niet_de_rest_van_het_gesprek(): void
    {
        $lead = Lead::factory()->create(['status' => 'calling']);

        $this->pasToe($lead, [
            'space_unit' => 'vierkante meter',
            'insulation' => 'good',
            'building_year' => 1930,
            'floor_level' => 1,
            'outdoor_unit_placement' => 'achtergevel',
            'pipe_length_m' => 8,
            'notes' => 'De woning is redelijk goed geïsoleerd.',
        ]);

        $vers = $lead->fresh();
        $this->assertSame('m2', $vers->space_unit, 'Dit hoort herkend te worden.');
        $this->assertSame('good', $vers->insulation);
        $this->assertSame(1930, $vers->building_year);
        $this->assertSame(1, $vers->floor_level);
        $this->assertSame('achtergevel', $vers->outdoor_unit_placement);
        $this->assertSame(8, $vers->pipe_length_m);
        $this->assertStringContainsString('redelijk goed geïsoleerd', (string) $vers->notes);
    }

    #[Test]
    public function nederlandse_antwoorden_worden_omgezet(): void
    {
        $lead = Lead::factory()->create(['status' => 'calling']);

        $this->pasToe($lead, [
            'space_unit' => 'Kubieke meter',
            'insulation' => 'Matig',
            'tier' => 'voordelig',
        ]);

        $vers = $lead->fresh();
        $this->assertSame('m3', $vers->space_unit);
        $this->assertSame('poor', $vers->insulation);
        $this->assertSame('budget', $vers->tier?->value);
    }

    #[Test]
    public function iets_wat_nergens_op_slaat_wordt_overgeslagen_en_gemeld(): void
    {
        $lead = Lead::factory()->create(['status' => 'calling', 'space_unit' => 'm2']);

        $this->pasToe($lead, [
            'space_unit' => 'weet ik niet',
            'building_year' => 1975,
        ]);

        $vers = $lead->fresh();
        $this->assertSame('m2', $vers->space_unit, 'De oude waarde hoort te blijven staan.');
        $this->assertSame(1975, $vers->building_year, 'De rest gaat gewoon door.');

        // Stil weglaten betekent dat niemand weet of het niet gevraagd is of
        // niet begrepen.
        $this->assertDatabaseHas('lead_events', [
            'lead_id' => $lead->id,
            'type' => 'lead_field_ignored',
        ]);
    }

    #[Test]
    public function een_vage_datum_laat_de_rest_met_rust(): void
    {
        $lead = Lead::factory()->create(['status' => 'calling']);

        // "ergens in het najaar" is een prima antwoord aan de telefoon.
        $this->pasToe($lead, ['desired_start' => 'ergens in het najaar', 'rooms_count' => 3]);

        $vers = $lead->fresh();
        $this->assertNull($vers->desired_start);
        $this->assertSame(3, $vers->rooms_count);
    }

    #[Test]
    public function een_te_lang_antwoord_wordt_ingekort_in_plaats_van_geweigerd(): void
    {
        $lead = Lead::factory()->create(['status' => 'calling']);

        $this->pasToe($lead, ['outdoor_unit_placement' => str_repeat('achter de schuur ', 40)]);

        $vers = $lead->fresh();
        $this->assertNotNull($vers->outdoor_unit_placement);
        $this->assertLessThanOrEqual(255, mb_strlen((string) $vers->outdoor_unit_placement));
    }

    #[Test]
    public function een_getalsveld_met_tekst_erin_wordt_niet_nul(): void
    {
        // (int) "onbekend" is 0, en een bouwjaar 0 ziet er ingevuld uit.
        // Leeg beginnen, anders toont de factory-waarde niets aan.
        $lead = Lead::factory()->create(['status' => 'calling', 'building_year' => null]);

        $this->pasToe($lead, ['building_year' => 'onbekend', 'floor_level' => 2]);

        $vers = $lead->fresh();
        $this->assertNull($vers->building_year);
        $this->assertSame(2, $vers->floor_level);
    }
}
