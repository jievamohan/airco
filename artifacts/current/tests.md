# Tests — KlimaatX landing v1

## Automated

| Check | Result |
|-------|--------|
| `docker compose exec web pnpm run typecheck` | PASS |
| `docker compose exec web pnpm run build` | PASS |
| `GET http://localhost:3010/` | 200 |
| Media `/media/hero.png`, `1st-animated.mp4`, `1st-start.png` | 200 |

## Manual / browser

| Scenario | Result |
|----------|--------|
| Hero + sticky header + CTA | PASS |
| S1 scroll-scrub advances video (`data-scrub-progress` → ~0.78, `currentTime` ~6.2/8) | PASS |
| Micro label “LUCHTSTROOM” during scrub | PASS |
| S2 clay street + “warmer” accent | PASS |
| Form empty submit → field errors / `aria-invalid` | PASS |
| Valid submit → success copy without persistence claim | PASS |

## Deferred

- Playwright e2e service (Lane I follow-up)
- `prefers-reduced-motion` OS toggle (code path present; not exercised in this pass)
