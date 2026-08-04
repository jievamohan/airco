import type { Ref } from 'vue'
import {
  HEATING_FIGURES,
  type HeatingScrollPhase,
} from './heatingFigures'

type GsapCtx = { revert: () => void }

/** Circumference for r=54 viewBox circle (2πr). */
const RING_LEN = 2 * Math.PI * 54

/**
 * GSAP ScrollTrigger scrub for the heating story.
 * CSS sticky pin lives in the component; this only drives opacity/Y/scale.
 * Mobile + prefers-reduced-motion: no ScrollTrigger (static stacked DOM).
 */
export function useHeatingStoryScroll(options: {
  container: Ref<HTMLElement | null>
  enabled: Ref<boolean>
  percentDisplay: Ref<string>
}) {
  const phase = ref<HeatingScrollPhase>('same-heat')
  const trackProgress = ref(0)

  let ctx: GsapCtx | null = null
  let ScrollTriggerRef: { refresh: () => void } | null = null

  const teardown = () => {
    ctx?.revert()
    ctx = null
  }

  const setup = async () => {
    teardown()
    const root = options.container.value
    if (!root || !options.enabled.value) {
      phase.value = 'reduced'
      trackProgress.value = 0
      options.percentDisplay.value = HEATING_FIGURES.savePercent
      return
    }

    const [{ gsap }, { ScrollTrigger }] = await Promise.all([
      import('gsap'),
      import('gsap/ScrollTrigger'),
    ])
    gsap.registerPlugin(ScrollTrigger)
    ScrollTriggerRef = ScrollTrigger

    const q = gsap.utils.selector(root)
    const pin = q('.heat__pin')[0] as HTMLElement | undefined
    if (!pin) return

    const counter = { n: 0 }

    ctx = gsap.context(() => {
      gsap.set(q('.heat__s1'), { autoAlpha: 1, y: 0, scale: 1 })
      gsap.set(q('.heat__products'), { autoAlpha: 1, y: 0, scale: 1 })
      gsap.set(q('.heat__s2-label'), { autoAlpha: 0, y: 12 })
      gsap.set(q('.heat__s2-price'), { autoAlpha: 0, y: 12 })
      gsap.set(q('.heat__warmte'), { autoAlpha: 0, scale: 0.98 })
      gsap.set(q('.heat__bars'), { autoAlpha: 0, y: 10 })
      gsap.set(q('.heat__s2-line'), { autoAlpha: 0, y: 10 })
      gsap.set(q('.heat__s3-main'), { autoAlpha: 0, y: 16, scale: 0.98 })
      gsap.set(q('.heat__footnotes'), { autoAlpha: 0 })
      gsap.set(q('.heat__bar-fill--airco'), { scaleX: 0, transformOrigin: 'left center' })
      gsap.set(q('.heat__bar-fill--cv'), { scaleX: 0, transformOrigin: 'left center' })
      gsap.set(q('.heat__ring-progress'), {
        strokeDasharray: RING_LEN,
        strokeDashoffset: RING_LEN,
        transformOrigin: '50% 50%',
        rotation: -90,
      })
      options.percentDisplay.value = '0%'
      counter.n = 0

      const tl = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
          trigger: root,
          start: 'top top',
          end: 'bottom bottom',
          scrub: true,
          onUpdate: (self) => {
            trackProgress.value = self.progress
            const p = self.progress
            if (p < 0.28) phase.value = 'same-heat'
            else if (p < 0.72) phase.value = 'price'
            else phase.value = 'conclusion'
          },
        },
      })

      tl.fromTo(
        q('.heat__s1'),
        { autoAlpha: 0.85, y: 14, scale: 0.98 },
        { autoAlpha: 1, y: 0, scale: 1, ease: 'power3.out', duration: 0.12 },
        0,
      )

      tl.to(q('.heat__s1'), { autoAlpha: 0, y: -12, ease: 'power3.out', duration: 0.1 }, 0.22)
      tl.to(q('.heat__s2-label'), { autoAlpha: 1, y: 0, ease: 'power3.out', duration: 0.08 }, 0.28)
      tl.to(q('.heat__s2-price'), { autoAlpha: 1, y: 0, ease: 'expo.out', duration: 0.1 }, 0.34)
      tl.to(q('.heat__warmte'), { autoAlpha: 1, scale: 1, ease: 'power3.out', duration: 0.08 }, 0.42)
      tl.to(q('.heat__bars'), { autoAlpha: 1, y: 0, ease: 'power3.out', duration: 0.08 }, 0.5)
      tl.to(
        q('.heat__bar-fill--airco'),
        { scaleX: 1, ease: 'power3.out', duration: 0.1 },
        0.5,
      )
      tl.to(
        q('.heat__bar-fill--cv'),
        { scaleX: 1, ease: 'power3.out', duration: 0.12 },
        0.52,
      )
      tl.to(q('.heat__s2-line'), { autoAlpha: 1, y: 0, ease: 'expo.out', duration: 0.08 }, 0.6)

      tl.to(
        [
          q('.heat__products'),
          q('.heat__s2-label'),
          q('.heat__s2-price'),
          q('.heat__warmte'),
          q('.heat__bars'),
          q('.heat__s2-line'),
        ],
        { autoAlpha: 0, y: -10, ease: 'power3.out', duration: 0.12 },
        0.7,
      )

      // Scene 3 enter: graph at 0%, empty ring — hold, then fill
      tl.set(counter, { n: 0 }, 0.76)
      tl.call(() => {
        options.percentDisplay.value = '0%'
      }, undefined, 0.76)
      tl.set(
        q('.heat__ring-progress'),
        { strokeDashoffset: RING_LEN },
        0.76,
      )
      tl.to(
        q('.heat__s3-main'),
        { autoAlpha: 1, y: 0, scale: 1, ease: 'expo.out', duration: 0.08 },
        0.78,
      )
      tl.to(
        q('.heat__footnotes'),
        { autoAlpha: 1, ease: 'power3.out', duration: 0.06 },
        0.8,
      )

      // Smooth fill 0 → 59 after graph is visible
      tl.to(
        counter,
        {
          n: HEATING_FIGURES.savePercentValue,
          duration: 0.14,
          ease: 'power2.inOut',
          onUpdate: () => {
            options.percentDisplay.value = `${Math.round(counter.n)}%`
          },
        },
        0.86,
      )
      tl.to(
        q('.heat__ring-progress'),
        {
          strokeDashoffset: RING_LEN * (1 - HEATING_FIGURES.savePercentValue / 100),
          ease: 'power2.inOut',
          duration: 0.14,
        },
        0.86,
      )

      tl.to(
        q('.heat__footnotes'),
        { autoAlpha: 0, ease: 'power3.out', duration: 0.06 },
        0.96,
      )
    }, root)

    ScrollTrigger.refresh()
  }

  onMounted(() => {
    void setup()
  })

  watch(
    () => [options.enabled.value, options.container.value] as const,
    () => {
      void setup()
    },
  )

  onUnmounted(() => {
    teardown()
    ScrollTriggerRef?.refresh()
  })

  return {
    phase,
    trackProgress,
  }
}
