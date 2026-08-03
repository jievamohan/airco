# Risk — KlimaatX landing v1

## Privacy / PII

Offerte form collects: name, address, postcode, city, email, phone, area m², notes.

**v1 mitigation:** client-side validation + local success UI only. No `fetch` to API, no persistence, no `console.log` of payloads.

## Auth / crypto / payments

None.

## Animation / performance

Scroll-scrub MP4 (~2MB) lazy-mounted in section 1. `prefers-reduced-motion` uses static PNG. Bundle Gate F: watch video weight; re-encode if scrub janks.

## Deferred

Real `POST /api/leads` requires rate limiting, retention policy, consent copy, and thin responses (rules 40 / 47 / 25).
