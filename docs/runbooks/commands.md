# Canonical commands (KlimaatX / airco)

## Stack

- Web: Nuxt 3 onder `apps/web` (pnpm) — landingspagina en het CRM-dashboard onder `/dashboard`
- API: Laravel 12 onder `apps/api` (composer, MySQL) — CRM, agent-workflow en integraties

## Docker

```bash
docker compose up -d --build
docker compose exec web pnpm run typecheck
docker compose exec web pnpm run build
docker compose logs -f web

docker compose exec api php artisan migrate --force
docker compose exec api php artisan db:seed --force
docker compose exec api composer test
docker compose exec api composer analyse
docker compose exec api vendor/bin/pint --test
```

### Databases

Er draaien er twee, met opzet:

| Service | Poort | Waarvoor |
|---|---|---|
| `db` | 3316 | je ontwikkeldata; blijft staan tussen sessies |
| `db-test` | 3317 | de testsuite; `RefreshDatabase` gooit het schema elke run weg |

De tests draaien op MySQL en niet op sqlite, want productie is MySQL en de
verschillen zitten juist in het schema: sqlite accepteert kolomtypes en defaults
die MySQL weigert. `db-test` staat bovendien op
`explicit_defaults_for_timestamp=OFF`, de stand van de VPS — anders blijft een
migratie die daar omvalt hier onzichtbaar. De data van `db-test` staat in tmpfs
en hoeft niets te overleven.

De eerste `up` duurt een paar minuten: `composer install` en `pnpm install`
draaien dan voor het eerst. De `agent`-container wacht bewust tot de `api`
gezond is — die deelt zijn `vendor`-map en zou anders omvallen op een
ontbrekende `autoload.php`. Volgen kan met:

```bash
docker compose ps          # toont de healthstatus van api
docker compose logs -f api
```

De api-container draait **Apache** (`php:8.4-apache`), niet `php artisan serve`:
die laatste is PHP's ingebouwde ontwikkelserver, verwerkt verzoeken serieel en
gaat anders om met omgevingsvariabelen dan een echte webserver.

Alle applicatie-instellingen staan in **`apps/api/.env.docker`** — dat is de
enige bron van waarheid voor deze containers. Het startscript kopieert dat naar
`.env` bij de eerste start en genereert eenmalig een applicatiesleutel. Wil je
iets lokaal anders, pas dan `.env` aan (of `.env.docker` voor iedereen); zet het
niet in de `environment:`-sectie van compose, want twee bronnen voor dezelfde
sleutel geven verschil tussen omgevingen.

Open: http://localhost:3010 (web) en http://localhost:8010 (api)

Er staan twee landingspagina's naast elkaar, zodat we de klant een keuze kunnen
laten zien:

| URL | Wat |
| --- | --- |
| http://localhost:3010 | Versie 1 — de Nuxt-pagina met scroll-animaties (`apps/web/pages/index.vue`) |
| http://localhost:3010/v2 | Versie 2 — de statische Solarlex-pagina (`apps/web/public/v2/`) |

Beide formulieren posten naar `POST /api/leads`. Aan `source` is in het
dashboard te zien welke pagina de lead opleverde: `web_form` voor versie 1,
`web_form_v2` voor versie 2. Versie 2 staat op `noindex` en in `robots.txt`,
want twee vindbare varianten van dezelfde site is dubbele content.

Het CRM-dashboard zit op **http://localhost:3010/dashboard**. Gebruik dezelfde
hostnaam als in `DASHBOARD_ORIGINS` staat: opent u het op een adres dat daar niet
in voorkomt, dan blokkeert CORS de aanroepen en meldt het inlogscherm dat de API
niet bereikbaar is. Inloggen kan met
het account dat `DatabaseSeeder` aanmaakt: het adres uit
`OWNER_NOTIFICATION_EMAIL` en het wachtwoord uit `OWNER_INITIAL_PASSWORD`
(standaard `wachtwoord-wijzigen` — wijzig dat direct).

