<?php

declare(strict_types=1);

namespace App\Enums;

enum CallPurpose: string
{
    case Qualification = 'qualification';
    case Conversion = 'conversion';
    case Chase = 'chase';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Qualification => 'Kwalificatiegesprek',
            self::Conversion => 'Conversiegesprek',
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
            self::Qualification => 'Ontbrekende technische gegevens ophalen en bevestigen dat de klant een offerte wil ontvangen.',
            self::Conversion => 'De verstuurde offerte doornemen, bezwaren wegnemen en een installatieafspraak inplannen.',
            self::Chase => 'Alsnog contact leggen, kort de aanvraag bevestigen en een geschikt belmoment of afspraak afstemmen.',
            self::Final => 'Laatste poging: vragen of er nog interesse is en anders netjes afsluiten.',
        };
    }
}
