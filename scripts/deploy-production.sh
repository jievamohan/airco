#!/usr/bin/env bash
# On-VPS static deploy for KlimaatX (TransIP / DirectAdmin).
# Usage (from repo root):
#   ./scripts/deploy-production.sh           # pull, generate, publish
#   ./scripts/deploy-production.sh --dry-run # same, but rsync --dry-run
#   ./scripts/deploy-production.sh --rollback
#   DEPLOY_REF=<sha> ./scripts/deploy-production.sh   # pin checkout (CI/CD)
#   ./scripts/deploy-production.sh --ref <sha>        # same as DEPLOY_REF
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

WEB_DIR="${ROOT_DIR}/apps/web"
OUT_PUBLIC="${WEB_DIR}/.output/public"
RELEASES_DIR="${ROOT_DIR}/releases"
KEEP_RELEASES=3

MODE="deploy"
DEPLOY_REF="${DEPLOY_REF:-}"

while [[ $# -gt 0 ]]; do
  case "${1}" in
    --dry-run)
      MODE="dry-run"
      shift
      ;;
    --rollback)
      MODE="rollback"
      shift
      ;;
    --ref)
      if [[ -z "${2:-}" ]]; then
        echo "error: --ref requires a commit SHA or ref" >&2
        exit 2
      fi
      DEPLOY_REF="${2}"
      shift 2
      ;;
    *)
      echo "error: unknown argument '${1}' (use --dry-run, --rollback, or --ref SHA)" >&2
      exit 2
      ;;
  esac
done

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

sync_main() {
  echo "→ git fetch origin main"
  git fetch origin main
  git checkout main
  if [[ -n "${DEPLOY_REF}" ]]; then
    echo "→ checkout pinned ref ${DEPLOY_REF}"
    git reset --hard "${DEPLOY_REF}"
  else
    echo "→ git pull --ff-only origin main"
    git pull --ff-only origin main
  fi
  echo "→ HEAD $(git rev-parse --short HEAD) ($(git rev-parse HEAD))"
}

pnpm_version_from_package() {
  local pm
  pm="$(grep -o '"packageManager"[[:space:]]*:[[:space:]]*"pnpm@[0-9.]*"' "${WEB_DIR}/package.json" \
    | sed -E 's/.*pnpm@([0-9.]+)".*/\1/' || true)"
  if [[ -n "${pm}" ]]; then
    echo "${pm}"
  else
    echo "9.15.9"
  fi
}

ensure_node_toolchain() {
  if command -v pnpm >/dev/null 2>&1; then
    echo "→ node $(node -v), pnpm $(pnpm -v)"
    return 0
  fi

  # GitHub Actions / non-interactive SSH skips login profiles — load nvm if present.
  if [[ -s "${HOME}/.nvm/nvm.sh" ]]; then
    export NVM_DIR="${HOME}/.nvm"
    # shellcheck disable=SC1091
    source "${HOME}/.nvm/nvm.sh"
    if [[ -f "${ROOT_DIR}/.nvmrc" ]]; then
      echo "→ nvm use ($(cat "${ROOT_DIR}/.nvmrc"))"
      nvm use --silent || nvm install
    fi
  fi

  if command -v node >/dev/null 2>&1 && ! command -v pnpm >/dev/null 2>&1; then
    local pnpm_ver
    pnpm_ver="$(pnpm_version_from_package)"
    echo "→ corepack enable + prepare pnpm@${pnpm_ver}"
    corepack enable
    corepack prepare "pnpm@${pnpm_ver}" --activate
  fi

  if ! command -v pnpm >/dev/null 2>&1; then
    local pnpm_ver
    pnpm_ver="$(pnpm_version_from_package)"
    echo "error: pnpm not found in non-interactive SSH session." >&2
    echo "Ensure Node + corepack are installed for ${USER:-deploy user}:" >&2
    echo "  corepack enable && corepack prepare pnpm@${pnpm_ver} --activate" >&2
    echo "Or add nvm/node to a login profile (~/.bash_profile) and retry." >&2
    exit 1
  fi

  echo "→ node $(node -v), pnpm $(pnpm -v)"
}

generate_site() {
  ensure_node_toolchain
  echo "→ pnpm install --frozen-lockfile + generate (apps/web)"
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
    sync_main
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
