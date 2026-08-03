/**
 * iOS Safari-safe setup for scroll-scrubbed videos:
 * - force muted + playsInline properties
 * - prime with play()/pause() after first user gesture
 * - avoid relying solely on HTML attributes (Safari is picky)
 */
export function useScrubVideo(video: Ref<HTMLVideoElement | null>) {
  const ready = ref(false)
  const error = ref(false)
  let primed = false

  const markReady = () => {
    const vid = video.value
    if (!vid) return
    if (!Number.isFinite(vid.duration) || vid.duration <= 0) return

    vid.pause()
    vid.controls = false
    vid.muted = true
    vid.defaultMuted = true
    vid.playsInline = true
    vid.setAttribute('playsinline', '')
    vid.setAttribute('webkit-playsinline', '')
    ready.value = true
  }

  const onError = () => {
    error.value = true
  }

  const syncFromElement = () => {
    const vid = video.value
    if (!vid) return
    if (vid.error) {
      onError()
      return
    }
    if (vid.readyState >= HTMLMediaElement.HAVE_METADATA) {
      markReady()
    }
  }

  const prime = async () => {
    const vid = video.value
    if (!vid || primed || error.value) return
    primed = true
    vid.muted = true
    vid.defaultMuted = true
    vid.playsInline = true
    try {
      // Unlock media pipeline on iOS — then freeze for scrubbing
      const p = vid.play()
      if (p !== undefined) await p
      vid.pause()
      if (vid.readyState >= HTMLMediaElement.HAVE_METADATA) {
        markReady()
      }
    } catch {
      primed = false
    }
  }

  const onGesture = () => {
    void prime()
  }

  onMounted(() => {
    syncFromElement()
    requestAnimationFrame(syncFromElement)

    const vid = video.value
    if (vid) {
      // Explicit load helps iOS when preload is ignored
      try {
        vid.load()
      } catch {
        /* ignore */
      }
    }

    window.addEventListener('touchstart', onGesture, { passive: true, once: true })
    window.addEventListener('scroll', onGesture, { passive: true, once: true })
  })

  onUnmounted(() => {
    window.removeEventListener('touchstart', onGesture)
    window.removeEventListener('scroll', onGesture)
  })

  watch(video, () => {
    primed = false
    ready.value = false
    syncFromElement()
    const vid = video.value
    if (vid) {
      try {
        vid.load()
      } catch {
        /* ignore */
      }
    }
  })

  return {
    ready,
    error,
    markReady,
    onError,
    syncFromElement,
    prime,
  }
}
