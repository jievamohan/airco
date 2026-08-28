# Review — lead-to-appointment agent

## Wat er is gebouwd

Een Laravel-API (`apps/api`) die van een binnengekomen aanvraag tot een
ingeplande installatie zelfstandig doorloopt, plus een CRM-dashboard in de
bestaande Nuxt-app (`apps/web/pages/dashboard`).

## Ontwerpkeuzes die uitleg verdienen

**De workflow is een toestandsmachine met een cadans-engine, geen keten van jobs
die elkaar aanroepen.** Elke stap schrijft weg wanneer de volgende actie moet
gebeuren; een scheduler-tick voert uit wat due is. Daardoor kan elke stap
uitgesteld, herhaald en vanuit het dashboard opnieuw afgetrapt worden zonder dat
er iets vastloopt of dubbel gebeurt.

**De opvolgcadans staat in de database, niet in code.** Zes stappen die
bel- en mailmomenten afwisselen, met instelbare wachttijden. De ondernemer kan de
cadans wijzigen zonder deploy.

**Integraties zitten achter een interface met een fake ernaast.** Daardoor draait
de volledige workflow in tests en in proefmodus zonder netwerk, en belt niemand
per ongeluk een echte klant tijdens het ontwikkelen.

**Geld is overal integer centen.** Nergens floats, zodat offerte, mail en pdf
nooit een cent uit elkaar lopen.

**Elke stap schrijft een `lead_event`.** De tijdlijn in het dashboard is daarmee
compleet en betrouwbaar, ook als iemand later gegevens aanpast.

**Bibliotheken zijn vermeden waar één endpoint volstond.** Google Calendar en
CalDAV gaan via directe HTTPS-aanroepen; dat scheelt een grote
afhankelijkheidsboom voor werk dat in enkele tientallen regels past.

## Wat er tijdens het bouwen is rechtgezet

Zes echte fouten, gevonden door tests en door een handmatige doorloop tegen een
draaiende API. De ernstigste: `SettingsRepository` gaf de meegegeven fallback
terug zodra die niet `null` was, waardoor élke instelling uit `.env` genegeerd
werd — proefmodus deed niets, prijsparameters kwamen niet aan. Zie `tests.md`
voor de volledige lijst.

## Wat bewust niet is gedaan

* **WhatsApp en sms.** Het `channel`-veld op `sequence_steps` is voorbereid, maar
  er is geen provider gekoppeld. Toevoegen is één klasse plus een cadansstap.
* **Rollen en rechten in het dashboard.** `users.role` bestaat, maar elke
  ingelogde gebruiker kan alles. Voor een klein team is dat werkbaar; groeit het
  team, dan is dit het eerste wat erbij moet.
* **Uitrol van de API op de VPS.** Beschreven in
  `docs/runbooks/agent-workflow.md`, maar nog handmatig: het vraagt keuzes over
  de VPS-inrichting die met de ondernemer afgestemd moeten worden.
* **Browsertests voor het dashboard.** De Playwright-service is in dit project
  nog steeds uitgesteld.

## Belangrijkste voorbehoud

De prijzen en montagetijden zijn **voorlopig** en afgeleid uit openbaar
marktonderzoek, niet uit de inkoop van KlimaatX. De uitkomsten liggen binnen de
marktrange, maar of er marge op zit, kan alleen de ondernemer beoordelen. Zie
`risk.md` en `docs/research/pricing-baseline.md`. Zet de agent pas op scherp
zodra de eigen cijfers in de catalogus staan.

## Poorten

| Poort | Resultaat |
|-------|-----------|
| Gate C — type-safety | PASS (`vue-tsc` schoon, PHPStan level 6 schoon, geen baseline of ignores) |
| Gate D — security | PASS (`composer audit` en `pnpm audit --prod` schoon, geen secrets in de repo) |
| Gate F — performance | PASS (build slaagt, geen nieuwe runtime-deps voor de web-app) |
| Tests | PASS (78 tests, 320 assertions) |
| Codestijl | PASS (Pint) |
