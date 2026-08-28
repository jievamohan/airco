<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Appointment;

/**
 * Actief zolang er geen agenda gekoppeld is (of in proefmodus). De afspraak
 * bestaat dan alleen in het CRM en gaat als ICS-bijlage naar de klant.
 */
class NullCalendarClient implements CalendarClient
{
    public function createEvent(Appointment $appointment): array
    {
        return [
            'created' => false,
            'event_id' => null,
            'calendar_ref' => null,
            'error' => 'Er is geen agendakoppeling actief; de afspraak staat alleen in het dashboard.',
        ];
    }

    public function provider(): string
    {
        return 'none';
    }
}
