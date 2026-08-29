<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CallOutcome;
use App\Enums\CallPurpose;
use App\Enums\LeadStatus;
use App\Jobs\BookAppointmentJob;
use App\Jobs\SendQuoteJob;
use App\Models\Call;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ElevenLabsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();

        Setting::where('key', 'agent.elevenlabs.webhook_secret')->update(['value' => self::SECRET]);
        app(SettingsRepository::class)->flush();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signedHeaders(array $payload, ?int $timestamp = null, ?string $secret = null): array
    {
        $timestamp ??= time();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? self::SECRET);

        return ['ElevenLabs-Signature' => sprintf('t=%d,v0=%s', $timestamp, $signature)];
    }

    private function callFor(Lead $lead, CallPurpose $purpose = CallPurpose::Qualification): Call
    {
        return $lead->calls()->create([
            'provider' => 'elevenlabs',
            'purpose' => $purpose->value,
            'attempt_no' => 1,
            'status' => 'initiated',
            'conversation_id' => 'conv-test-1',
            'to_number' => '+31612345678',
            'started_at' => now(),
        ]);
    }

    #[Test]
    public function een_verzoek_zonder_handtekening_wordt_geweigerd(): void
    {
        $this->postJson('/api/webhooks/elevenlabs/post-call', ['data' => []])
            ->assertStatus(401)
            ->assertJson(['message' => 'Ongeldige handtekening.']);
    }

    #[Test]
    public function een_verkeerd_ondertekend_verzoek_wordt_geweigerd(): void
    {
        $payload = ['data' => ['conversation_id' => 'conv-test-1']];

        $this->withHeaders($this->signedHeaders($payload, null, 'verkeerd-secret'))
            ->postJson('/api/webhooks/elevenlabs/post-call', $payload)
            ->assertStatus(401);
    }

    #[Test]
    public function een_verlopen_handtekening_wordt_geweigerd(): void
    {
        $payload = ['data' => ['conversation_id' => 'conv-test-1']];

        $this->withHeaders($this->signedHeaders($payload, time() - 7200))
            ->postJson('/api/webhooks/elevenlabs/post-call', $payload)
            ->assertStatus(401)
            ->assertJson(['message' => 'Handtekening is verlopen.']);
    }

    #[Test]
    public function een_beantwoord_kwalificatiegesprek_zet_transcript_weg_en_start_de_offerte(): void
    {
        Queue::fake();

        $lead = Lead::factory()->create(['status' => 'calling']);
        $call = $this->callFor($lead);

        $payload = [
            'type' => 'post_call_transcription',
            'data' => [
                'conversation_id' => 'conv-test-1',
                'status' => 'done',
                'metadata' => ['call_duration_secs' => 142],
                'transcript' => [
                    ['role' => 'agent', 'message' => 'Goedemiddag, u spreekt met KlimaatX.'],
                    ['role' => 'user', 'message' => 'Ja, ik wacht op een offerte.'],
                ],
                'analysis' => [
                    'call_successful' => 'success',
                    'transcript_summary' => 'Klant wil een offerte voor de woonkamer.',
                    'data_collection_results' => [
                        'rooms_count' => ['value' => 1],
                        'pipe_length_m' => ['value' => 7],
                        'floor_level' => ['value' => 0],
                    ],
                ],
            ],
        ];

        $this->withHeaders($this->signedHeaders($payload))
            ->postJson('/api/webhooks/elevenlabs/post-call', $payload)
            ->assertOk();

        $call->refresh();
        $this->assertSame('completed', $call->status);
        $this->assertSame(CallOutcome::Answered, $call->outcome);
        $this->assertStringContainsString('Goedemiddag', (string) $call->transcript);
        $this->assertSame('Klant wil een offerte voor de woonkamer.', $call->summary);

        $lead->refresh();
        $this->assertSame(LeadStatus::Qualified, $lead->status);
        $this->assertSame(7, $lead->pipe_length_m);

        Queue::assertPushed(SendQuoteJob::class);
    }

    #[Test]
    public function een_kort_gesprek_telt_als_niet_opgenomen(): void
    {
        $lead = Lead::factory()->create(['status' => 'calling']);
        $call = $this->callFor($lead);

        $payload = [
            'data' => [
                'conversation_id' => 'conv-test-1',
                'status' => 'done',
                'metadata' => ['call_duration_secs' => 4],
            ],
        ];

        $this->withHeaders($this->signedHeaders($payload))
            ->postJson('/api/webhooks/elevenlabs/post-call', $payload)
            ->assertOk();

        $this->assertSame(CallOutcome::NoAnswer, $call->refresh()->outcome);
        $this->assertDatabaseHas('lead_sequence_runs', ['lead_id' => $lead->id, 'status' => 'active']);
    }

    #[Test]
    public function akkoord_tijdens_het_conversiegesprek_zet_een_afspraak_in_de_wachtrij(): void
    {
        Queue::fake();

        $lead = Lead::factory()->create(['status' => 'quoted']);
        $call = $this->callFor($lead, CallPurpose::Conversion);

        $payload = [
            'data' => [
                'conversation_id' => 'conv-test-1',
                'status' => 'done',
                'metadata' => ['call_duration_secs' => 210],
                'analysis' => [
                    'call_successful' => 'success',
                    'data_collection_results' => [
                        'outcome' => ['value' => 'appointment_booked'],
                        'appointment_start' => ['value' => '2026-09-15 08:00:00'],
                    ],
                ],
            ],
        ];

        $this->withHeaders($this->signedHeaders($payload))
            ->postJson('/api/webhooks/elevenlabs/post-call', $payload)
            ->assertOk();

        $this->assertSame(CallOutcome::AppointmentBooked, $call->refresh()->outcome);
        Queue::assertPushed(BookAppointmentJob::class);
    }

    #[Test]
    public function hetzelfde_gesprek_wordt_niet_twee_keer_verwerkt(): void
    {
        Queue::fake();

        $lead = Lead::factory()->create(['status' => 'calling']);
        $call = $this->callFor($lead);

        $payload = [
            'data' => [
                'conversation_id' => 'conv-test-1',
                'status' => 'done',
                'metadata' => ['call_duration_secs' => 142],
                'analysis' => ['call_successful' => 'success'],
            ],
        ];

        $this->withHeaders($this->signedHeaders($payload))->postJson('/api/webhooks/elevenlabs/post-call', $payload)->assertOk();
        $this->withHeaders($this->signedHeaders($payload))->postJson('/api/webhooks/elevenlabs/post-call', $payload)
            ->assertOk()
            ->assertJson(['message' => 'Al verwerkt.']);

        Queue::assertPushed(SendQuoteJob::class, 1);
        $this->assertSame(1, $lead->refresh()->calls()->count());
    }

    #[Test]
    public function een_onbekend_gesprek_levert_een_nette_404(): void
    {
        $payload = ['data' => ['conversation_id' => 'bestaat-niet']];

        $this->withHeaders($this->signedHeaders($payload))
            ->postJson('/api/webhooks/elevenlabs/post-call', $payload)
            ->assertStatus(404);
    }
}
