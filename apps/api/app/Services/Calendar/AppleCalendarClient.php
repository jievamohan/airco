<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Services\IcsBuilder;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Zet afspraken in een iCloud-agenda via CalDAV.
 *
 * iCloud kent geen publieke REST-API voor agenda's; CalDAV met een
 * app-specifiek wachtwoord is de enige ondersteunde weg. Een gebeurtenis
 * aanmaken is een PUT van een iCalendar-bestand op een unieke URL.
 */
class AppleCalendarClient implements CalendarClient
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly IcsBuilder $ics,
    ) {}

    public function provider(): string
    {
        return 'apple';
    }

    public function createEvent(Appointment $appointment): array
    {
        $username = $this->settings->string('agent.calendar.apple.username');
        $password = $this->settings->string('agent.calendar.apple.app_password');
        $calendarPath = $this->settings->string('agent.calendar.apple.calendar_path');

        if ($username === '' || $password === '' || $calendarPath === '') {
            return ['created' => false, 'event_id' => null, 'calendar_ref' => null, 'error' => 'Apple-agenda is niet (volledig) gekoppeld.'];
        }

        $baseUrl = rtrim($this->settings->string('agent.calendar.apple.base_url', 'https://caldav.icloud.com'), '/');
        $eventUrl = sprintf('%s/%s/%s.ics', $baseUrl, trim($calendarPath, '/'), $appointment->ics_uid);

        try {
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders([
                    'Content-Type' => 'text/calendar; charset=utf-8',
                    'If-None-Match' => '*',
                ])
                ->timeout(20)
                ->withBody($this->ics->forAppointment($appointment), 'text/calendar')
                ->put($eventUrl);
        } catch (Throwable $e) {
            Log::error('Apple CalDAV niet bereikbaar', ['appointment_id' => $appointment->id, 'exception' => $e->getMessage()]);

            return ['created' => false, 'event_id' => null, 'calendar_ref' => null, 'error' => 'De agendadienst was niet bereikbaar.'];
        }

        if ($response->failed()) {
            return [
                'created' => false,
                'event_id' => null,
                'calendar_ref' => null,
                'error' => sprintf('De agendadienst gaf status %d terug.', $response->status()),
            ];
        }

        return [
            'created' => true,
            'event_id' => $appointment->ics_uid,
            'calendar_ref' => $calendarPath,
            'error' => null,
        ];
    }
}
