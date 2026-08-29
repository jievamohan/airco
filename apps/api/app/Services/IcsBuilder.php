<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;

/**
 * Bouwt een iCalendar-bestand voor een afspraak. Wordt zowel als bijlage naar
 * de klant gestuurd als naar iCloud geschreven via CalDAV.
 */
class IcsBuilder
{
    public function forAppointment(Appointment $appointment): string
    {
        $lead = $appointment->lead;
        $company = (string) config('agent.company.name');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$company.'//Lead agent//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:'.$appointment->ics_uid,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$appointment->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$appointment->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($appointment->title),
            'DESCRIPTION:'.$this->escape($appointment->notes ?? ''),
            'LOCATION:'.$this->escape($appointment->location ?? $lead->displayLocation()),
            'ORGANIZER;CN='.$this->escape($company).':mailto:'.(string) config('agent.company.email'),
            'STATUS:CONFIRMED',
        ];

        if ($lead->email !== null) {
            $lines[] = 'ATTENDEE;CN='.$this->escape($lead->name).';RSVP=TRUE:mailto:'.$lead->email;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold'], $lines))."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", "\r", ';', ','],
            ['\\\\', '\\n', '', '\;', '\\,'],
            trim($value),
        );
    }

    /**
     * iCalendar staat maximaal 75 octetten per regel toe; langere regels worden
     * gevouwen met een spatie aan het begin van de vervolgregel.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = str_split($line, 74);

        return array_shift($chunks)."\r\n ".implode("\r\n ", $chunks);
    }
}
