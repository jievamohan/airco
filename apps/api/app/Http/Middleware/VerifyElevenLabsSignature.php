<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controleert de HMAC-handtekening op de post-call webhook.
 *
 * ElevenLabs stuurt een header van de vorm "t=<unix>,v0=<hex hmac>" waarbij de
 * hmac over "<t>.<ruwe body>" is berekend met het webhook-secret. De handtekening
 * wordt constant-time vergeleken en het tijdstempel moet binnen het
 * tolerantievenster liggen, zodat oude verzoeken niet herhaald kunnen worden.
 */
class VerifyElevenLabsSignature
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->settings->string('agent.elevenlabs.webhook_secret');

        if ($secret === '') {
            Log::warning('Webhook geweigerd: er is geen webhook-secret ingesteld.');

            return response()->json(['message' => 'Webhook is niet geconfigureerd.'], 503);
        }

        $header = (string) $request->header('ElevenLabs-Signature', '');
        $parsed = $this->parseHeader($header);

        if ($parsed === null) {
            return response()->json(['message' => 'Ongeldige handtekening.'], 401);
        }

        [$timestamp, $signature] = $parsed;
        $tolerance = $this->settings->int('agent.elevenlabs.webhook_tolerance_seconds', 1800);

        if (abs(time() - $timestamp) > $tolerance) {
            return response()->json(['message' => 'Handtekening is verlopen.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Webhook geweigerd: handtekening komt niet overeen.');

            return response()->json(['message' => 'Ongeldige handtekening.'], 401);
        }

        return $next($request);
    }

    /**
     * @return array{0: int, 1: string}|null
     */
    private function parseHeader(string $header): ?array
    {
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            $part = trim($part);

            if (str_starts_with($part, 't=')) {
                $timestamp = (int) substr($part, 2);
            } elseif (str_starts_with($part, 'v0=')) {
                $signature = substr($part, 3);
            }
        }

        if ($timestamp === null || $timestamp <= 0 || $signature === null || $signature === '') {
            return null;
        }

        return [$timestamp, $signature];
    }
}
