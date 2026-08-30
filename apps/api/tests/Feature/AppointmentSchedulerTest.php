<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\QuoteKind;
use App\Jobs\BookAppointmentJob;
use App\Mail\AppointmentMail;
use App\Models\Lead;
use App\Services\AppointmentScheduler;
use App\Services\IcsBuilder;
use App\Services\QuoteBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00', 'Europe/Amsterdam'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function voorgestelde_momenten_liggen_op_werkdagen_en_na_de_voorbereidingstijd(): void
    {
        $slots = app(AppointmentScheduler::class)->availableSlots(240, 5);

        $this->assertCount(5, $slots);

        foreach ($slots as $slot) {
            $this->assertLessThanOrEqual(5, $slot->dayOfWeekIso, 'Alleen werkdagen.');
            $this->assertTrue($slot->greaterThan(now()->addHours(47)), 'Minimaal 48 uur vooruit.');
        }
    }

    #[Test]
    public function een_afspraak_wordt_vastgelegd_bevestigd_en_zet_de_lead_op_ingepland(): void
    {
        $lead = Lead::factory()->create(['status' => 'surveyed']);
        $quote = app(QuoteBuilder::class)->createForLead($lead, QuoteKind::Final);

        $appointment = app(AppointmentScheduler::class)->book($lead, $quote, null, 'installation');

        $this->assertSame('installation', $appointment->kind);
        $this->assertSame($quote->onsite_minutes, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at));
        $this->assertSame('none', $appointment->provider);
        $this->assertNotNull($appointment->sync_error, 'Zonder agendakoppeling hoort dat zichtbaar te zijn.');

        $lead->refresh();
        $this->assertSame(LeadStatus::AppointmentScheduled, $lead->status);
        $this->assertSame('accepted', $quote->refresh()->status);

        Mail::assertSent(AppointmentMail::class);
    }

    #[Test]
    public function een_opname_zet_de_lead_op_ingepland_maar_accepteert_de_indicatie_niet(): void
    {
        $lead = Lead::factory()->create(['status' => 'indicated']);
        $indicatie = app(QuoteBuilder::class)->createForLead($lead);

        $appointment = app(AppointmentScheduler::class)->book($lead, $indicatie, null, 'survey');

        $this->assertSame('survey', $appointment->kind);
        $this->assertStringContainsString('Opname', $appointment->title);

        $lead->refresh();
        $this->assertSame(LeadStatus::SurveyScheduled, $lead->status);
        $this->assertNull($lead->won_at, 'Een opname is nog geen opdracht.');

        // Een prijsindicatie is geen aanbod; die kan de klant niet aanvaarden.
        $this->assertSame('draft', $indicatie->refresh()->status);
    }

    #[Test]
    public function een_voorkeursmoment_uit_het_gesprek_wordt_gerespecteerd(): void
    {
        $lead = Lead::factory()->create(['status' => 'follow_up']);
        $wens = Carbon::parse('2026-09-15 08:00:00', 'Europe/Amsterdam');

        $appointment = app(AppointmentScheduler::class)->book($lead, null, $wens);

        $this->assertSame(
            $wens->format('Y-m-d H:i'),
            $appointment->starts_at->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'),
        );
    }

    #[Test]
    public function een_tijd_uit_het_gesprek_wordt_als_nederlandse_kloktijd_gelezen(): void
    {
        $scheduler = app(AppointmentScheduler::class);

        // De voice agent noemt "acht uur 's ochtends", niet acht uur UTC.
        $lokaal = $scheduler->parseLocal('2026-09-15 08:00:00');
        $this->assertNotNull($lokaal);
        $this->assertSame('2026-09-15 08:00', $lokaal->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'));
        $this->assertSame('2026-09-15 06:00', $lokaal->utc()->format('Y-m-d H:i'));

        // Staat er wel een zone bij, dan wint die.
        $metOffset = $scheduler->parseLocal('2026-09-15T08:00:00Z');
        $this->assertNotNull($metOffset);
        $this->assertSame('2026-09-15 10:00', $metOffset->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'));

        $this->assertNull($scheduler->parseLocal(null));
        $this->assertNull($scheduler->parseLocal('  '));
    }

    #[Test]
    public function de_job_boekt_op_het_moment_dat_de_klant_noemde(): void
    {
        $lead = Lead::factory()->create(['status' => 'follow_up']);

        (new BookAppointmentJob($lead->id, '2026-09-15 08:00:00'))->handle(app(AppointmentScheduler::class));

        $appointment = $lead->appointments()->firstOrFail();
        $this->assertSame(
            '2026-09-15 08:00',
            $appointment->starts_at->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'),
        );
    }

    #[Test]
    public function het_ics_bestand_is_geldig_en_bevat_de_klant(): void
    {
        $lead = Lead::factory()->create(['status' => 'follow_up']);
        $appointment = app(AppointmentScheduler::class)->book($lead, null);

        $ics = app(IcsBuilder::class)->forAppointment($appointment);

        $this->assertStringStartsWith('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('UID:'.$appointment->ics_uid, $ics);
        $this->assertStringContainsString('mailto:jan@example.nl', $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);

        foreach (explode("\r\n", trim($ics)) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'iCalendar-regels mogen maximaal 75 tekens zijn.');
        }
    }
}
