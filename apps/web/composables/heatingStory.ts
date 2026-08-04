/**
 * Scroll-story constants + helpers for the airco vs CV heating cost section.
 * Exact figures only — no live tariff API.
 */
import { clamp01, easeEnter } from './mapScrollPhases'

/** Desktop section track height (vh), including sticky pin. */
export const HEATING_TRACK_VH = 560

/** Mobile track — shorter sticky story, still one pin. */
export const HEATING_TRACK_VH_MOBILE = 420

/**
 * Intro band matched to winter exit overlap (HANDOFF_SCROLL_VH / scrollable).
 * Desktop: 40 / (560 - 100) ≈ 0.087
 * Mobile: 20 / (420 - 100) ≈ 0.0625
 */
export const HEATING_PHASE_DESKTOP = {
  introEnd: 0.087,
  outroStart: 0.94,
} as const

export const HEATING_PHASE_MOBILE = {
  introEnd: 0.063,
  outroStart: 0.94,
} as const

/** Hard-coded rekenvoorbeeld — do not invent other figures. */
export const HEATING_FIGURES = {
  powerPrice: '€ 0,28',
  cop: 4,
  heatFromPower: '4 kWh warmte',
  aircoPerKwh: '€ 0,07',
  gasPrice: '€ 1,40',
  gasEnergy: '10 kWh',
  boilerEfficiency: '80,7%',
  boilerHeat: '8,07 kWh warmte',
  boilerPerKwh: '€ 0,17',
  savingPct: '59%',
  barRatio: 2.43,
  rangeLow: '35%',
  rangeHigh: '60%',
} as const

export type HeatingSceneId =
  | 'intro'
  | 'aircoOutput'
  | 'aircoCost'
  | 'boilerOutput'
  | 'boilerCost'
  | 'compare'
  | 'saving'
  | 'context'

/** Inclusive scrub bands for the eight scenes (within scrubProgress 0→1). */
export const HEATING_SCENES: ReadonlyArray<{
  id: HeatingSceneId
  from: number
  to: number
}> = [
  { id: 'intro', from: 0.0, to: 0.11 },
  { id: 'aircoOutput', from: 0.09, to: 0.24 },
  { id: 'aircoCost', from: 0.22, to: 0.37 },
  { id: 'boilerOutput', from: 0.35, to: 0.5 },
  { id: 'boilerCost', from: 0.48, to: 0.61 },
  { id: 'compare', from: 0.59, to: 0.72 },
  { id: 'saving', from: 0.7, to: 0.86 },
  { id: 'context', from: 0.84, to: 1.0 },
]

export function sceneLocal(scrub: number, from: number, to: number): number {
  const span = Math.max(to - from, 0.0001)
  return clamp01((scrub - from) / span)
}

/**
 * Opacity envelope: fade in → hold → fade out within a scene-local window.
 * Uses soft ease on edges.
 */
export function sceneOpacity(
  local: number,
  fadeInEnd = 0.18,
  fadeOutStart = 0.82,
): number {
  const t = clamp01(local)
  if (t <= 0 || t >= 1) return 0
  if (t < fadeInEnd) return easeEnter(t / Math.max(fadeInEnd, 0.0001))
  if (t > fadeOutStart) {
    const u = (t - fadeOutStart) / Math.max(1 - fadeOutStart, 0.0001)
    return 1 - easeEnter(u)
  }
  return 1
}

/** Staggered reveal inside a scene: 0→1 between start/end of local progress. */
export function beatOpacity(
  local: number,
  start: number,
  end: number,
  hold = true,
): number {
  const t = clamp01(local)
  if (t < start) return 0
  if (t >= end) return hold ? 1 : 0
  return easeEnter((t - start) / Math.max(end - start, 0.0001))
}

/** Soft exit after a beat peaks (for sequential typography). */
export function beatWindow(
  local: number,
  from: number,
  to: number,
  holdFrac = 0.45,
): number {
  if (local <= from || local >= to) return 0
  const span = to - from
  const holdSpan = span * holdFrac
  const fade = (span - holdSpan) / 2
  if (fade <= 0.0001) return 1
  const fadeInEnd = from + fade
  const fadeOutStart = to - fade
  if (local >= fadeInEnd && local <= fadeOutStart) return 1
  if (local < fadeInEnd) {
    return easeEnter((local - from) / fade)
  }
  return 1 - easeEnter((local - fadeOutStart) / fade)
}

export function translateYReveal(opacity: number, maxPx = 28): string {
  const y = (1 - clamp01(opacity)) * maxPx
  return `translate3d(0, ${y}px, 0)`
}

export function productScale(opacity: number, from = 0.96, to = 1): number {
  const t = easeEnter(clamp01(opacity))
  return from + (to - from) * t
}
