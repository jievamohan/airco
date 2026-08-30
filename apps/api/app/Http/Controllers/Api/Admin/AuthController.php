<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $throttleKey = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => sprintf('Te veel inlogpogingen. Probeer het over %d seconden opnieuw.', RateLimiter::availableIn($throttleKey)),
            ], 429);
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages(['email' => 'Deze combinatie kennen we niet.']);
        }

        RateLimiter::clear($throttleKey);

        // Alleen verlopen tokens opruimen. Alle tokens weggooien zou betekenen
        // dat inloggen op je telefoon je op je laptop uitlogt.
        $user->tokens()->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();

        $remember = (bool) ($credentials['remember'] ?? false);
        $expiresAt = $remember
            ? now()->addDays($this->settings->int('agent.auth.remember_lifetime_days', 30))
            : now()->addMinutes($this->settings->int('agent.auth.session_lifetime_minutes', 480));

        $token = $user->createToken($remember ? 'dashboard (onthouden)' : 'dashboard', ['*'], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'remembered' => $remember,
            'user' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
        ]);
    }

    /**
     * Wijzigt het wachtwoord van de ingelogde gebruiker.
     *
     * Het huidige wachtwoord is verplicht: een sessie die iemand anders heeft
     * overgenomen mag de eigenaar niet uit zijn eigen dashboard kunnen zetten.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            // Zelfde ondergrens als de seeder aanhoudt voor het eerste account.
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [], [
            'current_password' => 'huidig wachtwoord',
            'password' => 'nieuw wachtwoord',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Dit is niet uw huidige wachtwoord.',
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Kies een ander wachtwoord dan het huidige.',
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // Andere sessies eruit: wie zijn wachtwoord wijzigt omdat het ergens
        // rondslingert, heeft er niets aan als de oude tokens blijven werken.
        // Het token van dit apparaat blijft staan, anders logt de wijziging je
        // meteen zelf uit.
        $huidige = $user->currentAccessToken();
        $user->tokens()->when(
            $huidige !== null,
            static fn ($query) => $query->where('id', '!=', $huidige->getKey()),
        )->delete();

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
