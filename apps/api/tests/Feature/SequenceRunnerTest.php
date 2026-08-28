<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Mail\ChaseMail;
use App\Models\Lead;
use App\Models\LeadSequenceRun;
use App\Services\LeadWorkflow;
use App\Services\SequenceRunner;
use App\Services\Voice\FakeVoiceAgentClient;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SequenceRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        $this->app->instance(VoiceAgentClient::class, new FakeVoiceAgentClient);
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00', 'Europe/Amsterdam'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function de_cadans_wisselt_bel_en_mailstappen_af_en_stopt_op_onbereikbaar(): void
    {
        $workflow = app(LeadWorkflow::class);
        $runner = app(SequenceRunner::class);

        $lead = Lead::factory()->create(['status' => 'follow_up']);
        $run = $workflow->startChase($lead);
        $this->assertNotNull($run);

        $uitgevoerd = 0;

        // Zes stappen: doorloop de hele rij door de klok telkens vooruit te zetten.
        for ($i = 0; $i < 8; $i++) {
            $run->refresh();

            if ($run->status !== 'active') {
                break;
            }

            Carbon::setTestNow($run->next_run_at?->copy()->addMinute());
            $uitgevoerd += $runner->runDue();
        }

        $this->assertSame(6, $uitgevoerd, 'Alle zes cadansstappen horen uitgevoerd te worden.');
        $this->assertSame('completed', $run->refresh()->status);
        $this->assertSame(LeadStatus::Unreachable, $lead->refresh()->status);

        Mail::assertSent(ChaseMail::class, 3);
        $this->assertSame(3, $lead->calls()->count(), 'Er horen drie belpogingen uit de cadans te komen.');
        $this->assertSame(3, $lead->refresh()->email_attempts);
    }

    #[Test]
    public function een_stap_wordt_pas_uitgevoerd_als_de_wachttijd_voorbij_is(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'follow_up']);
        $workflow->startChase($lead);

        $this->assertSame(0, app(SequenceRunner::class)->runDue(), 'Direct na de start is nog niets due.');

        Carbon::setTestNow(now()->addMinutes(21));

        $this->assertSame(1, app(SequenceRunner::class)->runDue());
    }

    #[Test]
    public function de_cadans_stopt_zodra_de_lead_reageert(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'follow_up']);
        $workflow->startChase($lead);

        $workflow->stopChase($lead, 'Lead heeft gereageerd.');

        $run = LeadSequenceRun::where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame('stopped', $run->status);
        $this->assertNull($run->next_run_at);

        Carbon::setTestNow(now()->addDays(3));
        $this->assertSame(0, app(SequenceRunner::class)->runDue());
    }

    #[Test]
    public function een_dubbele_start_maakt_geen_tweede_cadans_aan(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'follow_up']);

        $workflow->startChase($lead);
        $workflow->startChase($lead);

        $this->assertSame(1, LeadSequenceRun::where('lead_id', $lead->id)->count());
    }

    #[Test]
    public function een_lead_zonder_mailadres_slaat_de_mailstappen_over_zonder_te_breken(): void
    {
        $workflow = app(LeadWorkflow::class);
        $lead = Lead::factory()->create(['status' => 'follow_up', 'email' => null]);
        $run = $workflow->startChase($lead);
        $this->assertNotNull($run);

        for ($i = 0; $i < 8; $i++) {
            $run->refresh();

            if ($run->status !== 'active') {
                break;
            }

            Carbon::setTestNow($run->next_run_at?->copy()->addMinute());
            app(SequenceRunner::class)->runDue();
        }

        Mail::assertNotSent(ChaseMail::class);
        $this->assertSame(LeadStatus::Unreachable, $lead->refresh()->status);
        $this->assertContains('email_skipped', $lead->events()->pluck('type')->all());
    }
}
