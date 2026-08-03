# Infra review — static on-server deploy

## Change

- `nitro.preset: static` + `pnpm run generate` for Apache-servable output (`.output/public`)
- Root `Makefile` targets: `deploy-production`, `deploy-production-dry-run`, `rollback-production`
- `scripts/deploy-production.sh`: pull `main`, generate, fail-closed `PUBLIC_HTML` guards, snapshot, rsync with `.well-known` / `.htaccess` excludes
- `.env.deploy.example` (gitignored `.env.deploy` on VPS)
- Runbook: `docs/runbooks/deploy-production.md`

## Gate / policy notes

| Item | Status |
|------|--------|
| Local pnpm | Still via Docker (`docker compose exec web …`) |
| VPS pnpm | Explicit exception — host Node 22 + corepack pnpm 9.15.4 for generate |
| Gate F (prod) | Success = `pnpm run generate` with `index.html` + `_nuxt/` before rsync |
| Playwright | Unchanged — never on host |
| Secrets | `.env.deploy` ignored; example has placeholder path only |

## Security / ops

- Fail-closed if `PUBLIC_HTML` unset, relative, missing, or not ending in `public_html`
- No rsync unless generate artifact validates
- Snapshots in `releases/` (last 3) enable `make rollback-production`
- `rsync --delete` scoped to Nuxt-owned docroot; preserves ACME / DA `.htaccess`

## Rollback

1. `make rollback-production` (newest `releases/*.tar.gz`), or
2. Revert git commit and re-run `make deploy-production`
