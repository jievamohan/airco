# Tests

No web unit test runner in package. Behavior covered by existing e2e scrub testids (`product-scrub`, `climate-scrub`) and data attributes (`data-scroll-phase`, `data-track-progress`).

Manual checks:
- [ ] Last caption holds through end of explode scrub
- [ ] Explode pin translates up + fades during outro
- [ ] Climate pin translates up + fades in during intro at matching speed
- [ ] Reduced motion: static pins, no handoff transforms
