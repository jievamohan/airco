<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Voice\AgentDefinition;
use Illuminate\Console\Command;
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

    public function handle(AgentDefinition $definitie): int
    {
        try {
            $payload = $definitie->payload(
                (string) ($this->option('voice') ?? ''),
                (string) $this->option('naam'),
                (int) $this->option('duur'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $velden = array_keys($payload['platform_settings']['data_collection']);
        $this->line(sprintf(
            'Prompt: %d tekens. Dataverzameling: %d velden (%s, …).',
            strlen($payload['conversation_config']['agent']['prompt']['prompt']),
            count($velden),
            implode(', ', array_slice($velden, 0, 4)),
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
            $this->line('Heb je geen shell? Dan kan het ook vanuit het dashboard:');
            $this->line('  Instellingen → Voice agent → Agent bijwerken bij ElevenLabs.');

            return self::FAILURE;
        }

        try {
            $resultaat = $definitie->verstuur(
                $sleutel,
                $payload,
                (string) ($this->option('agent') ?? '') ?: null,
                (string) config('agent.elevenlabs.base_url', 'https://api.elevenlabs.io'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($resultaat['bijgewerkt'] ? 'Agent bijgewerkt.' : 'Agent aangemaakt.');
        $this->line('  agent-id: '.$resultaat['agent_id']);
        $this->newLine();
        $this->line('Zet dit id in het dashboard onder Instellingen → Voice agent.');
        $this->line('Wat dit commando níét doet: het telefoonnummer koppelen, de webhook');
        $this->line('aanzetten en de sleutels invullen. Dat staat in §0 van het runbook.');

        return self::SUCCESS;
    }
}
