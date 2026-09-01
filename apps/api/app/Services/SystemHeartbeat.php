<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Levenstekens van de twee processen waar de workflow op draait: de planner
 * (`agent:tick`, elke minuut via cron) en de wachtrij-worker.
 *
 * Beide kunnen omvallen zonder dat er ergens een foutmelding verschijnt — de
 * agent doet dan simpelweg niets meer. Daarom schrijft elk proces zelf een
 * tijdstempel weg en toont het dashboard hoe oud die is.
 */
class SystemHeartbeat
{
    private const PREFIX = 'agent.heartbeat.';

    /** Ruim genomen: de planner slaat een minuut over zonder dat er iets stuk is. */
    public const VERS_SECONDEN = 180;

    private const BEWAARTERMIJN_SECONDEN = 86400;

    public function record(string $proces): void
    {
        Cache::put(self::PREFIX.$proces, now()->toIso8601String(), self::BEWAARTERMIJN_SECONDEN);
    }

    public function lastSeen(string $proces): ?Carbon
    {
        $waarde = Cache::get(self::PREFIX.$proces);

        return is_string($waarde) ? Carbon::parse($waarde) : null;
    }

    /**
     * @return array{last_seen_at: string|null, seconds_ago: int|null, fresh: bool}
     */
    public function status(string $proces): array
    {
        $laatst = $this->lastSeen($proces);

        if ($laatst === null) {
            return ['last_seen_at' => null, 'seconds_ago' => null, 'fresh' => false];
        }

        $geleden = (int) $laatst->diffInSeconds(now(), true);

        return [
            'last_seen_at' => $laatst->toIso8601String(),
            'seconds_ago' => $geleden,
            'fresh' => $geleden <= self::VERS_SECONDEN,
        ];
    }
}
