# Plan: Explode → Climate handoff

## Goal
Replace the clip-path wipe between ProductExplode (deconstructed binnenunit) and Climate with a parallel scroll + opacity crossfade.

## Behavior
1. Last explode caption (`én te kunnen verwarmen`) holds at full opacity once shown (no early fade-out).
2. Explode pin scrolls up and fades opacity 1→0 during outro.
3. Climate pin scrolls into view and fades opacity 0→1 during intro, same distance (`HANDOFF_SCROLL_VH`) and linear speed.
4. Reduced motion unchanged (no handoff styles).

## Files
- `apps/web/composables/mapScrollPhases.ts` — `handoffPinStyle` helper
- `apps/web/components/landing/ProductExplodeSection.vue` — exit handoff + last caption hold
- `apps/web/components/landing/ClimateSection.vue` — enter handoff (exit wipe to Heating kept)
