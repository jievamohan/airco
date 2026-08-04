<template>
  <section
    id="certificeringen"
    ref="root"
    class="certs"
    :class="{ 'certs--reduced': reduced }"
  >
    <div class="certs__inner container">
      <h2 class="certs__title">Erkend en gecertificeerd</h2>
      <ul class="certs__grid" role="list">
        <li
          v-for="cert in certifications"
          :key="cert.id"
          class="certs__item"
        >
          <img
            :src="cert.image"
            :alt="cert.name"
            :width="cert.width"
            :height="cert.height"
            class="certs__logo"
            :class="{ 'certs__logo--tall': cert.tall }"
            loading="lazy"
            decoding="async"
          />
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup lang="ts">
const certifications = [
  {
    id: 'nen-1010',
    name: 'NEN 1010',
    image: '/media/certs/cert-nen-1010.webp',
    width: 136,
    height: 51,
    tall: false,
  },
  {
    id: 'nen-3140',
    name: 'NEN 3140',
    image: '/media/certs/cert-nen-3140.webp',
    width: 136,
    height: 51,
    tall: false,
  },
  {
    id: 'vca',
    name: 'VCA',
    image: '/media/certs/cert-vca.webp',
    width: 136,
    height: 51,
    tall: false,
  },
  {
    id: 'scope-12',
    name: 'Scope 12',
    image: '/media/certs/cert-scope-12.webp',
    width: 136,
    height: 51,
    tall: false,
  },
  {
    id: 'iso-9001',
    name: 'Kiwa ISO 9001',
    image: '/media/certs/cert-iso-9001.webp',
    width: 167,
    height: 320,
    tall: true,
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

    gsap.set(q('.certs__title'), { autoAlpha: 0, y: 28 })
    gsap.set(q('.certs__item'), { autoAlpha: 0, y: 24, scale: 0.96 })

    gsap.to(q('.certs__title'), {
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

    gsap.to(q('.certs__item'), {
      autoAlpha: 1,
      y: 0,
      scale: 1,
      duration: 0.8,
      stagger: 0.08,
      ease: 'power3.out',
      scrollTrigger: {
        trigger: q('.certs__grid')[0] as Element,
        start: 'top 85%',
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
.certs {
  padding-block: calc(var(--space) * 14) calc(var(--space) * 16);
  border-top: 1px solid rgb(0 0 0 / 0.06);
}

.certs__title {
  margin: 0 0 calc(var(--space) * 10);
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 600;
  letter-spacing: -0.03em;
}

.certs__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: calc(var(--space) * 6) calc(var(--space) * 5);
  margin: 0;
  padding: 0;
  list-style: none;
  align-items: center;
  justify-items: center;
}

.certs__item {
  display: grid;
  place-items: center;
  width: 100%;
  min-height: 72px;
}

.certs__logo {
  display: block;
  width: auto;
  max-width: 140px;
  height: 44px;
  object-fit: contain;
  object-position: center;
}

.certs__logo--tall {
  height: 88px;
  max-width: 56px;
}

@media (max-width: 699px) {
  .certs__item:last-child:nth-child(odd) {
    grid-column: 1 / -1;
  }
}

@media (min-width: 700px) {
  .certs__grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: calc(var(--space) * 4);
  }

  .certs__logo {
    height: 48px;
    max-width: 160px;
  }

  .certs__logo--tall {
    height: 96px;
    max-width: 60px;
  }
}

.certs--reduced .certs__title,
.certs--reduced .certs__item {
  opacity: 1 !important;
  visibility: visible !important;
  transform: none !important;
}
</style>
