<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Appointment;

interface CalendarClient
{
    /**
     * Zet de afspraak in de agenda van de ondernemer.
     *
     * @return array{created: bool, event_id: string|null, calendar_ref: string|null, error: string|null}
     */
    public function createEvent(Appointment $appointment): array;

    public function provider(): string;
}
