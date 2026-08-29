# Plan — Autonome lead-to-appointment agent workflow + CRM dashboard

Task: `tasks/lead-conversion-agent.md`
Branch: `claude/lead-conversion-agent-2cxnrz`

## 1. Doel

Van binnenkomende lead-e-mail tot ingeplande afspraak zonder handmatige tussenkomst:
mailbox uitlezen → verrijken → bellen met een ElevenLabs voice agent → direct offerte
mailen → na een uur nabellen → afspraak in de agenda → ondernemer informeren. Leads die
niet opnemen worden actief nagejaagd via bel- en mailstappen. Alles is zichtbaar,
bij te sturen en opnieuw af te trappen vanuit een dashboard met CRM.

## 2. Architectuur

Conform `.cursor/rules/10-repo-layout.mdc`:

```
apps/api   Laravel 12 + MySQL   CRM, orkestratie, integraties, /api/**
apps/web   Nuxt 3               landing (bestaand) + /dashboard (nieuw, client-side)
```

De web-app praat uitsluitend via HTTP met de API. Authenticatie voor het dashboard via
Laravel Sanctum bearer-tokens (geen cookie/CSRF-koppeling, want de web-app is statisch).

### Orkestratie

De workflow is een **expliciete toestandsmachine op de lead** plus een **cadans-engine**.
Geen impliciete ketens van jobs die elkaar aanroepen: elke stap schrijft de volgende
actie weg in `lead_sequence_runs.next_run_at`, en een scheduler-tick voert uit wat due is.
Dat maakt elke stap herhaalbaar, uitstelbaar en handmatig herstartbaar vanuit het dashboard.

```
                 ┌──────────────┐
 IMAP poll ─────►│  new         │
 POST /api/leads └──────┬───────┘
                        │ EnrichLeadJob (sizing + conceptofferte)
                 ┌──────▼───────┐
                 │  enriched    │
                 └──────┬───────┘
                        │ StartCallJob(purpose=qualification), binnen belvenster
                 ┌──────▼───────┐   geen gehoor
                 │  calling     ├──────────────► chase-cadans (bel + mail)
                 └──────┬───────┘                        │ max pogingen
                        │ post-call webhook: beantwoord   ▼
                 ┌──────▼───────┐                  ┌────────────┐
                 │  qualified   │                  │ unreachable│
                 └──────┬───────┘                  └────────────┘
                        │ SendQuoteJob (direct)
                 ┌──────▼───────┐
                 │  quoted      │
                 └──────┬───────┘
                        │ +60 min: StartCallJob(purpose=conversion)
                 ┌──────▼───────┐
                 │  follow_up   │
                 └──┬────────┬──┘
        akkoord     │        │ afwijzing
                 ┌──▼──────┐ └──────► lost
                 │appointmt│
                 │scheduled│  BookAppointmentJob → Google / Apple / ICS
                 └──┬──────┘
                    │ afspraak nagekomen
                 ┌──▼──┐
                 │ won │
                 └─────┘
```

Bij élke statusovergang: `LeadEvent` wegschrijven (timeline) en zo nodig
`NotifyOwnerJob` (mail naar de ondernemer).

### Belvenster en cadans

Bellen gebeurt alleen binnen configureerbare vensters (standaard ma–vr 09:00–20:00,
za 10:00–17:00, zo niet). Valt een geplande belactie buiten het venster, dan schuift hij
naar het eerstvolgende geldige moment. De chase-cadans is een rij `sequence_steps`
(kanaal `call` of `email`, wachttijd, doel) en dus in het dashboard te wijzigen zonder code.

Standaardcadans na geen gehoor:

| # | Na | Kanaal | Inhoud |
|---|----|--------|--------|
| 1 | 20 min | call | tweede belpoging |
| 2 | 5 min na stap 1 | email | "we hebben u geprobeerd te bellen" + link naar terugbelvoorkeur |
| 3 | 3 uur | call | derde belpoging |
| 4 | 1 dag | email | offerte/indicatie alsnog toesturen |
| 5 | 1 dag | call | vierde belpoging |
| 6 | 2 dagen | email | laatste bericht, daarna status `unreachable` |

## 3. Datamodel (apps/api)

| Tabel | Doel |
|-------|------|
| `leads` | kerngegevens, status, funnelvelden, verrijkte sizing, contactteller |
| `lead_events` | onveranderlijke timeline (systeem, agent, gebruiker, lead) |
| `calls` | belpogingen, provider-id, status, transcript, samenvatting, uitkomst, verzamelde velden |
| `quotes` / `quote_items` | offerteversies met bevroren catalogus-snapshot |
| `catalog_items` | apparatuur, materiaal en arbeid met inkoopprijs, marge en normtijd |
| `appointments` | agenda-afspraak per provider, met ICS-uid |
| `email_messages` | uitgaande en binnenkomende mail per lead |
| `sequences` / `sequence_steps` / `lead_sequence_runs` | cadans-definitie en -uitvoering |
| `settings` | integratie- en bedrijfsinstellingen als key/value, bewerkbaar in het dashboard |
| `users` | dashboardgebruikers (Sanctum) |

