# Gate F — Performance: PASS

## Bundelomvang van de landingspagina

Het dashboard is toegevoegd zonder nieuwe runtime-afhankelijkheden: het gebruikt
alleen wat Nuxt en Vue al meebrengen, plus `fetch`. De dashboardroutes staan op
`ssr: false` en zijn aparte route-chunks, dus de landingspagina laadt er niets
extra's van; alleen de gedeelde CSS komt erbij (`dashboard.css`, ± 6 kB
ongecomprimeerd).

`pnpm run build` slaagt en genereert 14 routes.

## Databasegebruik

* Alle lijstquery's gaan over geïndexeerde kolommen (`status`, `source`,
  `created_at`, `next_action_at`).
* De leadlijst is gepagineerd (standaard 25, maximaal 100) en laadt `latestQuote`
  eager, zodat er geen n+1 op offertes ontstaat.
* Het leaddetail laadt tijdlijn, gesprekken, offerteregels, afspraken en mails in
  één keer eager.
* `Model::preventLazyLoading()` staat aan buiten productie, zodat een n+1 tijdens
  ontwikkeling meteen een uitzondering geeft in plaats van stilletjes te blijven
  bestaan.
* De funnel doet acht `count()`-query's over een geïndexeerde `status`-kolom. Bij
  tienduizenden leads is dat nog steeds snel; wordt het ooit een probleem, dan is
  één `GROUP BY` met naberekening de volgende stap.

## Scheduler

`agent:tick` draait elke minuut en pakt maximaal 50 gesprekken en 100
cadansstappen per keer. Daarmee blijft één tick kort, ook als er een achterstand
is opgelopen; de rest komt de minuut erna.

## Vanaf-prijs-check

`GET /api/admin/catalog` doet er één extra offerteberekening bij (in geheugen,
zonder opslag) om te bepalen of de geadverteerde prijs nog haalbaar is. Dat zijn
enkele catalogusquery's op een tabel van 51 rijen; verwaarloosbaar naast de
lijst die het endpoint toch al ophaalt.

## Externe aanroepen

Alle uitgaande HTTP-aanroepen (ElevenLabs, Google, CalDAV) staan op een timeout
van 20 seconden en draaien in de wachtrij, niet in een webrequest. Het
Google-accesstoken wordt 50 minuten gecachet, zodat niet elke afspraak een
tokenrefresh kost.

## Testduur

```
99 tests in ± 6 seconden
```
