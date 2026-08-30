# Deploy naar productie (TransIP-VPS, DirectAdmin, geen Docker)

De hele applicatie draait op één domein: **https://airco.sinoxi.nl**.

| | Pad |
|---|---|
| Git-clone (monorepo) | `/home/sinoxi/domains/airco.sinoxi.nl` |
| Laravel (`artisan`, `.env`) | `…/apps/api` |
| Document root | `…/apps/api/public` |

Die laatste regel is de kern van de opzet: de statische Nuxt-build wordt in de
`public/` van Laravel gepubliceerd, naast de front controller. Eén origin dus —
de site op `/`, de tweede landingspagina op `/v2/`, het dashboard op
`/dashboard`, de API op `/api`. Geen CORS, geen tweede domein, één deploy.

Paden staan op één plek: [`scripts/deploy/prod-target.sh`](../../scripts/deploy/prod-target.sh).
Verhuist de boel, dan pas je daar alleen `KLIMAATX_REPO_ROOT` aan.

Lokaal blijft alles in Docker draaien; dit document gaat alleen over de VPS.

## Wat er op de VPS draait

| Onderdeel | Hoe |
|---|---|
| Site + API | Apache (DirectAdmin) → `apps/api/public` |
| Database | MySQL op de VPS zelf |
| Wachtrij | `klimaatx-queue.service` (systemd, gebruikersscope) |
| Hartslag van de agent | `klimaatx-scheduler.timer`, elke minuut `schedule:run` |

De wachtrij en de scheduler samen zijn wat lokaal de `agent`-container doet.
Ze worden bij elke deploy geïnstalleerd of ververst; je hoeft er na de eerste
keer niets meer aan te doen.

## Eenmalig inrichten

### 1. Software op de VPS

```bash
php -v          # 8.2 of nieuwer
composer -V     # of ~/bin/composer -V
node -v         # 22.x
pnpm -v         # 9.15.9
rsync --version
```

#### Composer

Op een DirectAdmin-VPS staat `composer` vaak niet op PATH. De deploy zoekt hem
daarom zelf op, in deze volgorde: PATH, `/usr/local/bin/composer`,
`/usr/bin/composer`, `~/bin/composer`, `~/.local/bin/composer`, en anders een
`composer.phar` in `apps/api`, de repo-root of je home. Vindt hij niets, dan
stopt hij vóór de git-sync met die lijst in beeld.

Staat hij er nog niet, installeer hem dan als de deploy-gebruiker — zonder sudo:

```bash
mkdir -p ~/bin
cd ~
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
# Handtekening van de installer controleren voordat je hem uitvoert.
php -r "if (hash_file('sha384','composer-setup.php') === trim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'installer ok'.PHP_EOL; } else { echo 'ONGELDIG — niet uitvoeren'.PHP_EOL; unlink('composer-setup.php'); }"
php composer-setup.php --install-dir="$HOME/bin" --filename=composer
php -r "unlink('composer-setup.php');"
~/bin/composer -V
```

Zet `~/bin` ook in `~/.bash_profile` (`export PATH="$HOME/bin:$PATH"`) zodat je
hem met de hand kunt aanroepen. Voor de deploy is dat niet nodig — die kent
`~/bin/composer` uit zichzelf.

##### GitHub-token voor composer

Composer haalt de pakketten als zip via de GitHub-API. Eén `composer install`
doet daar meer dan tachtig verzoeken, en zonder token ligt de limiet op 60 per
uur per IP. Deze server heeft dus een geldige token nodig — blijvend, want de
deploy draait `composer install` elke keer.

Gaat er iets mis met die token, dan zie je bij **elk** pakket:

```
Failed to download … from dist: Could not authenticate against github.com
Source fallback is disabled. Not trying alternative sources.
```

Die melding heeft twee oorzaken, en ze zien er hetzelfde uit:

- er staat een token in `auth.json` die GitHub weigert (verlopen of ingetrokken)
- er staat geen token en de anonieme limiet is op

