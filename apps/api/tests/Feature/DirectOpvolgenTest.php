<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DispatchCallJob;
use App\Jobs\ProcessNewLeadJob;
use App\Models\Lead;
use App\Services\LeadWorkflow;
use App\Services\Notifier;
use App\Services\SettingsRepository;
use App\Services\Voice\FakeVoiceAgentClient;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * De demostand belt meteen bij binnenkomst en laat het belvenster los, zodat
 * een demo buiten kantooruren de hele keten laat zien in plaats van een
 * gesprek voor de volgende ochtend in te plannen.
 *
 * Staat hij uit, dan verandert er niets: dat is de stand waarin echte klanten
 * gebeld worden.
 */
class DirectOpvolgenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        $this->app->instance(VoiceAgentClient::class, new FakeVoiceAgentClient);

        // Woensdagnacht, ruim buiten elk belvenster.
        Carbon::setTestNow(Carbon::parse('2026-09-02 23:40:00', 'Europe/Amsterdam'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function directOpvolgen(bool $aan): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('agent.workflow.follow_up_immediately', $aan);
        $settings->flush();
    }

    private function verwerkAanvraag(Lead $lead): void
    {
        (new ProcessNewLeadJob($lead->id))->handle(
            app(LeadWorkflow::class),
            app(Notifier::class),
            app(SettingsRepository::class),
        );
    }

    #[Test]
    public function zonder_de_demostand_wacht_een_nachtelijke_aanvraag_op_de_ochtend(): void
    {
        Queue::fake();

        $lead = Lead::factory()->create(['status' => 'new']);
        $this->verwerkAanvraag($lead);

        $call = $lead->calls()->latest('id')->firstOrFail();

        $this->assertSame(
            '2026-09-03 09:00',
            $call->scheduled_for?->timezone('Europe/Amsterdam')->format('Y-m-d H:i'),
        );
        $this->assertFalse((bool) $call->ignores_calling_window);

        Queue::assertNotPushed(DispatchCallJob::class);
    }

    #[Test]
    public function met_de_demostand_gaat_het_gesprek_meteen_de_deur_uit(): void
    {
        Queue::fake();
        $this->directOpvolgen(true);

        $lead = Lead::factory()->create(['status' => 'new']);
        $this->verwerkAanvraag($lead);

        $call = $lead->calls()->latest('id')->firstOrFail();

        $this->assertSame(
            '2026-09-02 23:40',
            $call->scheduled_for?->timezone('Europe/Amsterdam')->format('Y-m-d H:i'),
            'Geen wachttijd en geen belvenster: het gesprek staat op nu.',
        );
        $this->assertTrue((bool) $call->ignores_calling_window);

        // Wachten op de volgende tik is een minuut stilte voor de zaal.
        Queue::assertPushed(DispatchCallJob::class);
    }

    #[Test]
    public function de_hartslag_schuift_een_demogesprek_niet_alsnog_vooruit(): void
    {
        Queue::fake();
        $this->directOpvolgen(true);

        $lead = Lead::factory()->create(['status' => 'new']);
        $this->verwerkAanvraag($lead);

        $call = $lead->calls()->latest('id')->firstOrFail();
        $gepland = $call->scheduled_for;

        $this->artisan('agent:tick')->assertSuccessful();

        $this->assertTrue($call->refresh()->scheduled_for?->eq($gepland) ?? false);
    }

    #[Test]
    public function overdag_blijft_de_gewone_wachttijd_gelden_zodra_de_demostand_uit_staat(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-02 14:00:00', 'Europe/Amsterdam'));
        $this->directOpvolgen(false);

        $lead = Lead::factory()->create(['status' => 'new']);
        $this->verwerkAanvraag($lead);

        $call = $lead->calls()->latest('id')->firstOrFail();

        $this->assertSame('14:03', $call->scheduled_for?->timezone('Europe/Amsterdam')->format('H:i'));
        $this->assertFalse((bool) $call->ignores_calling_window);

        Queue::assertNotPushed(DispatchCallJob::class);
    }
}
