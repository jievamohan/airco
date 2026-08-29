#!/usr/bin/env bash
# Installeert of ververst de achtergrondprocessen van de agent op de VPS.
#
#   klimaatx-queue.service       de wachtrij (queue:work)
#   klimaatx-scheduler.timer     de hartslag (schedule:run, elke minuut)
#
# Draait standaard in de gebruikersscope van systemd, dus zonder sudo.
# Idempotent: veilig bij elke deploy — de units worden herschreven en herstart.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# "user" (standaard, geen sudo) of "system" (vereist root/sudo)
SCOPE="${KLIMAATX_SYSTEMD_SCOPE:-user}"

LARAVEL_ROOT="${1:-${LARAVEL_ROOT:-}}"
if [[ -z "$LARAVEL_ROOT" ]]; then
  if [[ -f "$PWD/artisan" ]]; then
    LARAVEL_ROOT="$PWD"
  elif [[ -f "$REPO_ROOT/apps/api/artisan" ]]; then
    LARAVEL_ROOT="$REPO_ROOT/apps/api"
  else
    echo "queue-worker: geen Laravel-map gevonden (geef het pad mee)" >&2
    exit 1
  fi
fi
LARAVEL_ROOT="$(cd "$LARAVEL_ROOT" && pwd)"

if [[ ! -f "$LARAVEL_ROOT/artisan" ]]; then
  echo "queue-worker: artisan niet gevonden in $LARAVEL_ROOT" >&2
  exit 1
fi

if ! command -v systemctl >/dev/null 2>&1; then
  echo "queue-worker: systemctl ontbreekt — zet de wachtrij en de scheduler" >&2
  echo "              handmatig in cron; zie docs/runbooks/deploy-production.md." >&2
  exit 1
fi

PHP_BIN="$(command -v php)"

systemctl_cmd() {
  if [[ "$SCOPE" == "user" ]]; then
    systemctl --user "$@"
  elif [[ "$(id -u)" -eq 0 ]]; then
    systemctl "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo systemctl "$@"
  else
    echo "queue-worker: sudo nodig voor systeem-scope" >&2
    exit 1
  fi
}

unit_dir() {
  if [[ "$SCOPE" == "user" ]]; then
    printf '%s\n' "${XDG_CONFIG_HOME:-$HOME/.config}/systemd/user"
  else
    printf '%s\n' /etc/systemd/system
  fi
}

write_unit() {
  local name="$1" content="$2" dir path
  dir="$(unit_dir)"
  path="$dir/$name"

  if [[ "$SCOPE" == "user" ]]; then
    mkdir -p "$dir"
    printf '%s\n' "$content" >"$path"
  elif [[ "$(id -u)" -eq 0 ]]; then
    printf '%s\n' "$content" >"$path"
  else
    printf '%s\n' "$content" | sudo tee "$path" >/dev/null
  fi
  echo "queue-worker: $path geschreven" >&2
}

wanted_by() {
  [[ "$SCOPE" == "user" ]] && printf 'default.target\n' || printf 'multi-user.target\n'
}

service_user_line() {
  # In systeem-scope moet het proces onder de eigenaar van de code draaien,
  # anders kan het niet in storage/ schrijven.
  if [[ "$SCOPE" == "user" ]]; then
    printf ''
  else
    printf 'User=%s\n' "${KLIMAATX_SERVICE_USER:-$(stat -c '%U' "$LARAVEL_ROOT" 2>/dev/null || stat -f '%Su' "$LARAVEL_ROOT")}"
  fi
}

# --max-time laat de worker elk uur netjes afsluiten; systemd start hem daarna
# meteen opnieuw. Zo raakt een langlopend proces geen geheugen kwijt en pakt
# het na een deploy vanzelf de nieuwe code op.
write_unit "klimaatx-queue.service" "$(cat <<EOF
[Unit]
Description=KlimaatX Laravel queue worker
After=network.target

[Service]
$(service_user_line)WorkingDirectory=${LARAVEL_ROOT}
ExecStart=${PHP_BIN} ${LARAVEL_ROOT}/artisan queue:work --tries=3 --timeout=120 --sleep=3 --max-time=3600
Restart=always
RestartSec=5
KillMode=mixed

[Install]
WantedBy=$(wanted_by)
EOF
)"

write_unit "klimaatx-scheduler.service" "$(cat <<EOF
[Unit]
Description=KlimaatX Laravel scheduler (eenmalige tik)

[Service]
Type=oneshot
$(service_user_line)WorkingDirectory=${LARAVEL_ROOT}
ExecStart=${PHP_BIN} ${LARAVEL_ROOT}/artisan schedule:run
EOF
)"

write_unit "klimaatx-scheduler.timer" "$(cat <<EOF
[Unit]
Description=KlimaatX scheduler elke minuut

[Timer]
OnCalendar=*:0/1
AccuracySec=1s
Persistent=true
Unit=klimaatx-scheduler.service

[Install]
WantedBy=timers.target
EOF
)"

systemctl_cmd daemon-reload
systemctl_cmd enable --now klimaatx-queue.service
systemctl_cmd enable --now klimaatx-scheduler.timer

# Na een deploy draait de worker nog op de oude code. De systemd-herstart
# hieronder is wat hem de nieuwe versie laat oppakken; queue:restart vraagt hem
# daarnaast netjes te stoppen na de job waar hij mee bezig is. Dat laatste
# schrijft naar de cache en kan dus falen als de database net hapert — geen
# reden om een verder geslaagde deploy te laten klappen.
(cd "$LARAVEL_ROOT" && php artisan queue:restart >/dev/null 2>&1) \
  || echo "queue-worker: let op — queue:restart mislukte; systemd herstart de worker alsnog" >&2
systemctl_cmd restart klimaatx-queue.service

if [[ "$SCOPE" == "user" ]] && command -v loginctl >/dev/null 2>&1; then
  if ! loginctl show-user "$(whoami)" -p Linger --value 2>/dev/null | grep -qi yes; then
    echo "queue-worker: linger aanzetten (processen overleven het uitloggen)" >&2
    loginctl enable-linger "$(whoami)" 2>/dev/null \
      || echo "queue-worker: LET OP — linger aanzetten lukte niet. Zonder linger stopt de wachtrij bij uitloggen; vraag de hoster of draai: loginctl enable-linger $(whoami)" >&2
  fi
fi

if ! systemctl_cmd is-active --quiet klimaatx-queue.service; then
  echo "queue-worker: klimaatx-queue draait niet na het starten" >&2
  systemctl_cmd status klimaatx-queue.service >&2 || true
  exit 1
fi

echo "queue-worker: klimaatx-queue en klimaatx-scheduler actief (scope=$SCOPE, $LARAVEL_ROOT)"
