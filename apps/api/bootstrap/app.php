<?php

use App\Http\Middleware\VerifyElevenLabsSignature;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'elevenlabs.signature' => VerifyElevenLabsSignature::class,
        ]);

        // Deze applicatie serveert alleen JSON: een niet-ingelogd verzoek hoort
        // een 401 te krijgen, niet een redirect naar een inlogpagina die hier
        // niet bestaat.
        $middleware->redirectGuestsTo(static fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never leak SQL, SQLSTATE, connection details or stack traces to API clients.
        // See .cursor/rules/25-api-no-sql-leak.mdc.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'De ingestuurde gegevens zijn ongeldig.',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Niet geauthenticeerd.'], 401);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Niet gevonden.'], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return response()->json([
                    'message' => $status === 404
                        ? 'Niet gevonden.'
                        : ($e->getMessage() !== '' && $status < 500 ? $e->getMessage() : 'Verzoek kon niet worden verwerkt.'),
                ], $status);
            }

            report($e);

            return response()->json(['message' => 'Er is een onverwachte fout opgetreden.'], 500);
        });
    })->create();
