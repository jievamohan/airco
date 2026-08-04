<template>
  <section
    id="reviews"
    ref="root"
    class="reviews"
    :class="{ 'reviews--reduced': reduced }"
  >
    <div class="reviews__inner container">
      <h2 class="reviews__title">Wat klanten zeggen</h2>
      <div class="reviews__grid">
        <blockquote v-for="review in reviews" :key="review.id" class="reviews__quote">
          <p>“{{ review.text }}”</p>
          <footer>{{ review.name }} · {{ review.place }}</footer>
        </blockquote>
      </div>
      <div class="reviews__trust" aria-label="Beoordelingen">
        <span>Google 4.9</span>
        <span>InstallQ 9.7</span>
        <span>Trustpilot 4.2</span>
        <span>STEK 5.0</span>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const reviews = [
  {
    id: 1,
    text: 'Stil, strak geplaatst, en in de winter merkbaar goedkoper dan de ketel.',
    name: 'Marieke',
    place: 'Utrecht',
  },
  {
    id: 2,
    text: 'Alles netjes uitgelegd. De installatie voelde precies en rustig.',
    name: 'Thomas',
    place: 'Amersfoort',
  },
  {
    id: 3,
    text: 'Eindelijk koel én warm zonder gedoe. Precies wat we zochten.',
    name: 'Sanne',
    place: 'Haarlem',
  },
]

const root = ref<HTMLElement | null>(null)
const reduced = usePrefersReducedMotion()

type GsapCtx = { revert: () => void }
let ctx: GsapCtx | null = null

const teardown = () => {
  ctx?.revert()
  ctx = null
}

const setup = async () => {
  teardown()
  const el = root.value
  if (!el || reduced.value) return

  const [{ gsap }, { ScrollTrigger }] = await Promise.all([
    import('gsap'),
    import('gsap/ScrollTrigger'),
  ])
  gsap.registerPlugin(ScrollTrigger)

  ctx = gsap.context(() => {
    const q = gsap.utils.selector(el)

    gsap.set(q('.reviews__title'), { autoAlpha: 0, y: 28 })
    gsap.set(q('.reviews__quote'), { autoAlpha: 0, y: 32 })
    gsap.set(q('.reviews__trust'), { autoAlpha: 0, y: 16 })

    gsap.to(q('.reviews__title'), {
      autoAlpha: 1,
      y: 0,
      duration: 0.85,
      ease: 'power3.out',
      scrollTrigger: {
        trigger: el,
        start: 'top 78%',
        once: true,
      },
    })

    gsap.to(q('.reviews__quote'), {
      autoAlpha: 1,
      y: 0,
      duration: 0.9,
      stagger: 0.12,
      ease: 'power3.out',
      scrollTrigger: {
        trigger: q('.reviews__grid')[0] as Element,
        start: 'top 82%',
        once: true,
      },
    })

    gsap.to(q('.reviews__trust'), {
      autoAlpha: 1,
      y: 0,
      duration: 0.75,
      ease: 'power3.out',
      scrollTrigger: {
        trigger: q('.reviews__trust')[0] as Element,
        start: 'top 90%',
        once: true,
      },
    })
  }, el)

  ScrollTrigger.refresh()
}

onMounted(() => {
  void setup()
})

watch(reduced, () => {
  void setup()
})

onUnmounted(() => {
  teardown()
})
</script>

<style scoped>
.reviews {
  padding-block: calc(var(--space) * 16) calc(var(--space) * 18);
}

.reviews__title {
  margin: 0 0 calc(var(--space) * 10);
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 600;
  letter-spacing: -0.03em;
}

.reviews__grid {
  display: grid;
  gap: calc(var(--space) * 8);
}

.reviews__quote {
  margin: 0;
}

.reviews__quote p {
  margin: 0 0 16px;
  font-size: clamp(22px, 2.4vw, 30px);
  font-weight: 400;
  letter-spacing: -0.02em;
  line-height: 1.35;
  color: var(--color-ink);
}

.reviews__quote footer {
  color: var(--color-ink-soft);
  font-size: 14px;
}

.reviews__trust {
  display: flex;
  flex-wrap: wrap;
  gap: 28px;
  margin-top: calc(var(--space) * 12);
  color: var(--color-ink-soft);
  font-size: 13px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

@media (min-width: 900px) {
  .reviews__grid {
    grid-template-columns: repeat(3, 1fr);
    gap: calc(var(--space) * 6);
  }
}

.reviews--reduced .reviews__title,
.reviews--reduced .reviews__quote,
.reviews--reduced .reviews__trust {
  opacity: 1 !important;
  visibility: visible !important;
  transform: none !important;
}
</style>
