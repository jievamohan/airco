<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Enriched = 'enriched';
    case Calling = 'calling';
    case Qualified = 'qualified';
    case Quoted = 'quoted';
    case FollowUp = 'follow_up';
    case AppointmentScheduled = 'appointment_scheduled';
    case Won = 'won';
    case Lost = 'lost';
    case Unreachable = 'unreachable';
    case DoNotContact = 'do_not_contact';

    /**
     * Volgorde waarin de funnel wordt getoond en gemeten.
     *
     * @return list<self>
     */
    public static function funnel(): array
    {
        return [
            self::New,
            self::Enriched,
            self::Calling,
            self::Qualified,
            self::Quoted,
            self::FollowUp,
            self::AppointmentScheduled,
            self::Won,
        ];
    }

    /**
     * Statussen waarin geen automatische acties meer plaatsvinden.
     *
     * @return list<self>
     */
    public static function terminal(): array
    {
        return [self::Won, self::Lost, self::Unreachable, self::DoNotContact];
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminal(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nieuw',
            self::Enriched => 'Verrijkt',
            self::Calling => 'Wordt gebeld',
            self::Qualified => 'Gekwalificeerd',
            self::Quoted => 'Offerte verstuurd',
            self::FollowUp => 'In opvolging',
            self::AppointmentScheduled => 'Afspraak ingepland',
            self::Won => 'Gewonnen',
            self::Lost => 'Verloren',
            self::Unreachable => 'Onbereikbaar',
            self::DoNotContact => 'Niet benaderen',
        };
    }
}
