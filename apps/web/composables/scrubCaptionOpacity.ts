/**
 * Fade in → hold at peak → fade out within [from, to].
 * @param hold Fraction of the window spent at full opacity (0–1). Default ~half the window.
 */
export function scrubCaptionOpacity(
  from: number,
  to: number,
  progress: number,
  hold = 0.5,
) {
  if (progress <= from || progress >= to) return 0

  const span = to - from
  if (span <= 0) return 0

  const holdSpan = span * Math.min(1, Math.max(0, hold))
  const fadeSpan = (span - holdSpan) / 2

  // Degenerate: almost all hold
  if (fadeSpan <= 0.0001) {
    return progress > from && progress < to ? 1 : 0
  }

  const fadeInEnd = from + fadeSpan
  const fadeOutStart = to - fadeSpan

  if (progress >= fadeInEnd && progress <= fadeOutStart) return 1
  if (progress < fadeInEnd) return (progress - from) / fadeSpan
  return (to - progress) / fadeSpan
}
