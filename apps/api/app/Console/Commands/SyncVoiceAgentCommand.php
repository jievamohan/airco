<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zet de agent bij ElevenLabs neer vanuit het runbook.
 *
 * De prompt en de dataverzamelingsvelden staan in
 * docs/runbooks/voice-agent-prompt.md, en dat blijft de enige bron: hetzelfde
 * document waar VoiceAgentPromptTest op controleert dat elk veld ook echt door
 * de code wordt uitgelezen. Overtypen in de webinterface is precies waar die
 * afspraak stilletjes breekt — één verkeerd gespelde identifier en die
 * informatie komt nooit in het CRM aan.
 *
 * De API-sleutel komt uit de omgeving en wordt nergens bewaard:
 *
 *   ELEVENLABS_API_KEY=... php artisan voice:agent-sync --voice=<voice_id>
 */
class SyncVoiceAgentCommand extends Command
{
    protected $signature = 'voice:agent-sync
        {--voice= : Het voice_id van de Nederlandse stem}
        {--naam=KlimaatX : Naam van de agent bij ElevenLabs}
        {--agent= : Bestaande agent bijwerken in plaats van een nieuwe aanmaken}
        {--duur=480 : Maximale gespreksduur in seconden}
        {--dry-run : Toon wat er verstuurd zou worden en stuur niets}';

    protected $description = 'Maakt of werkt de ElevenLabs-agent bij vanuit het runbook';

    public function handle(): int
    {
        try {
            $document = $this->document();
            $payload = $this->payload($document);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $velden = array_keys($payload['platform_settings']['data_collection']);
        $this->line(sprintf(
            'Prompt: %d tekens. Dataverzameling: %d velden (%s).',
            strlen($payload['conversation_config']['agent']['prompt']['prompt']),
            count($velden),
            implode(', ', array_slice($velden, 0, 4)).', …',
        ));

        if ($this->option('dry-run')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            $this->info('Proefdraai: er is niets verstuurd.');

            return self::SUCCESS;
        }

        // getenv() en niet env(): de sleutel komt uit de omgeving van dít
        // commando, niet uit .env. Laravel zet het lezen van putenv-waarden
        // bovendien uit tijdens tests, waardoor env() hier niets zou zien.
        $sleutel = (string) (getenv('ELEVENLABS_API_KEY') ?: '');

        if ($sleutel === '') {
            $this->error('Zet ELEVENLABS_API_KEY in de omgeving van dit commando.');
            $this->line('  ELEVENLABS_API_KEY=... php artisan voice:agent-sync --voice=<voice_id>');
            $this->line('Bewust niet uit .env of de instellingen: zo staat de sleutel nergens opgeslagen.');

            return self::FAILURE;
        }

        $agent = (string) ($this->option('agent') ?? '');
        $basis = rtrim((string) config('agent.elevenlabs.base_url', 'https://api.elevenlabs.io'), '/');

        $verzoek = Http::withHeaders(['xi-api-key' => $sleutel])->timeout(30)->asJson();

        $antwoord = $agent === ''
            ? $verzoek->post($basis.'/v1/convai/agents/create', $payload)
            : $verzoek->patch($basis.'/v1/convai/agents/'.$agent, $payload);

        if (! $antwoord->successful()) {
            $this->error('ElevenLabs antwoordde met '.$antwoord->status().':');
            $this->line($antwoord->body());

            return self::FAILURE;
        }

        $id = (string) ($antwoord->json('agent_id') ?? $agent);

        $this->info($agent === '' ? 'Agent aangemaakt.' : 'Agent bijgewerkt.');
        $this->line('  agent-id: '.$id);
        $this->newLine();
        $this->line('Zet dit id in het dashboard onder Instellingen → Voice agent.');
        $this->line('Wat dit commando níét doet: het telefoonnummer koppelen, de webhook');
        $this->line('aanzetten en de sleutels invullen. Dat staat in §0 van het runbook.');

        return self::SUCCESS;
    }

    private function document(): string
    {
        $pad = base_path('../../docs/runbooks/voice-agent-prompt.md');

        if (! is_file($pad)) {
            throw new RuntimeException('Runbook niet gevonden op '.$pad);
        }

        return (string) file_get_contents($pad);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $document): array
    {
        $stem = (string) ($this->option('voice') ?? '');

        if ($stem === '') {
            throw new RuntimeException(
                'Geef --voice mee: het voice_id van de Nederlandse stem die je bij ElevenLabs hebt gekozen.'
            );
        }

        $config = [
            'agent' => [
                // Vast: de openingszin krijgt de agent per gesprek mee.
                'first_message' => '{{gespreksopening}}',
                'language' => 'nl',
                'prompt' => ['prompt' => $this->prompt($document)],
            ],
            'tts' => ['voice_id' => $stem],
            'conversation' => ['max_duration_seconds' => (int) $this->option('duur')],
        ];

        return [
            'name' => (string) $this->option('naam'),
            'conversation_config' => $config,
            'platform_settings' => ['data_collection' => $this->velden($document)],
        ];
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
     * Leest §4 uit, zodat de identifiers bij ElevenLabs per definitie gelijk
     * zijn aan wat de webhook uitleest.
     *
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
            $velden[$naam] = [
                // ElevenLabs kent boolean, string, integer en number; het
                // runbook schrijft "number" voor alles wat telt of meet.
                'type' => $type === 'boolean' ? 'boolean' : ($type === 'number' ? 'number' : 'string'),
                'description' => trim(strip_tags(str_replace('`', '', $beschrijving))),
            ];
        }

        return $velden;
    }
}
