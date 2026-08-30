<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CallOutcome;
use App\Enums\CallPurpose;
use App\Enums\LeadStatus;
use App\Enums\QuoteKind;
use App\Jobs\ProcessNewLeadJob;
use App\Models\Lead;
use App\Models\LeadSequenceRun;
use App\Services\AppointmentScheduler;
use App\Services\LeadIntake;
use App\Services\LeadWorkflow;
use App\Services\Voice\FakeVoiceAgentClient;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private FakeVoiceAgentClient $voice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();

        $this->voice = new FakeVoiceAgentClient;
        $this->app->instance(VoiceAgentClient::class, $this->voice);

        // Dinsdagochtend: ruim binnen het belvenster.
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00', 'Europe/Amsterdam'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function een_binnengekomen_lead_wordt_verrijkt_en_krijgt_een_belafspraak(): void
    {
        $lead = Lead::factory()->create();

        app(LeadWorkflow::class)->enrich($lead);
        $lead->refresh();

        $this->assertSame(LeadStatus::Enriched, $lead->status);
        $this->assertSame(3.5, $lead->estimated_kw);
        $this->assertSame('single_split', $lead->recommended_system?->value);

        $call = app(LeadWorkflow::class)->scheduleCall($lead, CallPurpose::Qualification);

        $this->assertNotNull($call);
        $this->assertSame('queued', $call->status);
        $this->assertSame('+31612345678', $call->to_number);
    }

    #[Test]
    public function een_gesprek_buiten_het_belvenster_schuift_naar_het_eerstvolgende_moment(): void
    {
        // Zondag: er is geen belvenster geconfigureerd.
        Carbon::setTestNow(Carbon::parse('2026-09-06 12:00:00', 'Europe/Amsterdam'));

        $lead = Lead::factory()->create(['status' => 'enriched']);
        $call = app(LeadWorkflow::class)->scheduleCall($lead, CallPurpose::Qualification);

        $this->assertNotNull($call);
        $scheduled = $call->scheduled_for?->setTimezone('Europe/Amsterdam');
        $this->assertNotNull($scheduled);
        $this->assertSame('2026-09-07 09:00', $scheduled->format('Y-m-d H:i'));
    }

    #[Test]
    public function een_lead_zonder_bruikbaar_telefoonnummer_gaat_direct_de_opvolging_in(): void
    {
        $lead = Lead::factory()->create(['status' => 'enriched', 'phone' => 'niet te bellen']);

        $call = app(LeadWorkflow::class)->scheduleCall($lead, CallPurpose::Qualification);

        $this->assertNull($call);
        $this->assertDatabaseHas('lead_sequence_runs', ['lead_id' => $lead->id, 'status' => 'active']);
    }

    #[Test]
    public function een_beantwoord_kwalificatiegesprek_kwalificeert_de_lead(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'enriched']);

        $call = $workflow->scheduleCall($lead, CallPurpose::Qualification);
        $this->assertNotNull($call);
        $workflow->dispatchCall($call);

        $this->assertCount(1, $this->voice->started);
        $this->assertSame('+31612345678', $this->voice->started[0]['to']);

        $workflow->completeCall($call->refresh(), CallOutcome::Answered, 'Agent: goedemiddag', 'Klant wil een offerte.', [
            'rooms_count' => 2,
            'pipe_length_m' => 9,
            'floor_level' => 1,
        ]);

        $lead->refresh();
        $this->assertSame(LeadStatus::Qualified, $lead->status);
        $this->assertSame(2, $lead->rooms_count);
        $this->assertSame(9, $lead->pipe_length_m);
        // Twee ruimtes: het advies verschuift naar multisplit.
        $this->assertSame('multi_split', $lead->recommended_system?->value);
    }

    #[Test]
    public function niet_opnemen_start_de_opvolgcadans_en_verhoogt_de_teller(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'enriched']);

        $call = $workflow->scheduleCall($lead, CallPurpose::Qualification);
        $this->assertNotNull($call);
        $workflow->dispatchCall($call);
        $workflow->completeCall($call->refresh(), CallOutcome::NoAnswer);

        $lead->refresh();
        $this->assertSame(1, $lead->call_attempts);

        $run = LeadSequenceRun::where('lead_id', $lead->id)->first();
        $this->assertNotNull($run);
        $this->assertSame('active', $run->status);
        $this->assertNotNull($run->next_run_at);
    }

    #[Test]
    public function na_het_versturen_van_de_prijsindicatie_staat_het_conversiegesprek_een_uur_later(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'qualified']);

        $indicatie = $workflow->buildQuote($lead);
        $workflow->markIndicationSent($lead, $indicatie);

        $lead->refresh();
        $this->assertSame(LeadStatus::Indicated, $lead->status);
        $this->assertSame(QuoteKind::Indication, $indicatie->refresh()->kind);
        $this->assertSame('sent', $indicatie->status);
        $this->assertStringStartsWith('IND-', $indicatie->number);

        $conversion = $lead->calls()->where('purpose', CallPurpose::Conversion->value)->first();
        $this->assertNotNull($conversion);
        $this->assertSame(
            now()->addHour()->format('Y-m-d H:i'),
            $conversion->scheduled_for?->format('Y-m-d H:i'),
        );
    }

    #[Test]
    public function na_de_offerte_belt_de_agent_om_af_te_sluiten_en_niet_opnieuw_te_converteren(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'surveyed']);

        $offerte = $workflow->buildQuote($lead, QuoteKind::Final);
        $workflow->markQuoteSent($lead, $offerte);

        $lead->refresh();
        $this->assertSame(LeadStatus::Quoted, $lead->status);
        $this->assertStringStartsWith('OFF-', $offerte->refresh()->number);

        $this->assertSame(1, $lead->calls()->where('purpose', CallPurpose::Close->value)->count());
        $this->assertSame(0, $lead->calls()->where('purpose', CallPurpose::Conversion->value)->count());
    }

    #[Test]
    public function een_offerte_hoort_pas_te_bestaan_na_een_opname(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'indicated']);

        $this->assertFalse($workflow->surveyDone($lead), 'Zonder bezoek is er niets gezien.');

        app(AppointmentScheduler::class)->book($lead, null, null, 'survey');
        $this->assertFalse($workflow->surveyDone($lead->refresh()), 'Ingepland is nog niet geweest.');

        $workflow->markSurveyed($lead->refresh(), 'Meterkast heeft ruimte voor een extra groep.');

        $lead->refresh();
        $this->assertTrue($workflow->surveyDone($lead));
        $this->assertSame(LeadStatus::Surveyed, $lead->status);
        $this->assertStringContainsString('extra groep', (string) $lead->notes);
        $this->assertSame('completed', (string) $lead->appointments()->firstOrFail()->status);
    }

    #[Test]
    public function een_herziene_offerte_trekt_een_geboekte_klant_niet_terug_de_funnel_in(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'appointment_scheduled']);

        $workflow->markQuoteSent($lead, $workflow->buildQuote($lead, QuoteKind::Final));

        $lead->refresh();
        $this->assertSame(LeadStatus::AppointmentScheduled, $lead->status, 'De status hoort te blijven staan.');
        $this->assertSame(0, $lead->calls()->whereIn('purpose', [CallPurpose::Conversion->value, CallPurpose::Close->value])->count(),
            'Een geboekte klant hoort niet opnieuw nagebeld te worden.');
        $this->assertContains('quote_resent', $lead->events()->pluck('type')->all());
    }

    #[Test]
    public function een_verstuurde_indicatie_zakt_niet_terug_naar_in_opvolging(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'qualified']);

        $workflow->markIndicationSent($lead, $workflow->buildQuote($lead));
        $workflow->startChase($lead->refresh());

        $this->assertSame(LeadStatus::Indicated, $lead->refresh()->status);
    }

    #[Test]
    public function het_maximale_aantal_belpogingen_zet_de_lead_op_onbereikbaar(): void
    {
        $lead = Lead::factory()->create(['status' => 'follow_up', 'call_attempts' => 4]);

        $call = app(LeadWorkflow::class)->scheduleCall($lead, CallPurpose::Chase);

        $this->assertNull($call);
        $this->assertSame(LeadStatus::Unreachable, $lead->refresh()->status);
    }

    #[Test]
    public function een_lead_die_niet_benaderd_wil_worden_krijgt_geen_acties_meer(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'enriched']);

        $call = $workflow->scheduleCall($lead, CallPurpose::Qualification);
        $this->assertNotNull($call);
        $workflow->dispatchCall($call);
        $workflow->completeCall($call->refresh(), CallOutcome::DoNotContact);

        $lead->refresh();
        $this->assertTrue($lead->do_not_contact);
        $this->assertSame(LeadStatus::DoNotContact, $lead->status);
        $this->assertNull($workflow->scheduleCall($lead, CallPurpose::Chase));
    }

    #[Test]
    public function een_tweede_aanvraag_van_dezelfde_klant_wordt_samengevoegd(): void
    {
        Queue::fake();

        $intake = app(LeadIntake::class);
        $attributes = ['name' => 'Jan Jansen', 'email' => 'jan@example.nl', 'phone' => '0612345678'];

        $eerste = $intake->capture($attributes, 'web_form');
        $tweede = $intake->capture($attributes + ['city' => 'Utrecht'], 'web_form');

        $this->assertTrue($eerste['created']);
        $this->assertFalse($tweede['created']);
        $this->assertSame($eerste['lead']->id, $tweede['lead']->id);
        $this->assertSame('Utrecht', $tweede['lead']->refresh()->city);
        $this->assertSame(1, Lead::count());

        Queue::assertPushed(ProcessNewLeadJob::class, 1);
    }

    #[Test]
    public function elke_stap_belandt_in_de_tijdlijn(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create();

        $workflow->enrich($lead);
        $call = $workflow->scheduleCall($lead->refresh(), CallPurpose::Qualification);
        $this->assertNotNull($call);
        $workflow->dispatchCall($call);
        $workflow->completeCall($call->refresh(), CallOutcome::Answered);

        $types = $lead->events()->pluck('type')->all();

        foreach (['lead_enriched', 'status_changed', 'call_scheduled', 'call_started', 'call_completed'] as $expected) {
            $this->assertContains($expected, $types, sprintf('Timeline mist "%s".', $expected));
        }
    }
}
