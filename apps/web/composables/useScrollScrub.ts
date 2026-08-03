/**
 * Scroll-linked video scrubbing.
 * Expects a scrub-friendly MP4 (frequent keyframes / gop=1, H.264 + AAC).
 */
export function useScrollScrub(options: {
  container: Ref<HTMLElement | null>
  video: Ref<HTMLVideoElement | null>
  enabled: Ref<boolean>
}) {
  const progress = ref(0)

  let raf = 0
  let lastTime = -1
  let seeking = false

  const clamp01 = (n: number) => Math.min(1, Math.max(0, n))

  const readScrollProgress = () => {
    const el = options.container.value
    if (!el) return 0
    const rect = el.getBoundingClientRect()
    const scrollable = Math.max(rect.height - window.innerHeight, 1)
    return clamp01(-rect.top / scrollable)
  }

  const canSeek = (vid: HTMLVideoElement) => {
    if (!Number.isFinite(vid.duration) || vid.duration <= 0) return false
    // HAVE_METADATA is enough after iOS prime; seekable may lag briefly
    return vid.readyState >= HTMLMediaElement.HAVE_METADATA
  }

  const tick = () => {
    if (!options.enabled.value) {
      progress.value = 0
      raf = requestAnimationFrame(tick)
      return
    }

    const scrolled = readScrollProgress()
    progress.value = scrolled

    const vid = options.video.value
    if (vid && canSeek(vid) && !seeking && !vid.seeking) {
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
        // iOS sometimes skips seeked
        window.setTimeout(() => {
          seeking = false
        }, 120)
      }
    }

    raf = requestAnimationFrame(tick)
  }

  onMounted(() => {
    progress.value = readScrollProgress()
    raf = requestAnimationFrame(tick)
  })

  onUnmounted(() => {
    cancelAnimationFrame(raf)
  })

  watch(
    () => [options.enabled.value, options.video.value, options.container.value] as const,
    () => {
      lastTime = -1
      seeking = false
      progress.value = readScrollProgress()
    },
  )

  return { progress }
}
