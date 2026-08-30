<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CallPurpose;
use App\Models\Call;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadWorkflow;
use App\Services\Voice\FakeVoiceAgentClient;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Het belvenster beschermt tegen een agent die uit zichzelf op zondagavond
 * belt. Het hoort niet in de weg te zitten bij een mens die bewust op een knop
 * drukt en weet wie er opneemt.
 */
class BelvensterOverslaanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        $this->app->instance(VoiceAgentClient::class, new FakeVoiceAgentClient);

        // Zondagmiddag: buiten elk venster.
        Carbon::setTestNow(Carbon::parse('2026-08-30 15:45:00', 'Europe/Amsterdam'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function de_gewone_actie_wacht_netjes_op_maandagochtend(): void
    {
        $lead = Lead::factory()->create(['status' => 'new']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/admin/leads/{$lead->uuid}/actions", ['action' => 'call_qualification'])
            ->assertOk();

        $call = Call::where('lead_id', $lead->id)->firstOrFail();
        $this->assertFalse($call->ignores_calling_window);
        $this->assertSame(
            '2026-08-31 09:00',
            $call->scheduled_for?->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'),
        );
    }

    #[Test]
    public function nu_bellen_zet_het_gesprek_meteen_klaar(): void
    {
        $lead = Lead::factory()->create(['status' => 'new']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/admin/leads/{$lead->uuid}/actions", ['action' => 'call_qualification_now'])
            ->assertOk();

        $call = Call::where('lead_id', $lead->id)->firstOrFail();
        $this->assertTrue($call->ignores_calling_window);
        $this->assertTrue($call->scheduled_for?->lessThanOrEqualTo(now()));
    }

    #[Test]
    public function het_conversiegesprek_kan_net_zo_goed_meteen(): void
    {
        // Het conversiegesprek stond ingepland te wachten en was vanuit het
        // dashboard alleen opnieuw in te plannen — nooit af te trappen.
        $lead = Lead::factory()->create(['status' => 'quoted']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/admin/leads/{$lead->uuid}/actions", ['action' => 'call_conversion_now'])
            ->assertOk();

        $call = Call::where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame(CallPurpose::Conversion->value, $call->purpose->value);
        $this->assertTrue($call->ignores_calling_window);
        $this->assertTrue($call->scheduled_for?->lessThanOrEqualTo(now()));
    }

    #[Test]
    public function de_tik_schuift_zon_gesprek_niet_alsnog_vooruit(): void
    {
        // Het venster wordt op twee plekken gecontroleerd; zonder de vlag zou
        // deze tweede controle het gesprek alsnog naar maandag duwen.
        $lead = Lead::factory()->create(['status' => 'new']);
        app(LeadWorkflow::class)->scheduleCall($lead, CallPurpose::Qualification, null, true);

        $this->artisan('agent:tick')->assertExitCode(0);

        $call = Call::where('lead_id', $lead->id)->firstOrFail();
        $this->assertNotSame('queued', $call->fresh()->status, 'Het gesprek had de deur uit gemoeten.');
    }

    #[Test]
    public function een_gewoon_gepland_gesprek_blijft_wel_wachten(): void
    {
        $lead = Lead::factory()->create(['status' => 'new']);
        $call = app(LeadWorkflow::class)->scheduleCall($lead, CallPurpose::Qualification);
        // Doen alsof de tijd al verstreken is, maar het nog steeds zondag is.
        $call?->forceFill(['scheduled_for' => now()->subMinute()])->save();

        $this->artisan('agent:tick')->assertExitCode(0);

        $vers = Call::where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame('queued', $vers->status);
        $this->assertSame(
            '2026-08-31 09:00',
            $vers->scheduled_for?->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'),
        );
    }
}
