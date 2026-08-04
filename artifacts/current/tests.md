# Tests — Apple heating scroll

## Automated
- `docker compose exec web pnpm run typecheck` — **PASS**
- Playwright — deferred (no e2e service); `data-testid="heating-story"` + `data-scroll-phase` ready

## Manual checklist (brief + plan acceptance)

| # | Check | Status |
|---|--------|--------|
| 1 | `#verwarmen` + `heating-story` present; no 70% claim | PASS (SSR HTML) |
| 2 | Scene 1 copy exact; product pair WebP | PASS (SSR) |
| 3 | Scene 2 figures €0,07 / €0,17; bar-ratio 2.43 | PASS (DOM attrs) |
| 4 | Scene 3 59% + assumptions + disclaimer | PASS (SSR) |
| 5 | Track ~350vh desktop sticky pin | PASS (`min-height:350vh`) |
| 6 | Mobile/reduced: linear stack via `heat--mobile` / `heat--reduced` | implemented |
| 7 | GSAP client-only dynamic import + context revert | implemented |
| 8 | Winter handoff margins −40vh / −20vh preserved | implemented |

## Brief zelfreview (implementation intent)

1. Kernboodschap in 3s — Scene 1 headlines + products  
2. Techniek ondergeschikt — assumptions only in Scene 3  
3. Premium / rustig — white, Outfit, centered column  
4. Geen overbodige elementen — brief copy only  
5. Scroll subtiel — opacity / Y / scale / bar fill via ST scrub  
6. Apple-achtig — one message per beat, Layout B  
7. Zelfde warmte, lagere prijs — Scene 2 line + Scene 3 59%

**Verdict:** PASS for ship of this iteration (visual QA in browser recommended).
