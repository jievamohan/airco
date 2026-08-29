<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SystemType;
use App\Models\Lead;
use App\Services\SizingCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SizingCalculatorTest extends TestCase
{
    private SizingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SizingCalculator;
    }

    #[Test]
    public function het_rekent_vierkante_meters_om_naar_inhoud_en_kiest_een_standaardklasse(): void
    {
        // 32 m² × 2,6 m = 83,2 m³ × 40 W = 3,3 kW -> klasse 3,5 kW
        $lead = new Lead(['space_size' => 32, 'space_unit' => 'm2', 'rooms_count' => 1, 'building_year' => 1998]);

        $result = $this->calculator->forLead($lead);

        $this->assertSame(3.5, $result['kw']);
        $this->assertSame(SystemType::SingleSplit, $result['system']);
        $this->assertSame(83.2, $result['volume_m3']);
        $this->assertSame(40, $result['factor']);
    }

    #[Test]
    public function een_slecht_geisoleerde_woning_krijgt_een_zwaardere_factor(): void
    {
        $oud = new Lead(['space_size' => 40, 'space_unit' => 'm2', 'building_year' => 1965]);
        $nieuw = new Lead(['space_size' => 40, 'space_unit' => 'm2', 'building_year' => 2018]);

        $this->assertSame(50, $this->calculator->forLead($oud)['factor']);
        $this->assertSame(30, $this->calculator->forLead($nieuw)['factor']);
        $this->assertGreaterThan(
            $this->calculator->forLead($nieuw)['kw'],
            $this->calculator->forLead($oud)['kw'],
        );
    }

    #[Test]
    public function een_expliciete_isolatieopgave_wint_van_het_bouwjaar(): void
    {
        $lead = new Lead(['space_size' => 40, 'space_unit' => 'm2', 'building_year' => 1965, 'insulation' => 'good']);

        $this->assertSame(30, $this->calculator->forLead($lead)['factor']);
    }

    #[Test]
    public function meerdere_ruimtes_leiden_tot_een_multisplitadvies(): void
    {
        $lead = new Lead(['space_size' => 90, 'space_unit' => 'm2', 'rooms_count' => 3]);

        $result = $this->calculator->forLead($lead);

        $this->assertSame(SystemType::MultiSplit, $result['system']);
        $this->assertSame(3, $result['indoor_units']);
    }

    #[Test]
    public function zonder_ruimtemaat_valt_het_terug_op_de_meestverkochte_klasse(): void
    {
        $result = $this->calculator->forLead(new Lead(['name' => 'Zonder maat']));

        $this->assertSame(3.5, $result['kw']);
        $this->assertNull($result['volume_m3']);
    }

    #[Test]
    public function kubieke_meters_worden_niet_nog_eens_vermenigvuldigd(): void
    {
        $lead = new Lead(['space_size' => 83.2, 'space_unit' => 'm3', 'building_year' => 1998]);

        $this->assertSame(83.2, $this->calculator->forLead($lead)['volume_m3']);
    }
}
