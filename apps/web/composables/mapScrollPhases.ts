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

/**
 * Product intro matched to hero exit band (HANDOFF_SCROLL_VH) so
 * enterProgress and hero exit complete together (same visual speed).
 * Desktop: 40vh / (230vh - 100vh) ≈ 0.308
 * Mobile: 40vh / (175vh - 100vh) ≈ 0.533
 */
export const PRODUCT_PHASE_DESKTOP: PhaseOpts = {
  introEnd: 0.308,
  outroStart: 0.92,
}

export const PRODUCT_PHASE_MOBILE: PhaseOpts = {
  introEnd: 0.533,
  outroStart: 0.94,
}

/**
 * Climate intro length matched to explode outro scroll overlap so
 * enterProgress and exitProgress complete together (same visual speed).
 * Desktop: ~(1-0.92)*130vh / 120vh ≈ 0.087
 * Mobile: ~(1-0.94)*75vh / 65vh ≈ 0.069
 */
export const CLIMATE_PHASE_DESKTOP: PhaseOpts = {
  introEnd: 0.087,
  outroStart: 0.92,
}

export const CLIMATE_PHASE_MOBILE: PhaseOpts = {
  introEnd: 0.069,
  outroStart: 0.94,
}

/** Incoming must reach this before outgoing finishes clearing. */
export const HANDOFF_HOLD = 0.35

/**
 * Shared travel for hero→product and explode→climate pin handoffs.
 * Outgoing scrolls up by this amount while incoming scrolls up from below —
 * same distance so both move at the same visual speed.
 */
export const HANDOFF_SCROLL_VH = 40

export function clamp01(n: number) {
  return Math.min(1, Math.max(0, n))
}

/**
 * Parallel opacity + translateY handoff (linear so both sides match speed).
 * @param progress 0→1 through the handoff band
 * @param direction 'out' scrolls up + fades out; 'in' rises into view + fades in
 */
export function handoffPinStyle(
  progress: number,
  direction: 'in' | 'out',
  distanceVh = HANDOFF_SCROLL_VH,
): { opacity: string; transform: string } {
  const t = clamp01(progress)
  if (direction === 'out') {
    return {
      opacity: String(1 - t),
      transform: `translate3d(0, ${-t * distanceVh}vh, 0)`,
    }
  }
  return {
    opacity: String(t),
    transform: `translate3d(0, ${(1 - t) * distanceVh}vh, 0)`,
  }
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
