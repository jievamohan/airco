#!/usr/bin/env bash
# Bridges Stitch (Streamable HTTP MCP) to stdio for Cursor. Loads secrets from repo-root .env (gitignored).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT}/.env"
if [[ ! -f "${ENV_FILE}" ]]; then
  echo "stitch-mcp: missing ${ENV_FILE} — copy .env.example to .env and set STITCH_GOOGLE_API_KEY" >&2
  exit 1
fi
set -a
# shellcheck source=/dev/null
source "${ENV_FILE}"
set +a
if [[ -z "${STITCH_GOOGLE_API_KEY:-}" ]]; then
  echo "stitch-mcp: STITCH_GOOGLE_API_KEY is empty in .env" >&2
  exit 1
fi

# Cursor’s GUI-launched MCP krijgt vaak een minimaal PATH (geen Homebrew/nvm) — npx moet vindbaar zijn.
ensure_npx() {
  if command -v npx >/dev/null 2>&1; then
    return 0
  fi
  export PATH="/opt/homebrew/bin:/usr/local/bin:${HOME}/.local/bin:${PATH:-}"
  if command -v npx >/dev/null 2>&1; then
    return 0
  fi
  if [[ -s "${HOME}/.nvm/nvm.sh" ]]; then
    # shellcheck source=/dev/null
    source "${HOME}/.nvm/nvm.sh"
  fi
  if command -v npx >/dev/null 2>&1; then
    return 0
  fi
  if command -v fnm >/dev/null 2>&1; then
    eval "$(fnm env)"
  fi
  if command -v npx >/dev/null 2>&1; then
    return 0
  fi
  echo "stitch-mcp: npx not found. Installeer Node.js (bijv. brew install node) of zorg dat npx op PATH staat voor GUI-apps." >&2
  return 1
}
ensure_npx || exit 1

exec npx -y supergateway \
  --streamableHttp "https://stitch.googleapis.com/mcp" \
  --header "X-Goog-Api-Key: ${STITCH_GOOGLE_API_KEY}"
