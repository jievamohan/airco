# KlimaatX API

Laravel 12-applicatie met het CRM en de lead-to-appointment agent: mailbox-intake,
verrijking, uitbellen via een ElevenLabs voice agent, offertes, opvolgcadans en
agenda-afspraken.

De web-app (`apps/web`) praat hier uitsluitend via HTTP mee; het dashboard leeft
onder `/dashboard` in die app.

## Snel starten

Vanuit de repository-root:

```bash
docker compose up -d
docker compose exec api php artisan migrate --seed
```

De API luistert dan op http://localhost:8010. Lokaal staat `AGENT_DRY_RUN=true`:
de workflow loopt volledig, maar er wordt niet echt gebeld, gemaild of geboekt.

## Kwaliteitspoorten

```bash
composer run lint      # Pint
composer run analyse   # PHPStan level 6 (larastan)
composer run test      # PHPUnit
```

## Verder lezen

- `docs/runbooks/agent-workflow.md` — wat er draait, hoe je het instelt, wat je doet als het misgaat
- `docs/research/pricing-baseline.md` — waar de voorlopige prijzen en montagetijden vandaan komen
