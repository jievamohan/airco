# Dependency review — gsap

## Change
- Package: `gsap` `^3.15.0`
- App: `apps/web`
- Install: `docker compose exec web pnpm add gsap`
- Lockfile: `apps/web/pnpm-lock.yaml` updated

## Why
Design brief for `#verwarmen` Apple-style scroll section requires GSAP + ScrollTrigger exclusively for motion (opacity, translateY, light scale, clip/mask scrubbed to scroll).

## Scope of use
- Client-only dynamic import inside `useHeatingStoryScroll`
- ScrollTrigger scrub on Heating sticky track only
- Not used on Hero / Product / Climate / Winter (remain RAF sticky scrub)

## Risk
- **Low** — animation library; no network/auth surface
- Bundle: GSAP loaded only when Heating section mounts (dynamic import)
- License: GSAP standard license (free for most use cases; ScrollTrigger included in core package distribution used here)

## Rollback
Remove dependency and heating GSAP composable; restore prior HeatingSection claim block if needed.

## Verdict
**PASS** — allowed for this feature.
