<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function eigenaar(): User
    {
        return User::factory()->create([
            'password' => Hash::make('het-huidige-wachtwoord'),
        ]);
    }

    #[Test]
    public function de_eigenaar_kan_zijn_wachtwoord_wijzigen(): void
    {
        $user = $this->eigenaar();
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/password', [
            'current_password' => 'het-huidige-wachtwoord',
            'password' => 'een-nieuw-lang-wachtwoord',
            'password_confirmation' => 'een-nieuw-lang-wachtwoord',
        ])->assertOk()->assertExactJson(['ok' => true]);

        $this->assertTrue(Hash::check('een-nieuw-lang-wachtwoord', $user->fresh()->password));
    }

    #[Test]
    public function zonder_het_huidige_wachtwoord_verandert_er_niets(): void
    {
        // Een overgenomen sessie mag de eigenaar niet uit zijn eigen dashboard
        // kunnen zetten.
        $user = $this->eigenaar();
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/password', [
            'current_password' => 'iets-anders',
            'password' => 'een-nieuw-lang-wachtwoord',
            'password_confirmation' => 'een-nieuw-lang-wachtwoord',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('het-huidige-wachtwoord', $user->fresh()->password));
    }

    #[Test]
    public function een_kort_wachtwoord_wordt_geweigerd(): void
    {
        $user = $this->eigenaar();
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/password', [
            'current_password' => 'het-huidige-wachtwoord',
            'password' => 'kort',
            'password_confirmation' => 'kort',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('het-huidige-wachtwoord', $user->fresh()->password));
    }

    #[Test]
    public function de_herhaling_moet_kloppen(): void
    {
        $user = $this->eigenaar();
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/password', [
            'current_password' => 'het-huidige-wachtwoord',
            'password' => 'een-nieuw-lang-wachtwoord',
            'password_confirmation' => 'een-ander-lang-wachtwoord',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function hetzelfde_wachtwoord_opnieuw_heeft_geen_zin(): void
    {
        $user = $this->eigenaar();
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/password', [
            'current_password' => 'het-huidige-wachtwoord',
            'password' => 'het-huidige-wachtwoord',
            'password_confirmation' => 'het-huidige-wachtwoord',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function andere_apparaten_worden_uitgelogd_en_dit_apparaat_niet(): void
    {
        // Wie zijn wachtwoord wijzigt omdat het ergens rondslingert, heeft er
        // niets aan als de oude tokens blijven werken.
        $user = $this->eigenaar();
        $telefoon = $user->createToken('telefoon');
        $laptop = $user->createToken('laptop');

        $this->withToken($laptop->plainTextToken)
            ->postJson('/api/admin/password', [
                'current_password' => 'het-huidige-wachtwoord',
                'password' => 'een-nieuw-lang-wachtwoord',
                'password_confirmation' => 'een-nieuw-lang-wachtwoord',
            ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $telefoon->accessToken->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $laptop->accessToken->getKey()]);

        // En het token van dit apparaat werkt daarna nog.
        $this->withToken($laptop->plainTextToken)->getJson('/api/admin/me')->assertOk();
    }

    #[Test]
    public function zonder_inloggen_kan_het_niet(): void
    {
        $this->postJson('/api/admin/password', [
            'current_password' => 'x',
            'password' => 'een-nieuw-lang-wachtwoord',
            'password_confirmation' => 'een-nieuw-lang-wachtwoord',
        ])->assertStatus(401);
    }
}
