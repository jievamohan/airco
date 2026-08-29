#!/usr/bin/env bash
# Productiedeploy op de VPS: git sync, composer, migraties, Nuxt-build,
# publiceren, caches, queue worker, gezondheidscheck. Geen Docker.
#
#   make deploy-on-server                              handmatig (git pull)
#   KLIMAATX_DEPLOY_SHA=<sha> make deploy-on-server    vastgepinde commit (CI)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/prod-target.sh"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/publish-nuxt-public.sh"

# De checkout waar dit script zelf in staat is de waarheid. Zou hij ergens
# anders staan dan prod-target.sh zegt, dan deployt hij zichzelf — niet een
# tweede clone die iemand vergeten is bij te werken.
SELF_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
if [[ "$SELF_ROOT" != "$KLIMAATX_REPO_ROOT" ]]; then
  echo "deploy: let op — dit script staat in $SELF_ROOT, prod-target.sh noemt" >&2
  echo "        $KLIMAATX_REPO_ROOT. Ik werk op $SELF_ROOT." >&2
fi

REPO_ROOT="$SELF_ROOT"
LARAVEL_ROOT="$REPO_ROOT/apps/api"
PUBLIC_DIR="$LARAVEL_ROOT/public"
WEB_DIR="$REPO_ROOT/apps/web"
OUT_PUBLIC="$WEB_DIR/.output/public"

LOCK_FILE="${KLIMAATX_DEPLOY_LOCK:-$REPO_ROOT/.deploy.lock}"
UI_PRE_PULL="$LARAVEL_ROOT/.deploy/ui.pre-pull"
UI_PREV="$LARAVEL_ROOT/.deploy/ui.prev"
SITE_URL="${KLIMAATX_DEPLOY_SITE_URL:-$KLIMAATX_SITE_URL}"
HEALTH_URL="${KLIMAATX_DEPLOY_HEALTH_URL:-${SITE_URL%/}/up}"

# De site en de API delen één origin, dus de browser praat met /api op hetzelfde
# domein. Dit is de waarde die in de statische build gebakken wordt.
API_BASE="${NUXT_PUBLIC_API_BASE:-/api}"

if [[ ! -d "$REPO_ROOT/.git" ]]; then
  echo "deploy: geen .git in $REPO_ROOT" >&2
  exit 1
fi

if [[ ! -f "$LARAVEL_ROOT/artisan" ]]; then
  echo "deploy: artisan niet gevonden in $LARAVEL_ROOT" >&2
  exit 1
fi

restore_pre_pull_ui() {
  echo "deploy: UI terugzetten uit de momentopname van vóór de pull" >&2
  klimaatx_restore_ui "$PUBLIC_DIR" "$UI_PRE_PULL" \
    || echo "deploy: LET OP — terugzetten mislukt of momentopname leeg" >&2
}

# Een niet-interactieve SSH-sessie (GitHub Actions) leest ~/.bashrc niet, dus
# nvm staat er dan niet. Zelf laden, anders faalt de build op een ontbrekende
# node terwijl hij handmatig wel werkt.
ensure_node_pnpm_on_path() {
  if command -v node >/dev/null 2>&1 && command -v pnpm >/dev/null 2>&1; then
    return 0
  fi
  export PATH="/usr/local/bin:/usr/bin:${PATH:-}"
  local nvm_dir="${NVM_DIR:-$HOME/.nvm}"
  if [[ -s "$nvm_dir/nvm.sh" ]]; then
    # shellcheck disable=SC1090,SC1091
    . "$nvm_dir/nvm.sh"
    if [[ -f "$REPO_ROOT/.nvmrc" ]]; then
      nvm use --silent >/dev/null 2>&1 || true
    fi
  fi
  if command -v node >/dev/null 2>&1 && ! command -v pnpm >/dev/null 2>&1; then
    corepack enable >/dev/null 2>&1 || true
    corepack prepare "pnpm@$(pnpm_version_from_package)" --activate >/dev/null 2>&1 || true
  fi
  command -v node >/dev/null 2>&1 && command -v pnpm >/dev/null 2>&1
}

pnpm_version_from_package() {
  local pm
  pm="$(grep -o '"packageManager"[[:space:]]*:[[:space:]]*"pnpm@[0-9.]*"' "$WEB_DIR/package.json" \
    | sed -E 's/.*pnpm@([0-9.]+)".*/\1/' || true)"
  printf '%s\n' "${pm:-9.15.9}"
}

# Composer staat op een DirectAdmin-VPS lang niet altijd op PATH, en in een
# niet-interactieve SSH-sessie nog minder vaak. Daarom zelf zoeken: eerst PATH,
# dan de gebruikelijke plekken, dan een composer.phar in de repo.
COMPOSER_CMD=()

