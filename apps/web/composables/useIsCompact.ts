/**
 * Vertelt of het scherm te smal is voor een tabel.
 *
 * Het dashboard rendert op een telefoon kaarten in plaats van tabelrijen. Dat
 * is meer dan een kwestie van opmaak — de kaart laat andere dingen zien en in
 * een andere volgorde — dus dat kan CSS alleen niet doen. De dashboardroutes
 * draaien client-side (`ssr: false`), dus we mogen `matchMedia` meteen lezen;
 * zo staat de juiste weergave er al bij de eerste frame.
 */
export function useIsCompact(query = '(max-width: 720px)') {
  const compact = ref(import.meta.client ? window.matchMedia(query).matches : false)

  let media: MediaQueryList | null = null
  const sync = () => {
    if (media) compact.value = media.matches
  }

  onMounted(() => {
    media = window.matchMedia(query)
    sync()
    media.addEventListener('change', sync)
  })

  onBeforeUnmount(() => media?.removeEventListener('change', sync))

  return compact
}
