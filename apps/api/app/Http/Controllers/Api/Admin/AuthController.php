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

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