`composer diagnose` zegt welke van de twee het is. Een kale
`curl https://api.github.com/rate_limit` doet dat **niet**: die gaat anoniem en
zegt dus niets over de token die composer gebruikt.

Zetten of vervangen:

1. GitHub → Settings → Developer settings → **Personal access tokens** →
   *Generate new token (classic)*. Dit hoeft **geen enkele scope** te hebben:
   het gaat alleen om het ophalen van publieke pakketten. Geef hem een lange
   houdbaarheid, want de deploy heeft hem blijvend nodig.
2. Op de VPS, als de deploy-gebruiker:

```bash
composer config --global --auth github-oauth.github.com <token>
```

Dat schrijft `~/.config/composer/auth.json` (modus 600). Dat bestand hoort
nooit in de repo — het staat buiten de checkout, en dat moet zo blijven.

Controleren — dit gebruikt wél de token uit `auth.json`:

```bash
composer diagnose 2>&1 | grep -i -A2 github
```

Een oude token eerst weghalen kan met:

```bash
composer config --global --unset github-oauth.github.com
```

Raakt een token buiten de server bekend — in een chat, een ticket, een
screenshot — trek hem dan in bij GitHub en zet een nieuwe. Vervangen is een
minuut werk; uitzoeken wat iemand ermee gedaan heeft niet.

Zolang je geen token hebt kun je ook uitwijken naar `--prefer-source`: dan
cloont composer met git in plaats van via de API. Dat werkt zonder token, maar
is trager, en de deploy gebruikt het niet.

#### Node

Ontbreekt Node, installeer hem als de deploy-gebruiker:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
# nieuwe shell, of: source ~/.nvm/nvm.sh
nvm install 22
nvm alias default 22
corepack enable
corepack prepare pnpm@9.15.9 --activate
```

GitHub Actions opent een **niet-interactieve** SSH-sessie en leest `~/.bashrc`
dan niet. `deploy-on-server.sh` sourcet daarom zelf `~/.nvm/nvm.sh`, en de
workflow draait het commando via `bash -lc`. Werkt dit:

```bash
ssh sinoxi@HOST 'bash -lc "node -v && pnpm -v && php -v"'
```

dan werkt de deploy ook.

### 2. De clone neerzetten

In `/home/sinoxi/domains/airco.sinoxi.nl/` staat de oude prototype-site. Die mag
weg, maar `git clone` weigert in een niet-lege map. Daarom:

```bash
cd /home/sinoxi/domains/airco.sinoxi.nl

# Kijk eerst wat er staat en bewaar wat je wilt houden.
ls -A
tar -czf ~/airco-prototype-$(date +%F).tar.gz .

git init
git remote add origin git@github.com:jievamohan/airco.git
git fetch origin main
git checkout -f -b main origin/main
```

`git checkout -f` overschrijft alleen bestanden die in de repo zitten; oude
prototype-bestanden blijven staan tot je ze zelf weghaalt. Ruim `public_html/`
en de rest van het prototype daarna op — de document root gaat naar
`apps/api/public`, dus `public_html/` doet niets meer.

### 3. Document root omzetten in DirectAdmin

DirectAdmin → **Domain Setup** → `airco.sinoxi.nl` → document root naar:

```
/home/sinoxi/domains/airco.sinoxi.nl/apps/api/public
```

Kan dat in jouw DirectAdmin niet, dan is een symlink het alternatief:

```bash
cd /home/sinoxi/domains/airco.sinoxi.nl
rm -rf public_html
ln -s apps/api/public public_html
```

Apache moet dan wel `FollowSymLinks` toestaan op die map.

Controleer daarna dat `.htaccess` gelezen wordt (`AllowOverride All`) en dat
`mod_rewrite` aanstaat — zonder die twee krijg je op `/api/...` een 404 in
plaats van JSON.

### 4. Database en `.env`

Maak in DirectAdmin een MySQL-database plus gebruiker aan. Dan:

```bash
cd /home/sinoxi/domains/airco.sinoxi.nl/apps/api