Demodata om het dashboard gevuld te zien:

```bash
docker compose exec api php artisan db:seed --class='Database\\Seeders\\DemoSeeder'
```

Die seeder maakt acht leads verspreid over de funnel, met gesprekken,
transcripten, offertes en afspraken. Alleen voor lokaal en demo, nooit op
productie.

## Agent-workflow

De workflow draait op twee processen: de scheduler (hartslag) en een queue-worker.

```bash
# Hartslag: gesprekken doorzetten en opvolgstappen uitvoeren (elke minuut)
docker compose exec api php artisan schedule:work

# Wachtrij: verrijken, offertes mailen, afspraken boeken
docker compose exec api php artisan queue:work --tries=3

# Handmatig een stap forceren
docker compose exec api php artisan leads:poll-mailbox
docker compose exec api php artisan agent:tick
```

In productie draaien deze als systemd-units of via cron; zie
[agent-workflow.md](./agent-workflow.md).

## Production deploy (VPS / DirectAdmin)

Productie draait **zonder Docker** op de TransIP-VPS, alles op één domein:
https://airco.sinoxi.nl. De document root wijst naar `apps/api/public`, waar de
statische Nuxt-build naast de front controller van Laravel staat.

```bash
make deploy             # laptop → SSH → deploy op de VPS
make deploy-on-server   # op de VPS zelf
make deploy-worker      # alleen de wachtrij en de scheduler bijwerken
make rollback-ui        # laatste UI-momentopname terugzetten
```

**Auto-deploy:** een **gemergede** PR naar `main` start
`.github/workflows/deploy-production.yml` (SSH → `make deploy-on-server` →
rooktest). Een directe push naar `main` deployt niet; de gates draaien op de PR
(`.github/workflows/ci.yml`).

Volledige inrichting, secrets en het terugdraaien: [deploy-production.md](./deploy-production.md).

## Wijziging komt niet door in de browser

De dev-servers draaien in containers met een bind-mount naar je werkmap. Een
`git pull` op de host levert daarbinnen geen inotify-event op, dus Vite ziet de
wijziging niet vanzelf. Daarom staat `VITE_USE_POLLING=true` op de web-service.

Zie je een wijziging toch niet, controleer dan eerst wat de container heeft:

```bash
docker compose exec web grep -c "login__remember" pages/dashboard/login.vue
```

* `0` — de container heeft het bestand niet; je `git pull` is niet gelukt of je
  staat op een andere branch.
* `1` of meer — het bestand is er wel. Herstart dan de web-container en doe een
  harde ververs in de browser (cmd+shift+R):

```bash
docker compose restart web
```

## Secret scan

```bash
gitleaks detect --source . --config .gitleaks.toml --redact
```

Draait ook in CI vóór de deploy. Naast de standaardregels zit er een eigen regel
in `.gitleaks.toml` die aanslaat op een env-bestand in de repository met een
ingevulde wachtwoord-, sleutel- of tokenregel. Voorbeeldbestanden mogen die
sleutels tonen, maar altijd leeg.

Geheimen horen nooit in de repository. Lokale instellingen zet je in
`apps/api/.env` (gitignored); wat elke ontwikkelaar nodig heeft, staat leeg in
`apps/api/.env.docker`.

## Notes

- Locally: do not run `pnpm` on the host; use `docker compose exec web …`.
- On the VPS: `make deploy-on-server` uses host Node 22.14+ + pnpm 9.15.9 (documented exception).
- Playwright / e2e service: deferred (Lane I follow-up). Never run Playwright on the host.
- `NUXT_PUBLIC_API_BASE` moet bij het bouwen op de publieke API-basis staan, anders
  wijzen de formulieren en het dashboard naar localhost. Op productie is dat `/api`
  (zelfde domein); de deploy zet die waarde zelf en controleert hem in de build.
