<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Het minimum voor het eerste wachtwoord van de eigenaar. Niet streng
     * bedoeld — alleen streng genoeg om te voorkomen dat er per ongeluk een
     * account op een publieke site staat waar je zo op binnenloopt.
     */
    private const MINIMALE_WACHTWOORDLENGTE = 12;

    public function run(): void
    {
        // Deze drie zijn geen voorbeelddata maar basisgegevens: zonder
        // instellingen, catalogus en cadans kan de agent niets. Ze werken met
        // firstOrCreate, dus opnieuw draaien voegt hooguit toe wat een nieuwe
        // versie meebrengt en laat aangepaste prijzen staan.
        $this->call([
            SettingsSeeder::class,
            CatalogSeeder::class,
            SequenceSeeder::class,
        ]);

        $this->seedEigenaar();
    }

    /**
     * Maakt eenmalig het account waarmee de eigenaar in het dashboard komt.
     *
     * Bestaat het al, dan blijft het ongemoeid: het wachtwoord dat na de eerste
     * keer inloggen gewijzigd is, mag een volgende deploy niet terugzetten.
     */
    private function seedEigenaar(): void
    {
        $email = (string) config('agent.owner.email');

        if (User::where('email', $email)->exists()) {
            return;
        }

        $wachtwoord = (string) config('agent.owner.initial_password');

        // `.env.example` levert OWNER_INITIAL_PASSWORD leeg op, en een leeg
        // wachtwoord verifieert gewoon. Zonder deze controle zou een seed op
        // productie een account neerzetten waar iedereen op kan inloggen.
        if (strlen($wachtwoord) < self::MINIMALE_WACHTWOORDLENGTE) {
            throw new RuntimeException(
                'OWNER_INITIAL_PASSWORD is leeg of te kort (minimaal '
                .self::MINIMALE_WACHTWOORDLENGTE.' tekens). Vul hem in .env in '
                .'voordat je seedt; anders komt er een dashboardaccount te '
                .'staan waar iedereen op kan inloggen.'
            );
        }

        if (app()->environment('production') && $wachtwoord === 'wachtwoord-wijzigen') {
            throw new RuntimeException(
                'OWNER_INITIAL_PASSWORD staat nog op de standaardwaarde uit de '
                .'ontwikkelomgeving. Kies een eigen wachtwoord voordat je op '
                .'productie seedt.'
            );
        }

        User::create([
            'email' => $email,
            'name' => (string) config('agent.owner.name'),
            'role' => 'owner',
            'password' => Hash::make($wachtwoord),
        ]);
    }
}
