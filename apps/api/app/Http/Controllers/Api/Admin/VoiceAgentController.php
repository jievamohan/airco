<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsRepository;
use App\Services\Voice\AgentDefinition;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Zet de agent bij ElevenLabs neer vanuit het dashboard.
 *
 * Dezelfde code als `php artisan voice:agent-sync`, voor wie geen shell op de
 * server heeft. De sleutel komt hier uit de instellingen in plaats van uit de
 * omgeving — die staat er toch al, want zonder sleutel belt de agent niet.
 */
class VoiceAgentController extends Controller
{
    public function sync(SettingsRepository $settings, AgentDefinition $definitie): JsonResponse
    {
        $sleutel = $settings->string('agent.elevenlabs.api_key');
        $stem = $settings->string('agent.elevenlabs.voice_id');
        $bestaand = $settings->string('agent.elevenlabs.agent_id');

        if ($sleutel === '') {
            return response()->json([
                'message' => 'Vul eerst de ElevenLabs API-sleutel in en sla de instellingen op.',
            ], 422);
        }

        try {
            $payload = $definitie->payload(
                $stem,
                $settings->string('agent.company.name', 'KlimaatX'),
            );

            $resultaat = $definitie->verstuur(
                $sleutel,
                $payload,
                $bestaand !== '' ? $bestaand : null,
                $settings->string('agent.elevenlabs.base_url', 'https://api.elevenlabs.io'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Het id meteen vastleggen, anders moet iemand het overtypen uit een
        // melding — en dan staat er straks een agent die niemand aanroept.
        if ($resultaat['agent_id'] !== $bestaand) {
            Setting::where('key', 'agent.elevenlabs.agent_id')->update(['value' => $resultaat['agent_id']]);
            $settings->flush();
        }

        return response()->json([
            'ok' => true,
            'agent_id' => $resultaat['agent_id'],
            'bijgewerkt' => $resultaat['bijgewerkt'],
            'velden' => count($payload['platform_settings']['data_collection']),
        ]);
    }
}
