<?php

declare(strict_types=1);

namespace App\Services\Voice;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bouwt de agentconfiguratie uit het runbook en zet hem bij ElevenLabs neer.
 *
 * docs/runbooks/voice-agent-prompt.md is de enige bron: hetzelfde document
 * waar VoiceAgentPromptTest de code tegenaan houdt. De identifiers uit §4 zijn
 * het contract met onze webhook — overtypen in een webformulier is precies waar
 * dat contract stilletjes breekt.
 */
class AgentDefinition
{
    /**
     * ElevenLabs weigert een niet-Engelse agent zonder turbo- of flash-v2.5-model:
     *
     *   Non-english Agents must use turbo or flash v2_5.
     *
     * Onze agent spreekt Nederlands, dus die keuze is geen voorkeur maar een
     * eis. Flash heeft van de twee de laagste vertraging, en aan de telefoon
     * hoor je elke wachttijd.
     */
    public const STANDAARD_MODEL = 'eleven_flash_v2_5';

    /** @var list<string> */
    public const TOEGESTANE_MODELLEN = ['eleven_flash_v2_5', 'eleven_turbo_v2_5'];

    /**
     * @return array<string, mixed>
     */
    public function payload(
        string $voiceId,
        string $naam = 'KlimaatX',
        int $duurSeconden = 480,
        string $model = self::STANDAARD_MODEL,
    ): array {
        if (trim($voiceId) === '') {
            throw new RuntimeException('Geen voice_id opgegeven: kies eerst een Nederlandse stem bij ElevenLabs.');
        }

        if (! in_array($model, self::TOEGESTANE_MODELLEN, true)) {
            throw new RuntimeException(sprintf(
                'Model %s kan niet: een Nederlandstalige agent moet %s gebruiken.',
                $model,
                implode(' of ', self::TOEGESTANE_MODELLEN),
            ));
        }

        $document = $this->document();

        return [
            'name' => $naam,
            'conversation_config' => [
                'agent' => [
                    // De openingszin krijgt de agent per gesprek mee.
                    'first_message' => '{{gespreksopening}}',
                    'language' => 'nl',
                    'prompt' => ['prompt' => $this->prompt($document)],
                ],
                'tts' => [
                    'voice_id' => trim($voiceId),
                    'model_id' => $model,
                ],
                'conversation' => ['max_duration_seconds' => $duurSeconden],
            ],
            'platform_settings' => ['data_collection' => $this->velden($document)],
        ];
    }

    /**
     * Maakt de agent aan, of werkt hem bij als er al een id bekend is.
     *
     * @param  array<string, mixed>  $payload
     * @return array{agent_id: string, bijgewerkt: bool}
     */
    public function verstuur(string $apiKey, array $payload, ?string $agentId = null, string $baseUrl = 'https://api.elevenlabs.io'): array
    {
        if (trim($apiKey) === '') {
            throw new RuntimeException('Geen ElevenLabs API-sleutel beschikbaar.');
        }

        $basis = rtrim($baseUrl, '/');
        $bestaand = $agentId !== null && trim($agentId) !== '';

        $verzoek = Http::withHeaders(['xi-api-key' => $apiKey])->timeout(30)->asJson();

        $antwoord = $bestaand
            ? $verzoek->patch($basis.'/v1/convai/agents/'.trim($agentId), $payload)
            : $verzoek->post($basis.'/v1/convai/agents/create', $payload);

        if (! $antwoord->successful()) {
            throw new RuntimeException(sprintf(
                'ElevenLabs antwoordde met %d: %s',
                $antwoord->status(),
                mb_substr($antwoord->body(), 0, 500),
            ));
        }

        $id = (string) ($antwoord->json('agent_id') ?? ($bestaand ? trim((string) $agentId) : ''));

        if ($id === '') {
            throw new RuntimeException('ElevenLabs gaf geen agent-id terug.');
        }

        return ['agent_id' => $id, 'bijgewerkt' => $bestaand];
    }

    public function document(): string
    {
        $pad = base_path('../../docs/runbooks/voice-agent-prompt.md');

        if (! is_file($pad)) {
            throw new RuntimeException('Runbook niet gevonden op '.$pad);
        }

        return (string) file_get_contents($pad);
    }

    private function prompt(string $document): string
    {
        $blokken = explode('```text', $document);

        if (! isset($blokken[1])) {
            throw new RuntimeException('Het promptblok (```text) ontbreekt in het runbook.');
        }

        $prompt = trim(explode('```', $blokken[1])[0]);

        if ($prompt === '') {
            throw new RuntimeException('Het promptblok in het runbook is leeg.');
        }

        return $prompt;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function velden(string $document): array
    {
        $na = explode('## 4. Dataverzameling', $document);

        if (! isset($na[1])) {
            throw new RuntimeException('Sectie 4 (Dataverzameling) ontbreekt in het runbook.');
        }

        $sectie = explode('## 5.', $na[1])[0];

        preg_match_all('/^\| `([a-z_]+)` \| (\w+) \| (.+?) \|\s*$/m', $sectie, $treffers, PREG_SET_ORDER);

        if ($treffers === []) {
            throw new RuntimeException('Geen velden gevonden in sectie 4 van het runbook.');
        }

        $velden = [];

        foreach ($treffers as [, $naam, $type, $beschrijving]) {
            // ElevenLabs kent boolean, string, integer en number; het runbook
            // schrijft "number" voor alles wat telt of meet.
            $velden[$naam] = [
                'type' => match ($type) {
                    'boolean' => 'boolean',
                    'number' => 'number',
                    default => 'string',
                },
                'description' => trim(str_replace('`', '', $beschrijving)),
            ];
        }

        return $velden;
    }
}
