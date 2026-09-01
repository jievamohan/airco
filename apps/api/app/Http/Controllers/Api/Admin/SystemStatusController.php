<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Draaien de twee processen waar de workflow op leunt?
 *
 * Zonder planner wordt er niets ingepland, zonder worker wordt er niets
 * uitgevoerd — en in beide gevallen blijft het dashboard er rustig bij zonder
 * ook maar één foutmelding. Dit is de plek waar dat zichtbaar wordt.
 */
class SystemStatusController extends Controller
{
    public function __invoke(SystemHeartbeat $heartbeat): JsonResponse
    {
        $planner = $heartbeat->status('scheduler');
        $worker = $heartbeat->status('worker');

        // De worker meldt zich alleen als de planner hem werk geeft. Ligt de
        // planner stil, dan zegt een verlopen worker-hartslag niets over de
        // worker zelf; dat als "offline" tonen wijst de verkeerde kant op.
        $workerStand = match (true) {
            $worker['fresh'] => 'online',
            ! $planner['fresh'] => 'unknown',
            default => 'offline',
        };

        return response()->json([
            'scheduler' => $planner + ['state' => $planner['fresh'] ? 'online' : 'offline'],
            'worker' => $worker + ['state' => $workerStand],
            'queue' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
            'fresh_within_seconds' => SystemHeartbeat::VERS_SECONDEN,
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
