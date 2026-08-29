<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Bepaalt of er op een bepaald moment gebeld mag worden en schuift zo nodig
 * door naar het eerstvolgende toegestane moment.
 */
class CallingWindow
{
    private const MAX_LOOKAHEAD_DAYS = 14;

    /** @var array<int, array{0: string, 1: string}> */
    private array $windows;

    private string $timezone;

    public function __construct(SettingsRepository $settings)
    {
        /** @var array<int, array{0: string, 1: string}> $windows */
        $windows = config('agent.calling_windows', []);
        $this->windows = $windows;
        $this->timezone = $settings->string('agent.calendar.timezone', 'Europe/Amsterdam');
    }

    public function isOpenAt(Carbon $moment): bool
    {
        $local = $moment->copy()->setTimezone($this->timezone);
        $window = $this->windows[$local->dayOfWeekIso] ?? null;

        if ($window === null) {
            return false;
        }

        return $local->format('H:i') >= $window[0] && $local->format('H:i') < $window[1];
    }

    /**
     * Eerstvolgende moment vanaf $from waarop gebeld mag worden.
     */
    public function nextOpening(Carbon $from): Carbon
    {
        $candidate = $from->copy()->setTimezone($this->timezone);

        if ($this->isOpenAt($candidate)) {
            return $candidate->setTimezone($from->timezone ?? config('app.timezone'));
        }

        for ($day = 0; $day <= self::MAX_LOOKAHEAD_DAYS; $day++) {
            $probe = $candidate->copy()->addDays($day);
            $window = $this->windows[$probe->dayOfWeekIso] ?? null;

            if ($window === null) {
                continue;
            }

            [$openHour, $openMinute] = array_map('intval', explode(':', $window[0]));
            $opening = $probe->copy()->setTime($openHour, $openMinute);

            if ($opening->greaterThanOrEqualTo($candidate)) {
                return $opening->setTimezone($from->timezone ?? config('app.timezone'));
            }
        }

        // Zou niet moeten voorkomen zolang er minstens één venster is geconfigureerd.
        return $candidate->addDay()->startOfDay()->setTimezone($from->timezone ?? config('app.timezone'));
    }
}
