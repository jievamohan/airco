<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Call;
use Illuminate\Support\Str;

/**
 * Gebruikt in proefmodus en in tests: registreert het gesprek wel, belt niet.
 */
class FakeVoiceAgentClient implements VoiceAgentClient
{
    /** @var list<array{call_id: int, to: string, variables: array<string, string>}> */
    public array $started = [];

    public function startCall(Call $call, string $toNumber, array $variables): array
    {
        $this->started[] = ['call_id' => $call->id, 'to' => $toNumber, 'variables' => $variables];

        return [
            'provider_call_id' => 'fake-'.Str::random(12),
            'conversation_id' => 'conv-'.Str::random(12),
            'accepted' => true,
            'message' => 'Proefmodus: er is geen echt gesprek gestart.',
        ];
    }
}
