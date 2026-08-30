<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dezelfde inrichting als het artisan-commando, maar vanuit het dashboard —
 * voor wie geen shell op de server heeft.
 */
class VoiceAgentSyncEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    private function stel(string $key, ?string $waarde): void
    {
        Setting::where('key', $key)->update(['value' => $waarde]);
        app(SettingsRepository::class)->flush();
    }

    #[Test]
    public function zonder_inloggen_kan_het_niet(): void
    {
        Http::fake();

        $this->postJson('/api/admin/voice/agent-sync')->assertStatus(401);

        Http::assertNothingSent();
    }

    #[Test]
    public function zonder_sleutel_wordt_er_niets_verstuurd(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/admin/voice/agent-sync')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Vul eerst de ElevenLabs API-sleutel in en sla de instellingen op.');

        Http::assertNothingSent();
    }

    #[Test]
    public function zonder_stem_wordt_er_niets_verstuurd(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create());
        $this->stel('agent.elevenlabs.api_key', 'sleutel');

        $this->postJson('/api/admin/voice/agent-sync')
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'voice_id'));

        Http::assertNothingSent();
    }

    #[Test]
    public function de_agent_wordt_aangemaakt_en_het_id_blijft_bewaard(): void
    {
        Http::fake(['*' => Http::response(['agent_id' => 'agent_nieuw'], 200)]);
        Sanctum::actingAs(User::factory()->create());
        $this->stel('agent.elevenlabs.api_key', 'sleutel');
        $this->stel('agent.elevenlabs.voice_id', 'stem123');

        $this->postJson('/api/admin/voice/agent-sync')
            ->assertOk()
            ->assertJsonPath('agent_id', 'agent_nieuw')
            ->assertJsonPath('bijgewerkt', false)
            ->assertJsonPath('velden', 18);

        // Anders moet iemand het id overtypen uit een melding, en staat er
        // straks een agent die niemand aanroept.
        $this->assertSame('agent_nieuw', Setting::where('key', 'agent.elevenlabs.agent_id')->value('value'));

        Http::assertSent(function ($request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertStringEndsWith('/v1/convai/agents/create', $request->url());
            $this->assertSame('sleutel', $request->header('xi-api-key')[0]);
            $this->assertSame('stem123', $request->data()['conversation_config']['tts']['voice_id']);

            return true;
        });
    }

    #[Test]
    public function een_tweede_keer_werkt_bij_en_maakt_niets_nieuws(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        Sanctum::actingAs(User::factory()->create());
        $this->stel('agent.elevenlabs.api_key', 'sleutel');
        $this->stel('agent.elevenlabs.voice_id', 'stem123');
        $this->stel('agent.elevenlabs.agent_id', 'agent_bestaand');

        $this->postJson('/api/admin/voice/agent-sync')
            ->assertOk()
            ->assertJsonPath('bijgewerkt', true)
            ->assertJsonPath('agent_id', 'agent_bestaand');

        Http::assertSent(function ($request): bool {
            $this->assertSame('PATCH', $request->method());
            $this->assertStringEndsWith('/v1/convai/agents/agent_bestaand', $request->url());

            return true;
        });
    }

    #[Test]
    public function een_fout_van_elevenlabs_komt_leesbaar_terug(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'voice_id bestaat niet'], 422)]);
        Sanctum::actingAs(User::factory()->create());
        $this->stel('agent.elevenlabs.api_key', 'sleutel');
        $this->stel('agent.elevenlabs.voice_id', 'bestaat-niet');

        $this->postJson('/api/admin/voice/agent-sync')
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, '422'));

        // Bij een fout hoort er geen half id achter te blijven.
        $this->assertNull(Setting::where('key', 'agent.elevenlabs.agent_id')->value('value'));
    }

    #[Test]
    public function de_sleutel_gaat_nooit_terug_over_de_lijn(): void
    {
        Http::fake(['*' => Http::response(['agent_id' => 'agent_nieuw'], 200)]);
        Sanctum::actingAs(User::factory()->create());
        $this->stel('agent.elevenlabs.api_key', 'geheime-sleutel');
        $this->stel('agent.elevenlabs.voice_id', 'stem123');

        $antwoord = $this->postJson('/api/admin/voice/agent-sync')->assertOk();

        $this->assertStringNotContainsString('geheime-sleutel', $antwoord->getContent() ?: '');
    }
}
