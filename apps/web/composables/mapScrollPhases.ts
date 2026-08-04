export type ScrollPhase = 'intro' | 'scrub' | 'outro'

export type PhaseOpts = {
  introEnd: number
  outroStart: number
}

export type PhaseMap = {
  phase: ScrollPhase
  enterProgress: number
  scrubProgress: number
  exitProgress: number
}

export const SCRUB_PHASE_DESKTOP: PhaseOpts = {
  introEnd: 0.1,
  outroStart: 0.92,
}

export const SCRUB_PHASE_MOBILE: PhaseOpts = {
  introEnd: 0.06,
  outroStart: 0.94,
}

/** Incoming must reach this before outgoing finishes clearing. */
export const HANDOFF_HOLD = 0.35

export function clamp01(n: number) {
  return Math.min(1, Math.max(0, n))
}

/** Sticky-track progress: 0→1 while pin is stuck. */
export function readStickyTrackProgress(el: HTMLElement | null) {
  if (!el || typeof window === 'undefined') return 0
  const rect = el.getBoundingClientRect()
  const scrollable = Math.max(rect.height - window.innerHeight, 1)
  return clamp01(-rect.top / scrollable)
}

/**
 * Split full-track progress into intro / scrub / outro bands.
 * Captions + video seek must use scrubProgress only.
 */
export function mapScrollPhases(
  trackProgress: number,
  opts: PhaseOpts,
): PhaseMap {
  const introEnd = clamp01(opts.introEnd)
  const outroStart = Math.max(introEnd + 0.0001, clamp01(opts.outroStart))
  const p = clamp01(trackProgress)

  if (p < introEnd) {
    const span = Math.max(introEnd, 0.0001)
    return {
      phase: 'intro',
      enterProgress: clamp01(p / span),
      scrubProgress: 0,
      exitProgress: 0,
    }
  }

  if (p > outroStart) {
    const span = Math.max(1 - outroStart, 0.0001)
    return {
      phase: 'outro',
      enterProgress: 1,
      scrubProgress: 1,
      exitProgress: clamp01((p - outroStart) / span),
    }
  }

  const span = Math.max(outroStart - introEnd, 0.0001)
  return {
    phase: 'scrub',
    enterProgress: 1,
    scrubProgress: clamp01((p - introEnd) / span),
    exitProgress: 0,
  }
}

/** Enter settle: cubic-bezier(0.22, 1, 0.36, 1) approx. */
export function easeEnter(t: number) {
  const p = clamp01(t)
  return 1 - (1 - p) ** 3
}

/** Exit commit: cubic-bezier(0.4, 0, 0.2, 1) approx. */
export function easeExit(t: number) {
  const p = clamp01(t)
  return p < 0.5 ? 4 * p * p * p : 1 - (-2 * p + 2) ** 3 / 2
}
