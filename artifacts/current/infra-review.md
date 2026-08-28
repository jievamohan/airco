# Infra review

## Docker Compose

Drie diensten erbij naast `web`:

| Dienst | Poort | Rol |
|--------|-------|-----|
| `api` | 8010 → 8000 | Laravel-webserver, migreert en seedt bij het opstarten |
| `agent` | — | queue-worker plus scheduler; de hartslag van de workflow |
| `db` | 3316 → 3306 | MySQL 8.4 met healthcheck; `api` wacht daarop |

`api` en `agent` zijn bewust gescheiden: de agent kan stil worden gezet
(bijvoorbeeld tijdens onderhoud of een test) zonder dat de website geen
aanvragen meer kan wegschrijven. Beide delen het `api_vendor`-volume zodat
`composer install` maar één keer hoeft te draaien.

Lokaal staat `AGENT_DRY_RUN=true`: de workflow loopt volledig, maar er wordt niet
echt gebeld, gemaild of in een agenda geschreven. Dat voorkomt dat een
ontwikkelaar per ongeluk een echte klant belt.

`apps/api/Dockerfile` is `php:8.4-cli-alpine` met de extensies die de app nodig
heeft (`pdo_mysql`, `intl`, `mbstring`, `zip`, `gd`, `bcmath`, `opcache`).
Geen `imap`-extensie nodig dankzij `webklex/php-imap`.

## CI

`.github/workflows/ci-deploy.yml` krijgt een `api`-job vóór de deploy:
Pint, PHPStan, PHPUnit en `composer audit`. De web-job krijgt er
`pnpm audit --prod` bij. `deploy` hangt nu aan beide jobs, zodat een rode API
geen productie-deploy meer doorlaat.

De generate-stap krijgt `NUXT_PUBLIC_API_BASE` uit de repository-variabele
`PUBLIC_API_BASE`. **Actie nodig:** die variabele moet in GitHub gezet worden op
de publieke API-URL, anders wijzen het formulier en het dashboard in productie
naar `localhost`.

## Statische hosting

Het dashboard heeft dynamische routes (`/dashboard/leads/<uuid>`) die als bestand
niet bestaan. `apps/web/public/.htaccess` stuurt alles onder `/dashboard` naar de
SPA-fallback `200.html` en zet `X-Robots-Tag: noindex` op dat pad;
`public/robots.txt` sluit `/dashboard` uit. Draait de site ooit achter nginx in
plaats van Apache, dan is een `try_files $uri /200.html;` op `location /dashboard`
het equivalent.

## Wat nog niet geregeld is

De API wordt nog niet door `scripts/deploy-production.sh` uitgerold; dat script
publiceert alleen de statische site. Het uitrollen van de API (PHP-FPM, MySQL,
de queue-worker en de cron-regel voor `schedule:run`) staat beschreven in
`docs/runbooks/agent-workflow.md` maar is nog een handmatige stap. Dat is
bewust buiten scope gehouden: het vraagt om keuzes over de VPS-inrichting die
met de ondernemer afgestemd moeten worden.
