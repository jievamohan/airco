<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Praat rechtstreeks met de Google Calendar API via een refresh-token.
 *
 * Bewust geen google/apiclient: die sleept een grote afhankelijkheidsboom mee
 * terwijl we maar één endpoint nodig hebben.
 */
class GoogleCalendarClient implements CalendarClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function provider(): string
    {
        return 'google';
    }

    public function createEvent(Appointment $appointment): array
    {
        $token = $this->accessToken();

        if ($token === null) {
            return ['created' => false, 'event_id' => null, 'calendar_ref' => null, 'error' => 'Google-agenda is niet (volledig) gekoppeld.'];
        }

        $calendarId = $this->settings->string('agent.calendar.google.calendar_id', 'primary');
        $lead = $appointment->lead;

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->post(sprintf('%s/calendars/%s/events', self::API_BASE, rawurlencode($calendarId)), [
                    'summary' => $appointment->title,
                    'description' => $appointment->notes ?? '',
                    'location' => $appointment->location,
                    'iCalUID' => $appointment->ics_uid,
                    'start' => [
                        'dateTime' => $appointment->starts_at->toRfc3339String(),
                        'timeZone' => $appointment->timezone,
                    ],
                    'end' => [
                        'dateTime' => $appointment->ends_at->toRfc3339String(),
                        'timeZone' => $appointment->timezone,
                    ],
                    'attendees' => $lead->email !== null ? [['email' => $lead->email, 'displayName' => $lead->name]] : [],
                ]);
        } catch (Throwable $e) {
            Log::error('Google Calendar niet bereikbaar', ['appointment_id' => $appointment->id, 'exception' => $e->getMessage()]);

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

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return [
            'created' => true,
            'event_id' => isset($body['id']) ? (string) $body['id'] : null,
            'calendar_ref' => $calendarId,
            'error' => null,
        ];
    }

    private function accessToken(): ?string
    {
        $clientId = (string) config('agent.calendar.google.client_id');
        $clientSecret = (string) config('agent.calendar.google.client_secret');
        $refreshToken = $this->settings->string('agent.calendar.google.refresh_token');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return null;
        }

        /** @var string|null $token */
        $token = Cache::remember('agent.google.access_token', 3000, static function () use ($clientId, $clientSecret, $refreshToken): ?string {
            try {
                $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);
            } catch (Throwable $e) {
                Log::error('Vernieuwen van Google-token mislukt', ['exception' => $e->getMessage()]);

                return null;
            }

            if ($response->failed()) {
                Log::warning('Google weigerde het refresh-token', ['status' => $response->status()]);

                return null;
            }

            $accessToken = $response->json('access_token');

            return is_string($accessToken) ? $accessToken : null;
        });

        return $token;
    }
}
