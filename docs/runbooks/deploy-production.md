# Deploy production (static → DirectAdmin `public_html`)

Run **on the TransIP VPS**, inside the git checkout. This flow does **not** use Docker.

**Exception to local-dev rule:** on the VPS, `pnpm` runs on the host (Node 22 + corepack). Locally, keep using `docker compose exec web pnpm …`.

## Prerequisites (VPS)

- Git clone of this repo with access to `origin`
- Node.js **22**
- pnpm **9.15.4** via corepack:

```bash
corepack enable
corepack prepare pnpm@9.15.4 --activate
node -v   # v22.x
pnpm -v   # 9.15.4
```

- `rsync`, `tar`, `make`
- DirectAdmin docroot already exists, e.g. `/home/<user>/domains/<domain>/public_html`

## One-time setup

```bash
cd /path/to/airco
cp .env.deploy.example .env.deploy
# Edit PUBLIC_HTML to your real docroot (must be absolute and end with public_html)
```

`.env.deploy` is gitignored. Never commit it.

## Deploy

Manual (on VPS):

```bash
cd /path/to/airco
make deploy-production
```

### Automatic deploy (GitHub Actions)

When a pull request is **merged into `main`**, the workflow [`.github/workflows/ci-deploy.yml`](../../.github/workflows/ci-deploy.yml) runs:

1. **CI** — typecheck + `pnpm run generate` (same checks on every PR to `main`)
2. **Deploy** — SSH to the VPS, checkout the merge commit, then `make deploy-production` (build + rsync to `PUBLIC_HTML`)
3. **Smoke check** — optional HTTP 200 against `PRODUCTION_URL`

Configure under **Settings → Environments → production** (the deploy job uses `environment: production`).

**Environment secret** (1):

| Name | Example |
|------|---------|
| `VPS_SSH_KEY` | Private SSH key (PEM) |

**Environment variables**:

| Name | Required | Example |
|------|----------|---------|
| `VPS_SSH_HOST` | yes | VPS hostname or IP |
| `VPS_SSH_USER` | yes | DirectAdmin / SSH user |
| `VPS_DEPLOY_PATH` | yes | `/home/user/airco` |
| `VPS_SSH_PORT` | no | `22` |
| `PRODUCTION_URL` | no | `https://klimaatx.nl/` |

Repository-level secrets/variables work too, but the **production** environment is preferred (optional approval rules, separate config per env).

What the deploy script does (manual or CI):

1. `git fetch` + `git checkout main` + `git pull --ff-only origin main`
2. `cd apps/web && pnpm install --frozen-lockfile && pnpm run generate`
3. Abort unless `.output/public/index.html` and `_nuxt/` exist
4. Snapshot current `PUBLIC_HTML` → `releases/YYYYMMDD-HHMMSS.tar.gz` (keeps last 3)
5. `rsync -a --delete` from `apps/web/.output/public/` → `PUBLIC_HTML/`, excluding `.well-known` and `.htaccess`

**Warning:** rsync `--delete` removes files inside `PUBLIC_HTML` that are not in the generate output (except the excludes above). Do not point `PUBLIC_HTML` at a shared tree that holds unrelated sites.

## Dry-run (no live delete)

```bash
make deploy-production-dry-run
```

Still pulls and generates; rsync runs with `--dry-run`. No snapshot write to live tree.

## Rollback

```bash
make rollback-production
```

Restores the newest archive under `releases/` into `PUBLIC_HTML` (preserves `.well-known` / `.htaccess` if present).

## Fail-closed behaviour

| Condition | Result |
|-----------|--------|
| `PUBLIC_HTML` unset / relative / not ending in `public_html` / missing | Exit non-zero; live site untouched |
| Generate fails or no `index.html` | Exit non-zero; no rsync |
| Lockfile mismatch (`pnpm install --frozen-lockfile`) | Exit non-zero; no rsync |

## Smoke checklist (after deploy)

```bash
# Replace with your domain
curl -sS -o /dev/null -w '%{http_code}\n' https://YOUR_DOMAIN/
curl -sS -o /dev/null -w '%{http_code}\n' https://YOUR_DOMAIN/media/hero.png
# Confirm SHA printed by the script matches:
git rev-parse --short HEAD
```

Expect HTTP `200` and HTML containing `KlimaatX`. Spot-check one `/_nuxt/*.js` URL from `index.html`.

## Notes

- Production artifact is **static** (`nitro.preset: static` / `pnpm run generate`), not Nuxt SSR `node-server`.
- Snapshots live in `releases/` (gitignored) next to the repo root on the VPS.
- When a Laravel API lands later, it must not be served from this static docroot; use a separate origin or Apache reverse proxy.
