# Canonical commands (KlimaatX / airco)

## Stack

- Web: Nuxt 3 under `apps/web` (pnpm)
- API: deferred in v1 (no `apps/api`)

## Docker

```bash
docker compose up -d --build
docker compose exec web pnpm run typecheck
docker compose exec web pnpm run build
docker compose logs -f web
```

Open: http://localhost:3010

## Production deploy (VPS / DirectAdmin)

Static site publish runs **on the TransIP VPS** (host pnpm, not Docker):

```bash
make deploy-production
make deploy-production-dry-run
make rollback-production
```

See [deploy-production.md](./deploy-production.md).

**Auto-deploy:** merging a PR into `main` triggers `.github/workflows/ci-deploy.yml` (CI → SSH deploy → optional smoke). Set GitHub Actions secrets listed in the deploy runbook.

## Notes

- Locally: do not run `pnpm` on the host; use `docker compose exec web …`.
- On the VPS: `make deploy-production` uses host Node 22 + pnpm (documented exception).
- Playwright / e2e service: deferred (Lane I follow-up). Never run Playwright on the host.
- Gate D PHPStan / `composer audit`: N/A until `apps/api` exists — see `artifacts/current/infra-review.md`.
