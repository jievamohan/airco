<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Enriched = 'enriched';
    case Calling = 'calling';
    case Qualified = 'qualified';
    case Indicated = 'indicated';
    case SurveyScheduled = 'survey_scheduled';
    case Surveyed = 'surveyed';
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
            self::Indicated,
            self::SurveyScheduled,
            self::Surveyed,
            self::Quoted,
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

    /**
     * Statussen waarin de lead het verkooptraject al in is: er ligt een bedrag
     * bij de klant of er staat een bezoek gepland. De opvolgcadans mag zo'n
     * lead niet terugzetten naar "in opvolging", want dan lijkt het alsof er
     * nog niets verstuurd is.
     *
     * @return list<self>
     */
    public static function inSalesTraject(): array
    {
        return [self::Indicated, self::SurveyScheduled, self::Surveyed, self::Quoted];
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nieuw',
            self::Enriched => 'Verrijkt',
            self::Calling => 'Wordt gebeld',
            self::Qualified => 'Gekwalificeerd',
            self::Indicated => 'Prijsindicatie verstuurd',
            self::SurveyScheduled => 'Opname ingepland',
            self::Surveyed => 'Opname gedaan',
            self::Quoted => 'Offerte verstuurd',
            self::FollowUp => 'In opvolging',
            self::AppointmentScheduled => 'Installatie ingepland',
            self::Won => 'Gewonnen',
            self::Lost => 'Verloren',
            self::Unreachable => 'Onbereikbaar',
            self::DoNotContact => 'Niet benaderen',
        };
    }
}
