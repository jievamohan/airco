/**
 * Sticky-track progress (0→1) without video scrubbing.
 * Same measurement as useScrollScrub / readStickyTrackProgress.
 */
import {
  mapScrollPhases,
  readStickyTrackProgress,
  type PhaseOpts,
  type ScrollPhase,
} from './mapScrollPhases'

export function useStickyTrack(options: {
  container: Ref<HTMLElement | null>
  enabled?: Ref<boolean>
  scrubRange: Ref<PhaseOpts>
}) {
  const trackProgress = ref(0)
  const scrubProgress = ref(0)
  const enterProgress = ref(0)
  const exitProgress = ref(0)
  const phase = ref<ScrollPhase>('intro')

  let raf = 0

  const apply = (track: number) => {
    const mapped = mapScrollPhases(track, options.scrubRange.value)
    trackProgress.value = track
    scrubProgress.value = mapped.scrubProgress
    enterProgress.value = mapped.enterProgress
    exitProgress.value = mapped.exitProgress
    phase.value = mapped.phase
  }

  const tick = () => {
    apply(readStickyTrackProgress(options.container.value))
    raf = requestAnimationFrame(tick)
  }

  onMounted(() => {
    apply(readStickyTrackProgress(options.container.value))
    raf = requestAnimationFrame(tick)
  })

  onUnmounted(() => {
    cancelAnimationFrame(raf)
  })

  watch(
    () =>
      [
        options.container.value,
        options.scrubRange.value.introEnd,
        options.scrubRange.value.outroStart,
        options.enabled?.value,
      ] as const,
    () => {
      apply(readStickyTrackProgress(options.container.value))
    },
  )

  return {
    trackProgress,
    scrubProgress,
    enterProgress,
    exitProgress,
    phase,
  }
}