Geldbedragen worden opgeslagen als **integer centen**, nooit als float.

## 4. Integraties

| Integratie | Aanpak | Waarom |
|-----------|--------|--------|
| Mailbox | `webklex/php-imap` (eigen socketprotocol) | `ext-imap` is niet beschikbaar in de containerimage |
| ElevenLabs | Conversational AI `POST /v1/convai/twilio/outbound-call` met `dynamic_variables`; post-call webhook met HMAC-SHA256 handtekening | officiële outbound-flow, transcript en gestructureerde data komen terug via webhook |
| Google Calendar | directe HTTPS-calls met refresh-token → access-token | vermijdt de zware `google/apiclient`-afhankelijkheid |
| Apple Calendar | CalDAV (`PROPFIND`/`PUT` van iCalendar) met app-specifiek wachtwoord | enige ondersteunde weg voor iCloud |
| Offerte-PDF | `dompdf/dompdf` | pure PHP, geen systeembinaries nodig |
| Mail | Laravel Mailer over SMTP | reeds aanwezig |

Elke externe aanroep zit achter een interface met een fake-implementatie, zodat de
volledige workflow zonder netwerk getest kan worden en de agent lokaal in
"dry run"-stand kan draaien.

## 5. Prijs- en tijdmodel

Uitgewerkt in `docs/research/pricing-baseline.md`. Kern:

* Koellast = inhoud × isolatiefactor (30/40/50 W per m³), opgehoogd naar
  standaardklasse 2,0 / 2,5 / 3,5 / 5,0 / 7,1 kW.
* Offerteregel = `hoeveelheid × inkoopprijs × (1 + marge)`; arbeid apart tegen
  verkooptarief per monteursuur.
* Elke offerte legt naast de verkoopprijs ook de **kostprijs** vast (inkoop plus
  arbeid tegen het kostentarief) en daarmee de brutomarge. Onder de ingestelde
  drempel wordt de offerte gemarkeerd, komt er een tijdlijnregel en gaat er een
  waarschuwing naar de ondernemer.
* De geadverteerde **vanaf-prijs** is een ondergrens op het offertetotaal,
  uitgedrukt inclusief btw zodat hij één op één matcht met de advertentie. Het
  optionele **instappakket** topt een eenvoudige instapklus juist af op die
  prijs; dat staat standaard uit omdat het onder de kostprijs kan uitkomen.
* Montageduur = som van normtijden; doorlooptijd op locatie = monteursuren ÷ ploeggrootte.
* Alle parameters zitten in `catalog_items` en `settings` en zijn dus in het dashboard
  aan te passen. De seeder is idempotent en overschrijft nooit gewijzigde records.

## 6. Dashboard (apps/web/pages/dashboard)

| Scherm | Inhoud |
|--------|--------|
| Overzicht | funneltrechter met conversie en uitval per stap, KPI's, gesprekken vandaag |
| Leads | filterbare lijst op status, bron, periode, zoekterm |
| Lead-detail | gegevens bewerken, volledige timeline, transcripten, offertes, afspraken, en per stap een "opnieuw uitvoeren"-knop |
| Catalogus | prijzen, marges en normtijden bewerken |
| Instellingen | mailbox, ElevenLabs, agenda, belvensters, cadans, ondernemersmail |

Dashboardroutes draaien client-side (`ssr: false` via route rules) zodat de landing
statisch gegenereerd blijft.

## 7. Beveiliging

* Alle `/api/admin/**`-routes achter Sanctum; publieke routes beperkt tot
  `POST /api/leads` (rate limited), de offerte-weergave op ondertekend token, en de
  webhook-endpoints met handtekeningverificatie.
* Webhookhandtekeningen constant-time vergeleken, met tolerantievenster op de timestamp.
* Geen PII in logs; transcripten alleen in de database, niet in de logstream.
* Foutafhandeling conform `25-api-no-sql-leak.mdc`: generieke JSON-fouten, echte
  exception alleen server-side gelogd.
* Verzoek- en antwoordpayloads dun conform `47-thin-api-payloads.mdc`.

## 8. Kwaliteitspoorten

* Gate C: `vue-tsc` schoon voor web, PHPStan (larastan) level 6 schoon voor api.
* Gate D: geen secrets in de repo (alles via `.env`), strikte validatie op alle invoer.
* Gate F: Nuxt-build slaagt; dashboard is client-side en belast de statische landing niet.
