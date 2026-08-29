<?php

declare(strict_types=1);

namespace App\Enums;

enum CallOutcome: string
{
    case Answered = 'answered';
    case NoAnswer = 'no_answer';
    case Voicemail = 'voicemail';
    case Busy = 'busy';
    case Failed = 'failed';
    case Declined = 'declined';
    case AppointmentBooked = 'appointment_booked';
    case CallbackRequested = 'callback_requested';
    case DoNotContact = 'do_not_contact';

    public function reachedLead(): bool
    {
        return in_array($this, [
            self::Answered,
            self::Declined,
            self::AppointmentBooked,
            self::CallbackRequested,
            self::DoNotContact,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Answered => 'Opgenomen',
            self::NoAnswer => 'Niet opgenomen',
            self::Voicemail => 'Voicemail',
            self::Busy => 'In gesprek',
            self::Failed => 'Mislukt',
            self::Declined => 'Afgewezen',
            self::AppointmentBooked => 'Afspraak gemaakt',
            self::CallbackRequested => 'Terugbelverzoek',
            self::DoNotContact => 'Wil niet gebeld worden',
        };
    }
}