resolve_composer() {
  local cand

  if command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD=(composer)
    return 0
  fi

  for cand in /usr/local/bin/composer /usr/bin/composer \
              "$HOME/bin/composer" "$HOME/.local/bin/composer"; do
    if [[ -x "$cand" ]]; then
      COMPOSER_CMD=("$cand")
      return 0
    fi
  done

  for cand in "$LARAVEL_ROOT/composer.phar" "$REPO_ROOT/composer.phar" \
              "$HOME/composer.phar"; do
    if [[ -f "$cand" ]]; then
      COMPOSER_CMD=(php "$cand")
      return 0
    fi
  done

  return 1
}

require_toolchain() {
  local php_major_minor node_major

  for tool in php rsync git curl; do
    if ! command -v "$tool" >/dev/null 2>&1; then
      echo "deploy: $tool staat niet op PATH (zie docs/runbooks/deploy-production.md)" >&2
      exit 1
    fi
  done

  if ! resolve_composer; then
    echo "deploy: composer niet gevonden — niet op PATH, niet op de gebruikelijke" >&2
    echo "        plekken, en geen composer.phar in de repo of je home." >&2
    echo "        Zie docs/runbooks/deploy-production.md § Composer." >&2
    exit 1
  fi

  php_major_minor="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
  case "$php_major_minor" in
    8.2|8.3|8.4|8.5) ;;
    *)
      echo "deploy: PHP 8.2+ vereist (gevonden $php_major_minor)" >&2
      exit 1
      ;;
  esac

  # Vóór de git sync controleren: een mislukte toolchain ná de pull laat de
  # site achter met code die nog niet gebouwd is.
  if ! ensure_node_pnpm_on_path; then
    echo "deploy: node/pnpm niet gevonden (systeem noch nvm)." >&2
    echo "        Installeer Node 22 + corepack pnpm; zie docs/runbooks/deploy-production.md." >&2
    exit 1
  fi

  node_major="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
  if [[ "$node_major" -lt 22 ]]; then
    echo "deploy: Node 22 of nieuwer vereist (gevonden $(node -v))" >&2
    exit 1
  fi

  echo "deploy: php $(php -r 'echo PHP_VERSION;'), composer ${COMPOSER_CMD[*]}, node $(node -v), pnpm $(pnpm -v)"
}

require_env() {
  if [[ ! -f "$LARAVEL_ROOT/.env" ]]; then
    echo "deploy: $LARAVEL_ROOT/.env ontbreekt." >&2
    echo "        Maak hem eenmalig aan uit .env.example; zie de runbook." >&2
    exit 1
  fi

  # Zonder applicatiesleutel komt de deploy nog een heel eind en valt de site
  # daarna om op elke sessie en elk versleuteld veld. Hier stoppen is duidelijker
  # dan een 500 na afloop.
  if ! grep -q '^APP_KEY=base64:' "$LARAVEL_ROOT/.env"; then
    echo "deploy: APP_KEY ontbreekt in $LARAVEL_ROOT/.env." >&2
    echo "        Draai eenmalig: cd $LARAVEL_ROOT && php artisan key:generate" >&2
    exit 1
  fi
}

# Werkboom naar een vastgepinde commit (CI) of gewoon bijwerken (handmatig).
sync_repo_to_deploy_ref() {
  local sha="${KLIMAATX_DEPLOY_SHA:-}"
  local remote="${KLIMAATX_DEPLOY_REMOTE:-origin}"

  if [[ -z "$sha" ]]; then
    echo "deploy: git pull --ff-only (geen KLIMAATX_DEPLOY_SHA)" >&2
    git -C "$REPO_ROOT" pull --ff-only
    return 0
  fi

  if [[ ! "$sha" =~ ^[0-9a-fA-F]{7,40}$ ]]; then
    echo "deploy: ongeldige KLIMAATX_DEPLOY_SHA=$sha" >&2
    return 1
  fi

  if ! git -C "$REPO_ROOT" remote get-url "$remote" >/dev/null 2>&1; then
    echo "deploy: onbekende remote '$remote' op de VPS" >&2
    return 1
  fi

  echo "deploy: git fetch $remote (vastgepinde commit)" >&2
  git -C "$REPO_ROOT" fetch "$remote"

  if ! git -C "$REPO_ROOT" cat-file -e "${sha}^{commit}" 2>/dev/null; then
    echo "deploy: commit $sha los ophalen bij $remote" >&2
    git -C "$REPO_ROOT" fetch "$remote" "$sha"
  fi

  echo "deploy: git reset --hard $sha" >&2
  git -C "$REPO_ROOT" reset --hard "$sha"
}

install_php_dependencies() {
  echo "deploy: composer install (zonder dev)" >&2
  (
    cd "$LARAVEL_ROOT"
    "${COMPOSER_CMD[@]}" install --no-interaction --no-progress --prefer-dist \
      --no-dev --optimize-autoloader
  )
}

generate_site() {
  echo "deploy: pnpm install + nuxt generate (NUXT_PUBLIC_API_BASE=$API_BASE)" >&2
  (
    cd "$WEB_DIR"
    export NUXT_PUBLIC_API_BASE="$API_BASE"
    pnpm install --frozen-lockfile
    pnpm run generate
  )
}

