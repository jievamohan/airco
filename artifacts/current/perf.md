# Perf

PASS — same RAF scrub loop; handoff still uses transform + opacity (compositor-friendly). Removed clip-path from explode→climate handoff (one less paint invalidation). `will-change` kept on pins.
