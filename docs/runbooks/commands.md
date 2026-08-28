# Canonical commands (KlimaatX / airco)

## Stack

- Web: Nuxt 3 onder `apps/web` (pnpm) — landingspagina en het CRM-dashboard onder `/dashboard`
- API: Laravel 12 onder `apps/api` (composer, MySQL) — CRM, agent-workflow en integraties

## Docker

```bash
docker compose up -d --build
docker compose exec web pnpm run typecheck
docker compose exec web pnpm run build
docker compose logs -f web

docker compose exec api php artisan migrate --force
docker compose exec api php artisan db:seed --force
docker compose exec api composer test
docker compose exec api composer analyse
docker compose exec api vendor/bin/pint --test
```

Open: http://localhost:3010 (web) en http://localhost:8010 (api)

Het CRM-dashboard zit op **http://localhost:3010/dashboard**. Inloggen kan met
het account dat `DatabaseSeeder` aanmaakt: het adres uit
`OWNER_NOTIFICATION_EMAIL` en het wachtwoord uit `OWNER_INITIAL_PASSWORD`
(standaard `wachtwoord-wijzigen` — wijzig dat direct).

Demodata om het dashboard gevuld te zien:

```bash
docker compose exec api php artisan db:seed --class='Database\\Seeders\\DemoSeeder'
```

Die seeder maakt acht leads verspreid over de funnel, met gesprekken,
transcripten, offertes en afspraken. Alleen voor lokaal en demo, nooit op
productie.

## Agent-workflow

De workflow draait op twee processen: de scheduler (hartslag) en een queue-worker.

```bash
# Hartslag: gesprekken doorzetten en opvolgstappen uitvoeren (elke minuut)
docker compose exec api php artisan schedule:work

# Wachtrij: verrijken, offertes mailen, afspraken boeken
docker compose exec api php artisan queue:work --tries=3

# Handmatig een stap forceren
docker compose exec api php artisan leads:poll-mailbox
docker compose exec api php artisan agent:tick
```

In productie draaien deze als systemd-units of via cron; zie
[agent-workflow.md](./agent-workflow.md).

## Production deploy (VPS / DirectAdmin)

Static site publish runs **on the TransIP VPS** (host pnpm, not Docker):

```bash
make deploy-production
make deploy-production-dry-run
make rollback-production
```

See [deploy-production.md](./deploy-production.md). De API wordt apart uitgerold;
zie [agent-workflow.md](./agent-workflow.md).

**Auto-deploy:** merging a PR into `main` triggers `.github/workflows/ci-deploy.yml` (CI → SSH deploy → optional smoke). Configure the `production` environment in GitHub (secret: `VPS_SSH_KEY`; variables: host, user, deploy path — see deploy runbook).

## Notes

- Locally: do not run `pnpm` on the host; use `docker compose exec web …`.
- On the VPS: `make deploy-production` uses host Node 22.14+ + pnpm 9.15.9 (documented exception).
- Playwright / e2e service: deferred (Lane I follow-up). Never run Playwright on the host.
- `NUXT_PUBLIC_API_BASE` moet bij het bouwen van de web-app op de publieke API-URL staan,
  anders wijst het formulier en het dashboard naar localhost.