require_generate_artifact() {
  if [[ ! -f "$OUT_PUBLIC/index.html" ]] || [[ ! -d "$OUT_PUBLIC/_nuxt" ]]; then
    echo "deploy: build onvolledig — geen index.html of _nuxt/ in $OUT_PUBLIC" >&2
    return 1
  fi
  if [[ ! -f "$OUT_PUBLIC/v2/index.html" ]]; then
    echo "deploy: build onvolledig — de tweede landingspagina ontbreekt" >&2
    return 1
  fi
  # De API-basis wordt tijdens generate in de bundel gebakken. Staat daar de
  # ontwikkelwaarde in, dan wijzen de formulieren op productie naar localhost
  # en verdwijnen alle leads — zichtbaar pas als een klant belt.
  if ! grep -q "apiBase:\"$API_BASE\"" "$OUT_PUBLIC/index.html"; then
    echo "deploy: index.html bevat niet apiBase:\"$API_BASE\" — verkeerde build" >&2
    return 1
  fi
}

cache_laravel() {
  echo "deploy: artisan caches opbouwen" >&2
  (
    cd "$LARAVEL_ROOT"
    php artisan storage:link --quiet || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
  )
}

health_check() {
  local code
  echo "deploy: gezondheidscheck $HEALTH_URL" >&2
  if ! curl -fsS --max-time 30 -o /dev/null "$HEALTH_URL"; then
    echo "deploy: $HEALTH_URL antwoordt niet" >&2
    return 1
  fi

  echo "deploy: gezondheidscheck ${SITE_URL%/}/" >&2
  code="$(curl -fsS --max-time 30 -o /dev/null -w '%{http_code}' "${SITE_URL%/}/" || echo 000)"
  if [[ "$code" != "200" ]]; then
    echo "deploy: ${SITE_URL%/}/ gaf $code in plaats van 200" >&2
    return 1
  fi

  code="$(curl -fsS --max-time 30 -o /dev/null -w '%{http_code}' "${SITE_URL%/}/v2/" || echo 000)"
  if [[ "$code" != "200" ]]; then
    echo "deploy: ${SITE_URL%/}/v2/ gaf $code in plaats van 200" >&2
    return 1
  fi

  echo "deploy: / en /v2/ geven 200, /up antwoordt"
}

run_deploy() {
  echo "deploy: repo=$REPO_ROOT"
  echo "deploy: laravel=$LARAVEL_ROOT"
  echo "deploy: docroot=$PUBLIC_DIR"
  echo "deploy: HEAD=$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo onbekend)"
  if [[ -n "${KLIMAATX_DEPLOY_SHA:-}" ]]; then
    echo "deploy: doel=$KLIMAATX_DEPLOY_SHA via ${KLIMAATX_DEPLOY_REMOTE:-origin}"
  fi

  require_toolchain
  require_env

  echo "deploy: momentopname van de live UI → $UI_PRE_PULL" >&2
  klimaatx_snapshot_ui "$PUBLIC_DIR" "$UI_PRE_PULL"

  if ! sync_repo_to_deploy_ref; then
    echo "deploy: git sync mislukt — live site ongewijzigd" >&2
    exit 1
  fi
  echo "deploy: HEAD na sync=$(git -C "$REPO_ROOT" rev-parse --short HEAD)"

  # Gecachete config van de vorige versie kan naar sleutels wijzen die deze
  # versie niet meer kent; leegmaken vóór migreren.
  (cd "$LARAVEL_ROOT" && php artisan config:clear >/dev/null)

  if ! install_php_dependencies; then
    echo "deploy: composer install mislukt" >&2
    echo "        Staat er 'Could not authenticate against github.com' boven, dan" >&2
    echo "        is de anonieme GitHub-API-limiet op en mist deze server een" >&2
    echo "        token. Zie docs/runbooks/deploy-production.md § GitHub-token." >&2
    restore_pre_pull_ui
    exit 1
  fi

  if ! (cd "$LARAVEL_ROOT" && php artisan migrate --force); then
    echo "deploy: migraties mislukt" >&2
    restore_pre_pull_ui
    exit 1
  fi

  if ! generate_site || ! require_generate_artifact; then
    echo "deploy: Nuxt-build mislukt" >&2
    restore_pre_pull_ui
    exit 1
  fi

  if ! klimaatx_publish_ui "$OUT_PUBLIC" "$PUBLIC_DIR" "$UI_PREV"; then
    echo "deploy: publiceren mislukt" >&2
    restore_pre_pull_ui
    exit 1
  fi

  cache_laravel

  echo "deploy: queue worker installeren/verversen" >&2
  bash "$REPO_ROOT/scripts/deploy/install-queue-worker.sh" "$LARAVEL_ROOT"

  if ! health_check; then
    echo "deploy: gezondheidscheck mislukt — controleer de site meteen" >&2
    exit 1
  fi

  echo "deploy: klaar op $(git -C "$REPO_ROOT" rev-parse --short HEAD)"
}

echo "deploy: slot pakken op $LOCK_FILE" >&2
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "deploy: er loopt al een deploy ($LOCK_FILE) — gestopt" >&2
  exit 1
fi

run_deploy
