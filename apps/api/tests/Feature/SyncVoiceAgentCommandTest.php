<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Het commando bouwt de agentconfiguratie uit het runbook. Wat het verstuurt
 * moet kloppen met wat ElevenLabs verwacht én met wat onze webhook uitleest —
 * dat laatste is precies waarom het niet met de hand overgetypt wordt.
 */
class SyncVoiceAgentCommandTest extends TestCase
{
    #[Test]
    public function zonder_stem_doet_het_niets(): void
    {
        // De melding komt uit de gedeelde service en mag dus niet naar een
        // commandovlag verwijzen: het dashboard gebruikt dezelfde code.
        $this->artisan('voice:agent-sync')
            ->expectsOutputToContain('Geen voice_id opgegeven')
            ->assertExitCode(1);
    }

    #[Test]
    public function zonder_sleutel_wordt_er_niets_verstuurd(): void
    {
        Http::fake();
        putenv('ELEVENLABS_API_KEY');

        $this->artisan('voice:agent-sync', ['--voice' => 'stem123'])
            ->expectsOutputToContain('ELEVENLABS_API_KEY')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    #[Test]
    public function een_proefdraai_verstuurt_niets(): void
    {
        Http::fake();

        $this->artisan('voice:agent-sync', ['--voice' => 'stem123', '--dry-run' => true])
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    #[Test]
    public function de_configuratie_komt_uit_het_runbook(): void
    {
        Http::fake(['*' => Http::response(['agent_id' => 'agent_abc'], 200)]);
        putenv('ELEVENLABS_API_KEY=sleutel-uit-de-omgeving');

        $this->artisan('voice:agent-sync', ['--voice' => 'stem123'])->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            $this->assertSame('POST', $request->method());
            $this->assertStringEndsWith('/v1/convai/agents/create', $request->url());
            $this->assertSame('sleutel-uit-de-omgeving', $request->header('xi-api-key')[0]);

            // De openingszin krijgt de agent per gesprek mee; hier hoort de
            // variabele te staan en geen uitgeschreven zin.
            $this->assertSame('{{gespreksopening}}', $body['conversation_config']['agent']['first_message']);
            $this->assertSame('nl', $body['conversation_config']['agent']['language']);
            $this->assertSame('stem123', $body['conversation_config']['tts']['voice_id']);

            $prompt = $body['conversation_config']['agent']['prompt']['prompt'];
            $this->assertStringContainsString('{{gesprekstype}}', $prompt);
            $this->assertStringContainsString('digitale assistent', $prompt);
            $this->assertStringNotContainsString('```', $prompt, 'De markdown-hekjes horen er niet in.');

            return true;
        });
    }

    #[Test]
    public function elk_veld_uit_het_runbook_gaat_mee_met_een_geldig_type(): void
    {
        Http::fake(['*' => Http::response(['agent_id' => 'agent_abc'], 200)]);
        putenv('ELEVENLABS_API_KEY=sleutel');

        $this->artisan('voice:agent-sync', ['--voice' => 'stem123'])->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $velden = $request->data()['platform_settings']['data_collection'];

            // outcome stuurt de hele workflow aan; zonder dat veld gebeurt er
            // na het gesprek niets.
            $this->assertArrayHasKey('outcome', $velden);
            $this->assertArrayHasKey('appointment_start', $velden);
            $this->assertArrayHasKey('needs_condensate_pump', $velden);

            $this->assertSame('boolean', $velden['needs_condensate_pump']['type']);
            $this->assertSame('number', $velden['rooms_count']['type']);
            $this->assertSame('string', $velden['outcome']['type']);

            foreach ($velden as $naam => $veld) {
                $this->assertContains(
                    $veld['type'],
                    ['boolean', 'string', 'integer', 'number'],
                    "Veld {$naam} heeft een type dat ElevenLabs niet kent."
                );
                $this->assertNotSame('', $veld['description'], "Veld {$naam} heeft geen beschrijving.");
                $this->assertStringNotContainsString('`', $veld['description']);
            }

            return true;
        });
    }

    #[Test]
    public function met_een_bestaande_agent_wordt_er_bijgewerkt_en_niet_bijgemaakt(): void
    {
        // Twee keer draaien mag geen tweede agent opleveren.
        Http::fake(['*' => Http::response([], 200)]);
        putenv('ELEVENLABS_API_KEY=sleutel');

        $this->artisan('voice:agent-sync', ['--voice' => 'stem123', '--agent' => 'agent_bestaand'])
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $this->assertSame('PATCH', $request->method());
            $this->assertStringEndsWith('/v1/convai/agents/agent_bestaand', $request->url());

            return true;
        });
    }

    #[Test]
    public function een_fout_van_elevenlabs_komt_zichtbaar_terug(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'voice_id bestaat niet'], 422)]);
        putenv('ELEVENLABS_API_KEY=sleutel');

        $this->artisan('voice:agent-sync', ['--voice' => 'bestaat-niet'])
            ->expectsOutputToContain('422')
            ->assertExitCode(1);
    }
}
