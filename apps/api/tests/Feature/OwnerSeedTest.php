<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Het account waarmee de eigenaar in het dashboard komt, wordt door de seeder
 * aangemaakt. Die seeder draait ook bij elke productiedeploy, dus hier zit de
 * grens tussen "basisgegevens bijwerken" en "een deur openzetten".
 */
class OwnerSeedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function met_een_eigen_wachtwoord_komt_de_eigenaar_erin(): void
    {
        config()->set('agent.owner.initial_password', 'een-lang-eigen-wachtwoord');

        $this->seed(DatabaseSeeder::class);

        $eigenaar = User::where('email', config('agent.owner.email'))->firstOrFail();
        $this->assertSame('owner', $eigenaar->role);
        $this->assertTrue(Hash::check('een-lang-eigen-wachtwoord', $eigenaar->password));
    }

    #[Test]
    public function een_leeg_beginwachtwoord_levert_geen_account_op(): void
    {
        // `.env.example` levert deze sleutel leeg op, en een leeg wachtwoord
        // verifieert gewoon: zonder deze grens staat er na een seed een
        // dashboardaccount op een publieke site waar iedereen op binnenloopt.
        config()->set('agent.owner.initial_password', '');

        $this->expectException(RuntimeException::class);

        try {
            $this->seed(DatabaseSeeder::class);
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    #[Test]
    public function een_kort_beginwachtwoord_levert_geen_account_op(): void
    {
        config()->set('agent.owner.initial_password', 'kort');

        $this->expectException(RuntimeException::class);

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function de_ontwikkelstandaard_mag_niet_op_productie(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('agent.owner.initial_password', 'wachtwoord-wijzigen');

        $this->expectException(RuntimeException::class);

        // De seeder rechtstreeks draaien in plaats van via `db:seed`: dat
        // commando wil op productie eerst een bevestiging, en die vraag heeft
        // niets te maken met wat deze test controleert.
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();
    }

    #[Test]
    public function een_bestaand_account_houdt_zijn_eigen_wachtwoord(): void
    {
        // Na de eerste keer inloggen wijzigt de eigenaar zijn wachtwoord. Een
        // volgende deploy seedt opnieuw en mag dat niet terugdraaien.
        $eigenaar = User::factory()->create([
            'email' => config('agent.owner.email'),
            'password' => Hash::make('het-wachtwoord-van-de-eigenaar'),
        ]);
        $eerder = $eigenaar->password;

        config()->set('agent.owner.initial_password', 'een-heel-ander-wachtwoord');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($eerder, $eigenaar->fresh()->password);
        $this->assertDatabaseCount('users', 1);
    }
}
