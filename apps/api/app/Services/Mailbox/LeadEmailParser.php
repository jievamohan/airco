<?php

declare(strict_types=1);

namespace App\Services\Mailbox;

/**
 * Haalt leadgegevens uit de tekst van een binnengekomen e-mail.
 *
 * De parser is bewust label-gestuurd in plaats van afnemer-specifiek: zowel het
 * eigen websiteformulier als de gangbare leadportalen sturen een platte
 * "Label: waarde"-opsomming. Nieuwe labels toevoegen kan zonder nieuwe klasse.
 */
class LeadEmailParser
{
    /** @var array<string, list<string>> */
    private const LABELS = [
        'name' => ['naam', 'volledige naam', 'contactpersoon', 'aanvrager', 'klant'],
        'email' => ['e-mail', 'email', 'e-mailadres', 'emailadres', 'mailadres'],
        'phone' => ['telefoon', 'telefoonnummer', 'tel', 'mobiel', 'gsm'],
        'address' => ['adres', 'straat', 'straatnaam', 'straat en huisnummer'],
        'postcode' => ['postcode', 'post code'],
        'city' => ['plaats', 'woonplaats', 'stad', 'gemeente'],
        'space' => ['ruimtemaat', 'oppervlakte', 'ruimte', 'aantal m2', 'aantal m²', 'inhoud', 'grootte'],
        'rooms' => ['aantal ruimtes', 'aantal kamers', 'ruimtes', 'kamers'],
        'notes' => ['opmerkingen', 'opmerking', 'toelichting', 'bericht', 'vraag', 'omschrijving'],
    ];

    public function parse(string $subject, string $body, ?string $fromEmail = null, ?string $fromName = null): ParsedLead
    {
        $text = $this->normalise($body);
        $fields = $this->extractLabelledFields($text);

        $name = $fields['name'] ?? $fromName ?? $this->nameFromSubject($subject) ?? 'Onbekende aanvrager';
        $email = $fields['email'] ?? $this->firstEmail($text) ?? $fromEmail;
        $phone = $fields['phone'] ?? $this->firstPhone($text);
        $postcode = $this->normalisePostcode($fields['postcode'] ?? $this->firstPostcode($text));

        [$size, $unit] = $this->parseSpace($fields['space'] ?? null, $text);

        $extra = [];

        if (isset($fields['rooms']) && preg_match('/\d+/', $fields['rooms'], $m) === 1) {
            $extra['rooms_count'] = max(1, (int) $m[0]);
        }

        return new ParsedLead(
            name: $this->cleanName($name),
            email: $email !== null ? strtolower(trim($email)) : null,
            phone: $phone,
            address: $fields['address'] ?? null,
            postcode: $postcode,
            city: $fields['city'] ?? null,
            spaceSize: $size,
            spaceUnit: $unit,
            notes: $fields['notes'] ?? null,
            extra: $extra,
        );
    }

    /**
     * @return array<string, string>
     */
    private function extractLabelledFields(string $text): array
    {
        $found = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$rawLabel, $rawValue] = explode(':', $line, 2);
            $label = strtolower(trim(strip_tags($rawLabel)));
            // Uit platgeslagen tabellen komt een afsluitende dubbele punt mee.
            $value = rtrim(trim($rawValue), ": \t");

            if ($value === '') {
                continue;
            }

            foreach (self::LABELS as $field => $aliases) {
                if (isset($found[$field])) {
                    continue;
                }

                if (in_array($label, $aliases, true)) {
                    $found[$field] = $value;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * @return array{0: float|null, 1: string|null}
     */
    private function parseSpace(?string $labelled, string $text): array
    {
        $candidate = $labelled ?? $text;

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(m3|m³|m2|m²|vierkante meter|kubieke meter)/iu', $candidate, $m) !== 1) {
            return [null, null];
        }

        $size = (float) str_replace(',', '.', $m[1]);
        $raw = strtolower($m[2]);
        $unit = ($raw === 'm3' || $raw === 'm³' || $raw === 'kubieke meter') ? 'm3' : 'm2';

        return [$size > 0 ? $size : null, $size > 0 ? $unit : null];
    }

    private function firstEmail(string $text): ?string
    {
        return preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $text, $m) === 1 ? $m[0] : null;
    }

    private function firstPhone(string $text): ?string
    {
        return preg_match('/(?:\+31|0031|0)[\s\-]?\d(?:[\s\-]?\d){7,9}/', $text, $m) === 1 ? trim($m[0]) : null;
    }

    private function firstPostcode(string $text): ?string
    {
        return preg_match('/\b(\d{4})\s?([A-Za-z]{2})\b/', $text, $m) === 1 ? $m[1].' '.strtoupper($m[2]) : null;
    }

    private function normalisePostcode(?string $postcode): ?string
    {
        if ($postcode === null) {
            return null;
        }

        if (preg_match('/(\d{4})\s?([A-Za-z]{2})/', $postcode, $m) !== 1) {
            return null;
        }

        return $m[1].' '.strtoupper($m[2]);
    }

    private function nameFromSubject(string $subject): ?string
    {
        // Veel portalen zetten de naam achter een streepje of "van".
        if (preg_match('/(?:van|from|—|-)\s+([A-Z][\p{L}\'-]+(?:\s+[\p{L}\'-]+){0,3})\s*$/u', trim($subject), $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }

    private function cleanName(string $name): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($name)) ?? $name);

        return $clean === '' ? 'Onbekende aanvrager' : mb_substr($clean, 0, 120);
    }

    /**
     * HTML-mails worden platgeslagen zodat dezelfde label-logica werkt.
     */
    private function normalise(string $body): string
    {
        if (stripos($body, '<') !== false && stripos($body, '>') !== false) {
            $body = preg_replace('#<(br|/tr|/p|/div|/li|/h[1-6])[^>]*>#i', "\n", $body) ?? $body;
            $body = preg_replace('#</t[dh]>#i', ': ', $body) ?? $body;
            $body = strip_tags($body);
            $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $body = str_replace(["\r\n", "\r"], "\n", $body);

        return preg_replace('/[ \t]+/', ' ', $body) ?? $body;
    }
}
