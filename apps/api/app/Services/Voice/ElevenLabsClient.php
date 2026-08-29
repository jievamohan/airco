<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Call;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Start uitgaande gesprekken via ElevenLabs Conversational AI.
 *
 * De agent zelf (stem, prompt, taal, tools) wordt in het ElevenLabs-dashboard
 * beheerd; wij leveren per gesprek de dynamische variabelen aan en ontvangen
 * het resultaat terug via de post-call webhook.
 */
class ElevenLabsClient implements VoiceAgentClient
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function startCall(Call $call, string $toNumber, array $variables): array
    {
        $apiKey = $this->settings->string('agent.elevenlabs.api_key');
        $agentId = $this->settings->string('agent.elevenlabs.agent_id');
        $phoneNumberId = $this->settings->string('agent.elevenlabs.agent_phone_number_id');

        if ($apiKey === '' || $agentId === '' || $phoneNumberId === '') {
            return [
                'provider_call_id' => null,
                'conversation_id' => null,
                'accepted' => false,
                'message' => 'ElevenLabs is niet volledig geconfigureerd (API-sleutel, agent-id of telefoonnummer-id ontbreekt).',
            ];
        }

        $baseUrl = rtrim($this->settings->string('agent.elevenlabs.base_url', 'https://api.elevenlabs.io'), '/');
        $timeout = $this->settings->int('agent.elevenlabs.timeout', 20);

        try {
            $response = Http::withHeaders(['xi-api-key' => $apiKey])
                ->timeout($timeout)
                ->acceptJson()
                ->post($baseUrl.'/v1/convai/twilio/outbound-call', [
                    'agent_id' => $agentId,
                    'agent_phone_number_id' => $phoneNumberId,
                    'to_number' => $toNumber,
                    'conversation_initiation_client_data' => [
                        'dynamic_variables' => $variables,
                    ],
                ]);
        } catch (Throwable $e) {
            Log::error('Uitbellen via ElevenLabs mislukt', ['call_id' => $call->id, 'exception' => $e->getMessage()]);

            return [
                'provider_call_id' => null,
                'conversation_id' => null,
                'accepted' => false,
                'message' => 'De telefoniedienst was niet bereikbaar.',
            ];
        }

        if ($response->failed()) {
            Log::warning('ElevenLabs weigerde het gesprek', ['call_id' => $call->id, 'status' => $response->status()]);

            return [
                'provider_call_id' => null,
                'conversation_id' => null,
                'accepted' => false,
                'message' => sprintf('De telefoniedienst gaf status %d terug.', $response->status()),
            ];
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return [
            'provider_call_id' => isset($body['callSid']) ? (string) $body['callSid'] : null,
            'conversation_id' => isset($body['conversation_id']) ? (string) $body['conversation_id'] : null,
            'accepted' => (bool) ($body['success'] ?? true),
            'message' => isset($body['message']) ? (string) $body['message'] : null,
        ];
    }
}