# artisan draait op de autoloader van composer, dus die moet er eerst zijn.
# Zonder deze stap eindigt key:generate op een ontbrekende vendor/autoload.php.
# Staat composer niet op PATH: gebruik ~/bin/composer (zie stap 1).
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate
```

Vul in `.env` minstens in: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`OWNER_NOTIFICATION_EMAIL`, `OWNER_INITIAL_PASSWORD`, de `MAIL_*`-gegevens en de
sleutels van de spraakagent. `APP_URL` staat al op `https://airco.sinoxi.nl`.
`.env` staat in `.gitignore` en hoort nooit in git.

Die laatste twee zijn het account waarmee je straks het dashboard in komt — zie
[§ Het dashboardaccount](#het-dashboardaccount) hieronder.

De deploy draait deze `composer install` daarna bij elke keer opnieuw; hier is
hij alleen nodig omdat je `artisan` al vóór de eerste deploy gebruikt.

> `php artisan config:cache` legt `.env` vast in een cachebestand. Wijzig je
> `.env` later met de hand, draai dan `php artisan config:cache` opnieuw —
> anders draait de applicatie nog op de oude waarden.

### 5. Het dashboardaccount

Er is geen registratieformulier: het eerste account komt uit de seeder, die bij
elke deploy meedraait. Twee regels in `.env` bepalen wie dat is:

```bash
OWNER_NOTIFICATION_EMAIL=jij@voorbeeld.nl
OWNER_INITIAL_PASSWORD=een-eigen-wachtwoord-van-minimaal-12-tekens
```

De seeder **weigert** een account aan te maken zolang `OWNER_INITIAL_PASSWORD`
leeg of korter dan twaalf tekens is, en op productie ook bij de
ontwikkelstandaard `wachtwoord-wijzigen`. Dat is niet pietluttig: `.env.example`
levert die regel leeg op, en een leeg wachtwoord verifieert gewoon — zonder die
grens zou een seed een dashboardaccount neerzetten waar iedereen op binnenloopt.
Struikelt de deploy op "basisgegevens seeden mislukt", dan is dit bijna altijd
de reden.

Na de eerste deploy log je in op `https://airco.sinoxi.nl/dashboard/login`,
wijzig je het wachtwoord, en maak je de regel in `.env` weer leeg:

```bash
OWNER_INITIAL_PASSWORD=
php artisan config:cache
```

Bestaat het account eenmaal, dan laat de seeder het met rust — een volgende
deploy zet je gewijzigde wachtwoord dus niet terug.

Een tweede gebruiker maak je er met de hand bij:

```bash
cd /home/sinoxi/domains/airco.sinoxi.nl/apps/api
php artisan tinker
>>> \App\Models\User::create(['name'=>'…','email'=>'…','role'=>'owner','password'=>\Hash::make('…')]);
```

### 6. Eerste deploy

```bash
cd /home/sinoxi/domains/airco.sinoxi.nl
make deploy-on-server
```

Die draait migraties, bouwt de site, publiceert hem en zet de wachtrij en de
scheduler aan. Daarna:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://airco.sinoxi.nl/
curl -sS -o /dev/null -w '%{http_code}\n' https://airco.sinoxi.nl/v2/
curl -sS -o /dev/null -w '%{http_code}\n' https://airco.sinoxi.nl/up
systemctl --user status klimaatx-queue
systemctl --user list-timers klimaatx-scheduler.timer
```

Alle drie de URL's horen `200` te geven. Vul daarna één keer beide formulieren
in en kijk of de leads in het dashboard verschijnen, met de juiste bron.

### 7. Blijft de wachtrij draaien na uitloggen?

De units draaien in de **gebruikersscope** van systemd, dus zonder sudo. Die
stoppen bij uitloggen tenzij "linger" aanstaat. Het installatiescript zet dat
zelf aan; lukt dat niet, dan meldt het dat en doe je het los:

```bash
loginctl enable-linger sinoxi
```

Mag dat niet van de hoster, gebruik dan cron in plaats van de systemd-units:

```cron
* * * * * cd /home/sinoxi/domains/airco.sinoxi.nl/apps/api && php artisan schedule:run >/dev/null 2>&1
* * * * * cd /home/sinoxi/domains/airco.sinoxi.nl/apps/api && php artisan queue:work --stop-when-empty --tries=3 --timeout=120 >/dev/null 2>&1
```

## Automatisch deployen na een merge

Zodra een pull request naar `main` **gemerged** is, draait
[`.github/workflows/deploy-production.yml`](../../.github/workflows/deploy-production.yml)
via SSH `make deploy-on-server` op de VPS.

Een directe push naar `main` deployt bewust **niet**: de gates draaien op de
pull request, en met branch protection kan er niets naar `main` zonder groene
CI. Wat er deployt, is dus altijd code die door CI is gekomen. Handmatig
starten kan via **Actions → Deploy production → Run workflow**.

De VPS wordt op `github.sha` vastgepind (`git fetch` + `git reset --hard`), dus
er draait precies de gemergede commit — niet wat er toevallig op `main` stond
toen de deploy begon.

### Eenmalig: GitHub Environment

Repo → **Settings** → **Environments** → maak `production`.

**Secrets:**

| Naam | Inhoud |
|---|---|
| `VPS_SSH_KEY` | Private key (hele PEM, inclusief `BEGIN`/`END`) van een deploy-only sleutelpaar |
| `VPS_SSH_KEY_PASSPHRASE` | Alleen als die sleutel een passphrase heeft |

**Variables:**

| Naam | Verplicht | Voorbeeld |
|---|---|---|
| `VPS_SSH_HOST` | ja | hostnaam of IP van de VPS |
| `VPS_SSH_USER` | ja | `sinoxi` |
| `VPS_DEPLOY_PATH` | ja | `/home/sinoxi/domains/airco.sinoxi.nl` |
| `VPS_SSH_PORT` | nee | `22` |
| `PRODUCTION_URL` | nee | `https://airco.sinoxi.nl` (zet de rooktest aan) |
| `PUBLIC_API_BASE` | nee | leeg laten; standaard `/api` |

Sleutel maken (private key niet committen):

```bash
ssh-keygen -t ed25519 -f ~/.ssh/airco_deploy_ed25519 -C "github-actions-airco-deploy" -N ""
# publieke helft → ~sinoxi/.ssh/authorized_keys op de VPS
# private helft  → GitHub-secret VPS_SSH_KEY
```

De VPS moet zelf ook bij GitHub kunnen (voor `git fetch`): zet daar een
read-only deploy key voor in de repo.

## Wat de deploy precies doet

Onder een `flock` op `.deploy.lock`, zodat twee deploys elkaar niet in de weg
lopen:

1. Controleren dat php, composer, rsync, node ≥ 22 en pnpm er zijn, en dat
   `apps/api/.env` bestaat — **vóór** de git-sync, zodat een half ingerichte
   server de live site niet aanraakt
2. Momentopname van de live UI → `apps/api/.deploy/ui.pre-pull/`
3. Git-sync: met `KLIMAATX_DEPLOY_SHA` een `fetch` + `reset --hard`, anders
   `git pull --ff-only`
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan migrate --force` en `php artisan db:seed --force`
6. `pnpm install --frozen-lockfile` + `nuxt generate` met `NUXT_PUBLIC_API_BASE=/api`
7. Controle op de build: `index.html`, `_nuxt/`, `v2/index.html`, en of de
   juiste API-basis erin gebakken is
8. Publiceren: `rsync --delete` van de build naar `apps/api/public/`, met de
   bestanden die Laravel zelf meebrengt uitgesloten
9. `storage:link`, `config:cache`, `route:cache`, `view:cache`
10. Wachtrij en scheduler installeren/herstarten
11. Rooktest: `/up`, `/` en `/v2/`

Struikelt stap 4 t/m 8, dan wordt de UI teruggezet uit de momentopname van
stap 2 en stopt de deploy met een foutcode. Migraties worden **niet**
teruggedraaid.

Welke bestanden de publicatie met rust laat, wordt afgeleid uit `git ls-files`
in `apps/api/public` (nu: `index.php`, `.htaccess`, `favicon.ico`) plus
`storage`, `.well-known` en `.deploy`. Zet je daar later iets bij in git, dan
blijft dat vanzelf gespaard.

## Terugdraaien

De echte rollback is de vorige commit opnieuw deployen — de site is volledig
uit git te reproduceren:

```bash
cd /home/sinoxi/domains/airco.sinoxi.nl
KLIMAATX_DEPLOY_SHA=<vorige-sha> make deploy-on-server
```

Alleen de UI terugzetten (na een publicatie die wel slaagde maar niet deugt):

```bash
make rollback-ui
```

Dat zet `_nuxt/` en de HTML terug uit `apps/api/.deploy/ui.prev/`. `media/` en
`v2/` zitten niet in die momentopname — die zijn groot en veranderen zelden;
opnieuw deployen zet ze terug. Databasemigraties draaien nooit vanzelf terug.

## Losse handelingen

```bash
make deploy           # vanaf je laptop: SSH → deploy op de VPS
make deploy-on-server # op de VPS zelf
make deploy-worker    # alleen de wachtrij en de scheduler bijwerken
make rollback-ui      # laatste UI-momentopname terugzetten
```

Logboeken:

```bash
journalctl --user -u klimaatx-queue -f
tail -f apps/api/storage/logs/laravel-$(date +%F).log
```

## Als het misgaat

| Symptoom | Waarschijnlijke oorzaak |
|---|---|
| `/` geeft 403 | Er staat geen `index.html` in de document root: de build is niet gepubliceerd. Draai de deploy opnieuw en lees waar hij afhaakt |
| `/` toont `{"service":"klimaatx-api"}` | De `DirectoryIndex`-regel uit `.htaccess` wordt niet gelezen — `AllowOverride` staat niet op `All` |
| `/api/leads` geeft 404 in plaats van JSON | `mod_rewrite` uit, of `AllowOverride` staat niet op `All` |
| `/dashboard/leads/<uuid>` geeft een JSON-404 | De dashboardregel in `.htaccess` staat na de front controller |
| Formulier meldt "geen verbinding" | De build is gemaakt zonder `NUXT_PUBLIC_API_BASE=/api`; de deploy controleert hierop |
| Leads komen binnen maar er gebeurt niets | `klimaatx-queue` draait niet, of linger staat uit |
| Deploy stopt op "basisgegevens seeden mislukt" | `OWNER_INITIAL_PASSWORD` is leeg of te kort (stap 5) |
| Kan niet inloggen op het dashboard | Het account bestaat nog niet: `php artisan db:seed --force` na het invullen van stap 5 |
| Wijziging in `.env` heeft geen effect | `php artisan config:cache` opnieuw draaien |
| Deploy stopt op "er loopt al een deploy" | Vorige run is hard afgebroken; verwijder `.deploy.lock` |
| `composer: command not found` | Composer staat niet op PATH; installeer hem in `~/bin` (stap 1) — de deploy vindt hem daar zelf |
| `Could not authenticate against github.com` bij elk pakket | De GitHub-token van composer wordt geweigerd, of ontbreekt en de anonieme limiet is op. `composer diagnose` zegt welke (stap 1, § GitHub-token voor composer) |
| `Failed opening required 'vendor/autoload.php'` | `composer install` is nog niet gedraaid in `apps/api` |
