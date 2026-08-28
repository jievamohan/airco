# DB review — lead-to-appointment agent

Alle migraties zitten in `apps/api/database/migrations` en zijn nieuw; er wordt
geen bestaande tabel gewijzigd behalve `users` (één kolom erbij).

## Nieuwe tabellen

| Tabel | Doel | Vreemde sleutels |
|-------|------|------------------|
| `settings` | key/value-instellingen, bewerkbaar in het dashboard | — |
| `catalog_items` | apparatuur, materiaal en toeslagen met inkoop, marge en normtijd | — |
| `leads` | kern van het CRM, met soft deletes | — |
| `lead_events` | onveranderlijke tijdlijn | `lead_id` → `leads`, cascade delete |
| `quotes` | offerteversies per lead | `lead_id` → `leads`, cascade delete |
| `quote_items` | offerteregels met bevroren prijs | `quote_id` cascade, `catalog_item_id` null on delete |
| `calls` | belpogingen, transcript en uitkomst | `lead_id` cascade |
| `appointments` | agenda-afspraken | `lead_id` cascade, `quote_id` null on delete |
| `email_messages` | uitgaande mail per lead | `lead_id` cascade |
| `sequences`, `sequence_steps`, `lead_sequence_runs` | opvolgcadans en de uitvoering ervan | cascade binnen de keten |

## Wijziging op een bestaande tabel

`users` krijgt `role` (string, default `operator`) na `email`. De `down()` laat
de kolom weer vallen. Bestaande rijen krijgen de default; er zijn er nog geen in
productie, want de API is nieuw.

## Reversibiliteit

Elke migratie heeft een `down()` die precies terugdraait wat `up()` doet
(`dropIfExists`, respectievelijk `dropColumn`). De sequence-migratie laat de drie
tabellen in omgekeerde afhankelijkheidsvolgorde vallen, zodat de vreemde sleutels
niet in de weg zitten. `migrate:rollback` is getest tegen sqlite en gebruikt geen
constructies die MySQL 8.4 niet aankan.

## Rollback in productie

1. `php artisan down`
2. `php artisan migrate:rollback --step=N` (N = aantal batches sinds de release)
3. Vorige release van de code terugzetten
4. `php artisan up`

Omdat dit de eerste release van de API is, is de veiligste rollback simpelweg de
API uitzetten: de website blijft dan werken, alleen het formulier kan geen
aanvraag meer wegschrijven. Overweeg in dat geval het formulier tijdelijk terug
te zetten op een `mailto:`-bevestiging.

## Datakeuzes met een reden

* **Geldbedragen zijn integer centen.** Nergens floats voor geld, zodat
  afrondingsverschillen tussen offerte, mail en pdf uitgesloten zijn.
* **Tijdstippen worden in UTC opgeslagen**, met een aparte `timezone`-kolom op
  `appointments` voor de weergave. De agent en het dashboard leveren Nederlandse
  kloktijd aan; `AppointmentScheduler::parseLocal()` zet dat om.
* **`leads.dedupe_hash`** is een sha256 over e-mail plus telefoonnummer. Daarmee
  wordt een herhaalde aanvraag binnen 30 dagen aan de bestaande lead gehangen in
  plaats van als nieuwe lead te verschijnen.
* **`lead_events` wordt nooit bijgewerkt of verwijderd**, alleen aangevuld: de
  tijdlijn moet betrouwbaar zijn, ook als iemand later gegevens aanpast.
* **Soft deletes op `leads`** zodat een per ongeluk verwijderde lead terug te
  halen is; alle onderliggende tabellen hangen aan een harde cascade voor het
  geval de lead echt wordt opgeruimd (AVG-verzoek).

## Indices

Geïndexeerd op wat het dashboard en de scheduler echt filteren: `leads.status`,
`leads.source`, `leads.next_action_at`, `leads.dedupe_hash`, `leads.email`,
`leads.phone`, `calls.scheduled_for`, `calls.status`, `calls.conversation_id`,
`lead_events.occurred_at` en `lead_sequence_runs.next_run_at`.

## Privacy

`calls.transcript` bevat een woordelijk gespreksverslag en dus persoonsgegevens.
Het staat alleen in de database, wordt nooit gelogd en gaat niet mee in de
leadlijst-API. Bij een verwijderverzoek volstaat het verwijderen van de lead:
de cascade ruimt transcripten, offertes, mails en afspraken mee op.

**Aandachtspunt voor de ondernemer:** spreek een bewaartermijn af voor
transcripten en opnames. Die is nu niet automatisch begrensd.
