<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SystemHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Het levensteken van de wachtrij-worker. De planner zet hem elke minuut klaar;
 * dat hij wordt uitgevoerd, is het bewijs dat er een worker draait.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(SystemHeartbeat $heartbeat): void
    {
        $heartbeat->record('worker');
    }
}
