<?php

declare(strict_types=1);

namespace App\Enums;

enum CallPurpose: string
{
    case Qualification = 'qualification';
    case Conversion = 'conversion';
    case Close = 'close';
    case Chase = 'chase';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Qualification => 'Kwalificatiegesprek',
            self::Conversion => 'Conversiegesprek',
            self::Close => 'Afsluitgesprek',
            self::Chase => 'Opvolgpoging',
            self::Final => 'Laatste belpoging',
        };
    }

    /**
     * Instructie die als dynamische variabele naar de voice agent gaat.
     */
    public function objective(): string
    {
        return match ($this) {
            self::Qualification => 'Ontbrekende technische gegevens ophalen en aankondigen dat de klant een vrijblijvende prijsindicatie per mail krijgt.',
            self::Conversion => 'De verstuurde prijsindicatie doornemen, bezwaren wegnemen en een opname ter plaatse inplannen; pas daarna volgt de offerte.',
            self::Close => 'De offerte na de opname doornemen, akkoord vragen en een installatiedatum vastleggen.',
            self::Chase => 'Alsnog contact leggen, kort de aanvraag bevestigen en een geschikt belmoment of afspraak afstemmen.',
            self::Final => 'Laatste poging: vragen of er nog interesse is en anders netjes afsluiten.',
        };
    }

    /**
     * Welke afspraak dit gesprek probeert te maken. Het conversiegesprek plant
     * de opname; pas het afsluitgesprek plant de installatie zelf.
     */
    public function booksAppointmentKind(): string
    {
        return $this === self::Close ? 'installation' : 'survey';
    }
}
