# Gate C — Type-safety: PASS

## Web

```
pnpm run typecheck   # nuxt typecheck (vue-tsc)
→ geen fouten
```

Onderweg gerepareerd: `process.env` in `nuxt.config.ts` (geen Node-types
beschikbaar) en een `robots`-sleutel in `routeRules` die zonder de bijbehorende
module niet bestaat. Beide vervangen door constructies die Nuxt zelf kent.

## API

```
composer run analyse   # PHPStan 2.2.9 + larastan, level 6
→ [OK] No errors
```

Startpunt was 83 meldingen (en blijft schoon na het toevoegen van de vanaf-prijslogica). Die zijn opgelost door het onderliggende probleem aan
te pakken, niet door ze te onderdrukken:

* Alle Eloquent-modellen hebben nu een volledig `@property`-blok, zodat PHPStan
  weet dat bijvoorbeeld `created_at` een `Carbon` is en geen `string`. Dat waren
  ruim zeventig van de meldingen.
* `HasFactory` is verwijderd uit de modellen die geen factory hebben; de
  `@use`-tags verwezen naar klassen die niet bestonden.
* `$lead->tier?->value ?? …` werd `$lead->tier->value ?? …`: de nullsafe-operator
  is overbodig links van `??`.
* `env()` buiten de config-map vervangen door een echte configsleutel
  (`agent.owner.initial_password`).
* Twee `collect()`-aanroepen in de tests op ongetypeerde JSON vervangen door
  `array_column` met een expliciete vorm.

Er staan **geen** `@phpstan-ignore`-regels en er is geen baseline.

## Codestijl

```
vendor/bin/pint --test
→ passed
```
