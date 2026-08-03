export function usePrefersReducedMotion() {
  const reduced = ref(false)

  onMounted(() => {
    const mq = window.matchMedia('(prefers-reduced-motion: reduce)')
    const apply = () => {
      reduced.value = mq.matches
    }
    apply()
    mq.addEventListener('change', apply)
    onUnmounted(() => mq.removeEventListener('change', apply))
  })

  return reduced
}
