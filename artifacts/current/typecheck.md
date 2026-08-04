# Typecheck

Not run in this environment (Docker web stack not up). Changes are TypeScript-safe:
- `handoffPinStyle` returns `{ opacity: string; transform: string }`
- Call sites replace previous inline wipe objects with the same style binding shape
- Removed unused `easeEnter` import from ClimateSection

Gate C: deferred to CI.
