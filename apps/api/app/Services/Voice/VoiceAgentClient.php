<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Call;

/**
 * Abstractie over de telefonie-agent. De ElevenLabs-implementatie belt echt;
 * de fake laat de volledige workflow zonder netwerk lopen (tests en proefmodus).
 */
interface VoiceAgentClient
{
    /**
     * Start een uitgaand gesprek.
     *
     * @param  array<string, string>  $variables  dynamische variabelen voor de agent-prompt
     * @return array{provider_call_id: string|null, conversation_id: string|null, accepted: bool, message: string|null}
     */
    public function startCall(Call $call, string $toNumber, array $variables): array;
}
