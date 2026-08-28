# Security — lead-to-appointment agent

Status: **PASS**, met twee openstaande punten voor de ondernemer (zie onderaan).

## Toegang

| Endpoint | Bescherming |
|----------|-------------|
| `POST /api/leads` | publiek, `throttle:10,1`, strikte veldallowlist |
| `GET /api/quotes/{token}` | 48 tekens willekeurig token, `throttle:60,1`, alleen alfanumeriek |
| `POST /api/webhooks/elevenlabs/post-call` | HMAC-SHA256-handtekening met tijdvenster |
| `POST /api/admin/login` | `throttle:10,1` plus 5 pogingen per e-mail/IP per 5 minuten |
| `/api/admin/**` overig | Sanctum bearertoken |

Een niet-geauthenticeerd verzoek levert een JSON-401, ook zonder `Accept`-header
(`redirectGuestsTo` geeft `null` terug, want deze applicatie heeft geen inlogpagina).

## Webhookverificatie

`VerifyElevenLabsSignature` leest de header `t=<unix>,v0=<hex>`, berekent
`hash_hmac('sha256', "$t.$body", $secret)` en vergelijkt met `hash_equals`
(constant-time). Verzoeken buiten het tolerantievenster (standaard 30 minuten)
worden geweigerd, wat replay van een onderschept verzoek beperkt. Ontbreekt het
secret, dan antwoordt de endpoint 503 in plaats van door te laten — fail closed.

Getest in `tests/Feature/ElevenLabsWebhookTest.php`: zonder handtekening,
met verkeerd secret en met verlopen tijdstempel geven alle drie een 401.

## Invoervalidatie

`StoreLeadRequest` en `UpdateLeadRequest` werpen 422 bij onbekende sleutels
(`array_diff` vóór validatie). Statusvelden, tellers en tijdstempels staan
bewust niet in de allowlist: die volgen uit de workflow, niet uit een formulier.
`CatalogController` en `SequenceController` doen hetzelfde voor hun eigen
bewerkbare velden. Getest.

## Uitgaande payloads

Conform `47-thin-api-payloads.mdc`. De leadlijst bevat geen e-mail, telefoon,
notities of interne id's — alleen wat de tabel toont; getest met
`assertArrayNotHasKey`. Het leaddetail is uitgebreider omdat het scherm dat toont,
maar gaat via een expliciete resource en niet via `toArray()` op het model.

## Geheimen

Geen enkel secret staat in de repository; alles komt uit `.env` of uit de
`settings`-tabel. Instellingen met `is_secret` gaan **nooit** terug over de lijn:
`SettingController::index` stuurt `value: null` en alleen een `is_set`-vlag. Het
dashboard stuurt een leeg secretveld niet mee, zodat opslaan zonder invullen een
bestaande sleutel niet wist. Getest.

## Foutafhandeling

Conform `25-api-no-sql-leak.mdc`: `bootstrap/app.php` vangt alles onder `/api/**`
af en geeft generieke Nederlandse meldingen terug. `QueryException`,
`PDOException` en onverwachte fouten worden `report()`-ed en komen als
"Er is een onverwachte fout opgetreden." bij de client. Geen SQL, geen SQLSTATE,
geen stacktrace.

## Persoonsgegevens

* Transcripten en contactgegevens staan alleen in de database, nooit in de
  logstream. De integratieklassen loggen bij fouten alleen id's en statuscodes.
* De klant kan in het gesprek "niet meer benaderen" aangeven; dat zet
  `do_not_contact` en stopt alle automatische acties, ook een handmatig
  ingeplande belpoging.
* De cadans stopt uit zichzelf na een configureerbaar maximum, zodat er niet
  eindeloos wordt nagebeld.
* Verwijderen van een lead ruimt via cascade transcripten, offertes, mails en
  afspraken mee op.

## CORS

Alleen de origins uit `DASHBOARD_ORIGINS` mogen de API aanroepen; `supports_credentials`
staat uit (we gebruiken bearertokens, geen cookies).

## Wat de ondernemer nog moet regelen

1. **Bewaartermijn voor gespreksopnames en transcripten.** Die is nu niet
   automatisch begrensd. Spreek een termijn af en laat er een opruimtaak op los.
2. **Toestemming en informatieplicht bij het bellen.** Er wordt gebeld op basis
   van een eigen aanvraag van de klant, wat gerechtvaardigd belang oplevert, maar
   de voice agent moet aan het begin van het gesprek melden dat het een
   AI-assistent is en dat er wordt opgenomen. Dat hoort in de agentprompt bij
   ElevenLabs; wij kunnen het van hieruit niet afdwingen.
