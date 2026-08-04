# Risk

- **UI motion only** — no auth/payments/crypto/privacy impact.
- **Dependency:** `gsap@^3.15.0` added for Heating ScrollTrigger scrub only — see `dependency-review.md`.
- **Lanes:** I (gsap) + W2 (heatingFigures / useHeatingStoryScroll) + W1 (HeatingSection).
- **Regression:** Winter→Heating handoff margins (−40vh / −20vh) and z-index 1 preserved; CSS sticky + ST scrub (no ScrollTrigger pin) to avoid dual-pin fights.
- **Reduced motion / mobile:** static linear stack; GSAP killed.
- **Claim fidelity:** 70% claim removed; brief 59% + assumptions only.
- **Impact:** low–medium — new dep + long sticky section; scoped to `#verwarmen`.
