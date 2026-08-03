export function useScrollProgress(target: Ref<HTMLElement | null>) {
  const progress = ref(0)

  const clamp01 = (n: number) => Math.min(1, Math.max(0, n))

  const measure = () => {
    const el = target.value
    if (!el) {
      progress.value = 0
      return
    }
    const rect = el.getBoundingClientRect()
    const view = window.innerHeight
    const start = view * 0.85
    const end = view * 0.25
    const raw = (start - rect.top) / (start - end)
    progress.value = clamp01(raw)
  }

  let raf = 0
  let ticking = false
  const onScroll = () => {
    if (ticking) return
    ticking = true
    raf = requestAnimationFrame(() => {
      measure()
      ticking = false
    })
  }

  onMounted(() => {
    measure()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll, { passive: true })
  })

  onUnmounted(() => {
    cancelAnimationFrame(raf)
    window.removeEventListener('scroll', onScroll)
    window.removeEventListener('resize', onScroll)
  })

  return { progress }
}
