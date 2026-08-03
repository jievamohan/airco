#!/usr/bin/env bash
# On-VPS static deploy for KlimaatX (TransIP / DirectAdmin).
# Usage (from repo root):
#   ./scripts/deploy-production.sh           # pull, generate, publish
#   ./scripts/deploy-production.sh --dry-run # same, but rsync --dry-run
#   ./scripts/deploy-production.sh --rollback
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

WEB_DIR="${ROOT_DIR}/apps/web"
OUT_PUBLIC="${WEB_DIR}/.output/public"
RELEASES_DIR="${ROOT_DIR}/releases"
KEEP_RELEASES=3

MODE="deploy"
if [[ "${1:-}" == "--dry-run" ]]; then
  MODE="dry-run"
elif [[ "${1:-}" == "--rollback" ]]; then
  MODE="rollback"
elif [[ -n "${1:-}" ]]; then
  echo "error: unknown argument '${1}' (use --dry-run or --rollback)" >&2
  exit 2
fi

load_env_deploy() {
  if [[ -f "${ROOT_DIR}/.env.deploy" ]]; then
    # shellcheck disable=SC1091
    set -a
    source "${ROOT_DIR}/.env.deploy"
    set +a
  fi
}

require_public_html() {
  if [[ -z "${PUBLIC_HTML:-}" ]]; then
    cat >&2 <<'EOF'
error: PUBLIC_HTML is not set.

Create .env.deploy from .env.deploy.example and set:
  PUBLIC_HTML=/home/USER/domains/DOMAIN/public_html

Live site was not changed.
EOF
    exit 1
  fi

  if [[ "${PUBLIC_HTML}" != /* ]]; then
    echo "error: PUBLIC_HTML must be an absolute path (got: ${PUBLIC_HTML})" >&2
    echo "Live site was not changed." >&2
    exit 1
  fi

  case "${PUBLIC_HTML}" in
    */public_html|*/public_html/) ;;
    *)
      echo "error: PUBLIC_HTML must end with 'public_html' (got: ${PUBLIC_HTML})" >&2
      echo "Live site was not changed." >&2
      exit 1
      ;;
  esac

  # Normalize trailing slash for rsync destination
  PUBLIC_HTML="${PUBLIC_HTML%/}"

  if [[ ! -d "${PUBLIC_HTML}" ]]; then
    echo "error: PUBLIC_HTML does not exist: ${PUBLIC_HTML}" >&2
    echo "Create the DirectAdmin docroot first. Live site was not changed." >&2
    exit 1
  fi
}

require_generate_artifact() {
  if [[ ! -f "${OUT_PUBLIC}/index.html" ]]; then
    echo "error: missing ${OUT_PUBLIC}/index.html after generate" >&2
    echo "Live site was not changed." >&2
    exit 1
  fi
  if [[ ! -d "${OUT_PUBLIC}/_nuxt" ]]; then
    echo "error: missing ${OUT_PUBLIC}/_nuxt/ after generate" >&2
    echo "Live site was not changed." >&2
    exit 1
  fi
}

snapshot_public_html() {
  mkdir -p "${RELEASES_DIR}"
  local stamp
  stamp="$(date +%Y%m%d-%H%M%S)"
  local archive="${RELEASES_DIR}/${stamp}.tar.gz"
  echo "→ snapshot ${PUBLIC_HTML} → ${archive}"
  tar -czf "${archive}" -C "${PUBLIC_HTML}" .
  # Keep only the newest KEEP_RELEASES archives
  local extras
  extras="$(ls -1t "${RELEASES_DIR}"/*.tar.gz 2>/dev/null | tail -n +"$((KEEP_RELEASES + 1))" || true)"
  if [[ -n "${extras}" ]]; then
    # shellcheck disable=SC2086
    rm -f ${extras}
  fi
}

rsync_publish() {
  local dry_flag=()
  if [[ "${MODE}" == "dry-run" ]]; then
    dry_flag=(--dry-run -v)
    echo "→ rsync dry-run (no live changes)"
  else
    echo "→ rsync publish → ${PUBLIC_HTML}/"
  fi

  rsync -a --delete \
    "${dry_flag[@]}" \
    --exclude='.well-known' \
    --exclude='.htaccess' \
    "${OUT_PUBLIC}/" \
    "${PUBLIC_HTML}/"
}

pull_main() {
  echo "→ git fetch + pull origin main"
  git fetch origin main
  git checkout main
  git pull --ff-only origin main
  echo "→ HEAD $(git rev-parse --short HEAD) ($(git rev-parse HEAD))"
}

generate_site() {
  echo "→ pnpm install --frozen-lockfile + generate (apps/web)"
  if ! command -v pnpm >/dev/null 2>&1; then
    echo "error: pnpm not found. Enable corepack (Node 22): corepack enable && corepack prepare pnpm@9.15.4 --activate" >&2
    exit 1
  fi
  (
    cd "${WEB_DIR}"
    pnpm install --frozen-lockfile
    pnpm run generate
  )
}

do_rollback() {
  load_env_deploy
  require_public_html
  mkdir -p "${RELEASES_DIR}"
  local latest
  latest="$(ls -1t "${RELEASES_DIR}"/*.tar.gz 2>/dev/null | head -n 1 || true)"
  if [[ -z "${latest}" ]]; then
    echo "error: no snapshots in ${RELEASES_DIR}/" >&2
    exit 1
  fi
  echo "→ restore ${latest} → ${PUBLIC_HTML}/"
  # Clear contents but keep directory and excluded DA paths if present
  find "${PUBLIC_HTML}" -mindepth 1 -maxdepth 1 \
    ! -name '.well-known' \
    ! -name '.htaccess' \
    -exec rm -rf {} +
  tar -xzf "${latest}" -C "${PUBLIC_HTML}"
  echo "✓ rollback complete from $(basename "${latest}")"
}

case "${MODE}" in
  rollback)
    do_rollback
    ;;
  deploy|dry-run)
    load_env_deploy
    require_public_html
    pull_main
    generate_site
    require_generate_artifact
    if [[ "${MODE}" == "deploy" ]]; then
      snapshot_public_html
    else
      echo "→ skip snapshot (dry-run)"
    fi
    rsync_publish
    if [[ "${MODE}" == "dry-run" ]]; then
      echo "✓ dry-run complete (live site unchanged)"
    else
      echo "✓ deploy complete → ${PUBLIC_HTML}"
      echo "  Smoke: curl -sS -o /dev/null -w '%{http_code}\\n' https://YOUR_DOMAIN/"
    fi
    ;;
esac
