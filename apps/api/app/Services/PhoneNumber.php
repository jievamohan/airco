<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Normaliseert Nederlandse telefoonnummers naar E.164, zoals de telefoniedienst
 * ze verwacht. Levert null bij een nummer dat niet gebeld kan worden.
 */
class PhoneNumber
{
    public function normalise(?string $raw, string $defaultCountryCode = '31'): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+')) {
            $number = '+'.preg_replace('/\D/', '', substr($digits, 1));

            return strlen($number) >= 9 ? $number : null;
        }

        $digits = preg_replace('/\D/', '', $digits) ?? '';

        if (str_starts_with($digits, '00')) {
            $number = '+'.substr($digits, 2);

            return strlen($number) >= 9 ? $number : null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } elseif (str_starts_with($digits, $defaultCountryCode)) {
            $digits = substr($digits, strlen($defaultCountryCode));
        }

        // Een Nederlands abonneenummer is negen cijfers na de landcode.
        if (strlen($digits) !== 9) {
            return null;
        }

        return '+'.$defaultCountryCode.$digits;
    }

    public function isMobile(?string $normalised): bool
    {
        return $normalised !== null && str_starts_with($normalised, '+316');
    }
}
