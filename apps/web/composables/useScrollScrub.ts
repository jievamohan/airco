/**
 * Scroll-linked video scrubbing with intro/scrub/outro phase split.
 * Expects a scrub-friendly MP4 (frequent keyframes / gop=1, H.264 + AAC).
 */
import {
  mapScrollPhases,
  readStickyTrackProgress,
  SCRUB_PHASE_DESKTOP,
  type PhaseOpts,
  type ScrollPhase,
} from './mapScrollPhases'

export function useScrollScrub(options: {
  container: Ref<HTMLElement | null>
  video: Ref<HTMLVideoElement | null>
  enabled: Ref<boolean>
  scrubRange?: Ref<PhaseOpts>
  pauseSeekOffscreen?: boolean
}) {
  const trackProgress = ref(0)
  const scrubProgress = ref(0)
  const enterProgress = ref(0)
  const exitProgress = ref(0)
  const phase = ref<ScrollPhase>('intro')
  /** Alias of scrubProgress for callers that still expect `progress`. */
  const progress = scrubProgress

  let raf = 0
  let lastTime = -1
  let seeking = false
  let onscreen = true
  let io: IntersectionObserver | null = null

  const pauseOffscreen = options.pauseSeekOffscreen !== false

  const resolveRange = (): PhaseOpts =>
    options.scrubRange?.value ?? SCRUB_PHASE_DESKTOP

  const applyPhases = (track: number) => {
    const mapped = mapScrollPhases(track, resolveRange())
    trackProgress.value = track
    scrubProgress.value = mapped.scrubProgress
    enterProgress.value = mapped.enterProgress
    exitProgress.value = mapped.exitProgress
    phase.value = mapped.phase
  }

  const canSeek = (vid: HTMLVideoElement) => {
    if (!Number.isFinite(vid.duration) || vid.duration <= 0) return false
    return vid.readyState >= HTMLMediaElement.HAVE_METADATA
  }

  const seekToScrub = (scrolled: number) => {
    const vid = options.video.value
    if (!vid || !canSeek(vid) || seeking || vid.seeking) return
    if (!options.enabled.value) return
    if (pauseOffscreen && !onscreen) return

    const fps = 24
    const frameIndex = Math.round(scrolled * (vid.duration * fps - 1))
    const t = Math.min(vid.duration - 1 / fps, Math.max(0, frameIndex / fps))
    if (Math.abs(t - lastTime) >= 1 / fps - 0.0005) {
      lastTime = t
      seeking = true
      const done = () => {
        seeking = false
        vid.removeEventListener('seeked', done)
      }
      vid.addEventListener('seeked', done)
      try {
        vid.currentTime = t
      } catch {
        seeking = false
      }
      window.setTimeout(() => {
        seeking = false
      }, 120)
    }
  }

  const tick = () => {
    // Always measure track — even when scrub/video disabled (reduced motion / loading).
    const track = readStickyTrackProgress(options.container.value)
    applyPhases(track)
    seekToScrub(scrubProgress.value)
    raf = requestAnimationFrame(tick)
  }

  const bindIo = (el: HTMLElement | null) => {
    io?.disconnect()
    io = null
    if (!pauseOffscreen || !el || typeof IntersectionObserver === 'undefined') {
      onscreen = true
      return
    }
    io = new IntersectionObserver(
      ([entry]) => {
        onscreen = entry?.isIntersecting ?? true
      },
      { root: null, threshold: 0 },
    )
    io.observe(el)
  }

  onMounted(() => {
    applyPhases(readStickyTrackProgress(options.container.value))
    bindIo(options.container.value)
    raf = requestAnimationFrame(tick)
  })

  onUnmounted(() => {
    cancelAnimationFrame(raf)
    io?.disconnect()
    io = null
  })

  watch(
    () =>
      [
        options.enabled.value,
        options.video.value,
        options.container.value,
        options.scrubRange?.value.introEnd,
        options.scrubRange?.value.outroStart,
      ] as const,
    () => {
      lastTime = -1
      seeking = false
      bindIo(options.container.value)
      applyPhases(readStickyTrackProgress(options.container.value))
    },
  )

  return {
    trackProgress,
    scrubProgress,
    progress,
    phase,
    enterProgress,
    exitProgress,
  }
}
