# Dependency review

## Nieuw in `apps/api` (Laravel 12, PHP 8.4)

| Pakket | Versie | Waarvoor | Afweging |
|--------|--------|----------|----------|
| `laravel/framework` | ^12.0 | applicatieraamwerk | vastgelegd in `.cursor/rules/10-repo-layout.mdc` |
| `laravel/sanctum` | ^4.0 | bearertokens voor het dashboard | first-party; geen cookie/CSRF-koppeling nodig voor een statische SPA |
| `webklex/php-imap` | ^6.0 | mailbox uitlezen | praat zelf IMAP over een socket, dus de `imap`-extensie hoeft niet in de image |
| `dompdf/dompdf` | ^3.0 | offerte-pdf | pure PHP, geen headless browser of systeembinaries |
| `larastan/larastan` | ^3.0 (dev) | statische analyse | vereist door Gate C |

Bewust **niet** toegevoegd:

* `google/apiclient` — we gebruiken één endpoint van Google Calendar; een directe
  HTTPS-aanroep met een refresh-token scheelt een grote afhankelijkheidsboom.
* Een CalDAV-bibliotheek — een afspraak aanmaken is één `PUT` van een
  iCalendar-bestand; dat schrijven we zelf, inclusief regelvouwing volgens RFC 5545.
* Een HTTP-clientwrapper voor ElevenLabs — `Illuminate\Http\Client` volstaat.

## Gewijzigd in `apps/web`

| Wijziging | Reden |
|-----------|-------|
| `pnpm.overrides.nanoid: ^3.3.18` | `pnpm audit --prod` meldde GHSA (hoog): custom generators kunnen oneindig doorlopen bij size 0. De transitieve versie (3.3.17 via postcss/vite) valt binnen het kwetsbare bereik. Na de override: geen meldingen meer. Typecheck en build blijven groen. |

Er zijn geen nieuwe runtime-afhankelijkheden voor de web-app: het dashboard is
gebouwd met wat Nuxt zelf meebrengt.

## Auditresultaat

```
composer audit  → No security vulnerability advisories found.
pnpm audit --prod → No known vulnerabilities found.
```

Beide draaien voortaan ook in CI (`.github/workflows/ci-deploy.yml`).

## Licenties

Alle toegevoegde pakketten zijn MIT of LGPL-2.1 (`dompdf`, LGPL — gebruikt als
bibliotheek, niet aangepast). Geen copyleft-verplichting op onze eigen code.
