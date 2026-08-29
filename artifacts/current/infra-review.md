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

Dat gedeelde volume vraagt wel om volgorde. De agent hing eerst aan
`condition: service_started`, en dat wacht alleen tot de api-container draait,
niet tot `composer install` klaar is — de agent viel dan meteen om op een
ontbrekende `vendor/autoload.php`. De api heeft nu een healthcheck op `/up`,
die pas slaagt als dependencies, migraties en seed klaar zijn en de webserver
antwoordt; de agent hangt aan `condition: service_healthy`.

Beide containers starten via `apps/api/docker/start.sh`, dat ook `.env`
aanmaakt en eenmalig een applicatiesleutel genereert. Zonder dat viel een verse
clone om op een ontbrekende `APP_KEY`, want `.env` staat terecht in
`.gitignore`. Het script wordt via de bind-mount ingelezen, dus een wijziging
vraagt geen rebuild van de image.

De api-container draait **Apache** in plaats van `php artisan serve`. Die
laatste is PHP's ingebouwde ontwikkelserver: hij verwerkt verzoeken serieel,
lijkt in niets op productie en gaat aantoonbaar anders om met
omgevingsvariabelen — een instelling uit de `environment:`-sectie van compose
kwam in de ene omgeving wel en in de andere niet aan, waardoor CORS het live
domein terugstuurde terwijl compose iets anders opgaf. De documentroot staat op
`public/`, zodat de rest van de applicatie niet via de webserver bereikbaar is.

Daarmee samenhangend is de configuratie teruggebracht tot **één** bron:
`apps/api/.env.docker`, dat het startscript naar `.env` kopieert. De
`environment:`-secties van de api- en agent-service zijn verwijderd. Een `.env`
die nog een ongewijzigde kopie van `.env.example` is (aangemaakt door een
eerdere versie van dit script, met productiewaarden erin) wordt bij het starten
vervangen.

Lokaal staat `AGENT_DRY_RUN=true`: de workflow loopt volledig, maar er wordt niet
echt gebeld, gemaild of in een agenda geschreven. Dat voorkomt dat een
ontwikkelaar per ongeluk een echte klant belt.

`apps/api/Dockerfile` is `php:8.4-cli-alpine` met de extensies die de app nodig
heeft (`pdo_mysql`, `intl`, `mbstring`, `zip`, `gd`, `bcmath`, `opcache`).
Geen `imap`-extensie nodig dankzij `webklex/php-imap`.

Verder staat `DASHBOARD_ORIGINS` lokaal op zowel `http://localhost:3010` als
`http://127.0.0.1:3010`. Met alleen de eerste blokkeerde CORS elke aanroep zodra
je het dashboard op `127.0.0.1` opende, en toonde het inlogscherm het kale
"Failed to fetch" van de browser. De API-client vertaalt zo'n netwerk- of
CORS-fout nu naar een melding die het API-adres en de eigen origin noemt.

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
