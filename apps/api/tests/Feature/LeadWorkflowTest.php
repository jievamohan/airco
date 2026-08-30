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
use App\Services\QuoteBuilder;
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
    public function het_verrijken_kiest_geen_kwaliteitsklasse_voor_je(): void
    {
        // De standaardklasse is een terugvaloptie, geen besluit. Hem hier
        // wegschrijven maakte er in het dashboard "Midden" van, alsof iemand
        // dat gekozen had.
        $lead = Lead::factory()->create(['tier' => null, 'space_size' => 40, 'space_unit' => 'm2']);

        app(LeadWorkflow::class)->enrich($lead);

        $this->assertNull($lead->fresh()->tier, 'De klasse hoort leeg te blijven.');
    }

    #[Test]
    public function een_offerte_zonder_gekozen_klasse_valt_terug_op_de_standaard(): void
    {
        $lead = Lead::factory()->create(['tier' => null, 'space_size' => 40, 'space_unit' => 'm2']);
        app(LeadWorkflow::class)->enrich($lead);

        $quote = app(QuoteBuilder::class)->createForLead($lead->fresh(), QuoteKind::Indication);

        $this->assertGreaterThan(0, $quote->total_cents, 'Er hoort gewoon een offerte uit te komen.');
        $this->assertNull($lead->fresh()->tier, 'En de lead blijft ook daarna zonder keuze staan.');
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
    public function dezelfde_woning_met_een_ander_mailadres_blijft_een_lead(): void
    {
        // Op contactgegevens sleutelen leverde twee leads voor één woning op.
        Queue::fake();
        $intake = app(LeadIntake::class);
        $adres = ['address' => 'Valkenboslaan 249', 'postcode' => '2563CW', 'city' => 'Den Haag', 'phone' => '0648690000'];

        $eerste = $intake->capture($adres + ['name' => 'R. Mohan', 'email' => 'prive@example.nl'], 'web_form');
        $tweede = $intake->capture($adres + ['name' => 'R. Mohan', 'email' => 'zakelijk@example.nl'], 'web_form');

        $this->assertFalse($tweede['created']);
        $this->assertSame($eerste['lead']->id, $tweede['lead']->id);
    }

    #[Test]
    public function een_andere_woning_van_dezelfde_klant_wordt_een_eigen_lead(): void
    {
        // Sizing, opname en montage hangen aan de locatie, niet aan de persoon.
        Queue::fake();
        $intake = app(LeadIntake::class);
        $contact = ['name' => 'R. Mohan', 'email' => 'r@example.nl', 'phone' => '0648690000'];

        $intake->capture($contact + ['address' => 'Valkenboslaan 249', 'postcode' => '2563CW'], 'web_form');
        $tweede = $intake->capture($contact + ['address' => 'Keizersgracht 12', 'postcode' => '1015CW'], 'web_form');

        $this->assertTrue($tweede['created']);
        $this->assertSame(2, Lead::count());
    }

    #[Test]
    public function een_aanvraag_na_verlies_hangt_niet_aan_de_afgesloten_lead(): void
    {
        // Dit gebeurde op productie: de lead ging op verloren, een minuut later
        // kwam er een aanvraag binnen, en die werd eraan geplakt. Aan een
        // verloren lead gebeurt niets meer, dus die aanvraag was dood.
        Queue::fake();
        $intake = app(LeadIntake::class);
        $aanvraag = ['name' => 'R. Mohan', 'email' => 'r@example.nl', 'phone' => '0648690000', 'address' => 'Valkenboslaan 249', 'postcode' => '2563CW'];

        $eerste = $intake->capture($aanvraag, 'web_form')['lead'];
        $eerste->forceFill(['status' => 'lost'])->save();

        $tweede = $intake->capture($aanvraag, 'web_form');

        $this->assertTrue($tweede['created'], 'Een afgesloten lead hoort geen nieuwe aanvraag meer op te vangen.');
        $this->assertNotSame($eerste->id, $tweede['lead']->id);
    }

    #[Test]
    public function een_herhaalde_aanvraag_is_af_te_splitsen_naar_een_eigen_lead(): void
    {
        Queue::fake();
        $intake = app(LeadIntake::class);
        $basis = ['name' => 'R. Mohan', 'email' => 'r@example.nl', 'phone' => '0648690000', 'address' => 'Valkenboslaan 249', 'postcode' => '2563CW'];

        $bron = $intake->capture($basis + ['space_size' => 80, 'rooms_count' => 3], 'web_form')['lead'];
        $intake->capture($basis + ['space_size' => 15, 'rooms_count' => 1], 'web_form');

        $melding = $bron->events()->where('type', 'lead_duplicate')->latest('id')->firstOrFail();
        $nieuw = $intake->splitFrom($bron, $melding->payload['aanvraag'], 'web_form');

        $this->assertNotSame($bron->id, $nieuw->id);
        $this->assertSame(15.0, (float) $nieuw->space_size, 'De afgesplitste lead draagt de nieuwe maten.');
        $this->assertSame(80.0, (float) $bron->fresh()->space_size, 'En de oorspronkelijke lead blijft zoals hij was.');
        $this->assertDatabaseHas('lead_events', ['lead_id' => $bron->id, 'type' => 'lead_split']);
    }

    #[Test]
    public function een_herhaalde_aanvraag_laat_wel_een_spoor_na(): void
    {
        // Zonder dit is een tweede aanvraag onzichtbaar: geen nieuwe lead, en
        // in de lijst niets dat verandert. Wie net het formulier invulde, zoekt
        // dan tevergeefs.
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-01 09:00:00'));

        $intake = app(LeadIntake::class);
        $attributes = ['name' => 'Jan Jansen', 'email' => 'jan@example.nl', 'phone' => '0612345678'];
        $eerste = $intake->capture($attributes, 'web_form')['lead'];

        Carbon::setTestNow(Carbon::parse('2026-09-01 14:30:00'));
        $intake->capture($attributes, 'web_form');

        $vers = $eerste->refresh();
        $this->assertSame('2026-09-01 14:30:00', $vers->last_request_at->format('Y-m-d H:i:s'));
        $this->assertTrue($vers->last_request_at->greaterThan($vers->created_at));
        $this->assertSame(2, $vers->requests_count);

        Carbon::setTestNow();
    }

    #[Test]
    public function een_afwijkend_antwoord_in_een_herhaalde_aanvraag_verdwijnt_niet_stil(): void
    {
        // Wat er staat is vaak aan de telefoon nagevraagd, dus dat overschrijven
        // we niet. Maar iemand die opnieuw invult met een grotere ruimte moet
        // wel gezien worden.
        Queue::fake();

        $intake = app(LeadIntake::class);
        $attributes = ['name' => 'Jan Jansen', 'email' => 'jan@example.nl', 'phone' => '0612345678', 'space_size' => 40];
        $lead = $intake->capture($attributes, 'web_form')['lead'];

        $intake->capture(['space_size' => 120] + $attributes, 'web_form');

        $this->assertSame(40.0, (float) $lead->refresh()->space_size, 'De nagevraagde waarde blijft staan.');

        $melding = $lead->events()->where('type', 'lead_duplicate')->latest('id')->first();
        $this->assertNotNull($melding);
        $this->assertStringContainsString('ruimtemaat', (string) $melding->description);
        $this->assertStringContainsString('120', (string) $melding->description);
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
