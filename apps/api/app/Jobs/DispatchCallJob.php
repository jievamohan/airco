<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Call;
use App\Services\LeadWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Zet een ingeplande belpoging door naar de voice agent.
 */
class DispatchCallJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $callId) {}

    public function handle(LeadWorkflow $workflow): void
    {
        $call = Call::with('lead')->find($this->callId);

        if ($call === null || $call->status !== 'queued') {
            return;
        }

        $workflow->dispatchCall($call);
    }
}
