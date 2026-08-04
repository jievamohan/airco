<template>
  <section
    id="projecten"
    ref="root"
    class="projects"
    :class="{ 'projects--reduced': reduced }"
  >
    <div class="projects__head container">
      <h2 class="projects__title">Uitgevoerde projecten</h2>
    </div>
    <div class="projects__strip" role="list">
      <article
        v-for="project in projects"
        :key="project.id"
        class="projects__item"
        role="listitem"
      >
        <div class="projects__media">
          <img
            :src="project.image"
            :alt="`${project.type} in ${project.place}`"
            width="1024"
            height="1536"
            class="projects__img"
            loading="lazy"
            decoding="async"
          />
        </div>
        <div class="projects__meta">
          <p>{{ project.place }}</p>
          <p>{{ project.type }}</p>
          <p>{{ project.area }}</p>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
const projects = [
  {
    id: 1,
    place: 'Utrecht',
    type: 'Split-unit woonkamer',
    area: '72 m²',
    image: '/media/project-utrecht.webp',
  },
  {
    id: 2,
    place: 'Amersfoort',
    type: 'Multi-split',
    area: '110 m²',
    image: '/media/project-amersfoort.webp',
  },
  {
    id: 3,
    place: 'Hilversum',
    type: 'Slaapkamer + woonkamer',
    area: '85 m²',
    image: '/media/project-hilversum.webp',
  },
  {
    id: 4,
    place: 'Amsterdam',
    type: 'Appartement',
    area: '64 m²',
    image: '/media/project-amsterdam.webp',
  },
  {
    id: 5,
    place: 'Haarlem',
    type: 'Nieuwbouw',
    area: '98 m²',
    image: '/media/project-haarlem.webp',
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

    gsap.set(q('.projects__title'), { autoAlpha: 0, y: 28 })
    gsap.set(q('.projects__item'), { autoAlpha: 0, y: 36 })
    gsap.set(q('.projects__img'), { scale: 1.1 })

    gsap.to(q('.projects__title'), {
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

    gsap.to(q('.projects__item'), {
      autoAlpha: 1,
      y: 0,
      duration: 0.9,
      stagger: 0.1,
      ease: 'power3.out',
      scrollTrigger: {
        trigger: q('.projects__strip')[0] as Element,
        start: 'top 82%',
        once: true,
      },
    })

    gsap.to(q('.projects__img'), {
      scale: 1,
      duration: 1.15,
      stagger: 0.1,
      ease: 'power3.out',
      scrollTrigger: {
        trigger: q('.projects__strip')[0] as Element,
        start: 'top 82%',
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
.projects {
  padding-block: calc(var(--space) * 14) calc(var(--space) * 16);
}

.projects__title {
  margin: 0 0 calc(var(--space) * 6);
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 600;
  letter-spacing: -0.03em;
}

.projects__strip {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(240px, 1fr);
  gap: 2px;
  overflow-x: auto;
  scrollbar-width: none;
}

.projects__strip::-webkit-scrollbar {
  display: none;
}

.projects__item {
  position: relative;
  min-height: 320px;
}

.projects__media {
  height: 100%;
  min-height: 320px;
  overflow: hidden;
  background: #ececec;
}

.projects__img {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 320px;
  object-fit: cover;
  object-position: center;
  transform-origin: center center;
  will-change: transform;
}

.projects__meta {
  position: absolute;
  left: 20px;
  bottom: 20px;
  opacity: 0;
  transform: translateY(6px);
  transition: opacity 0.25s ease, transform 0.25s ease;
  color: #fff;
  font-size: 13px;
  line-height: 1.45;
  text-shadow: 0 1px 12px rgb(0 0 0 / 0.45);
}

.projects__meta p {
  margin: 0;
}

.projects__item:hover .projects__meta,
.projects__item:focus-within .projects__meta {
  opacity: 1;
  transform: translateY(0);
}

@media (max-width: 767px) {
  .projects__meta {
    opacity: 1;
    transform: none;
  }
}

.projects--reduced .projects__title,
.projects--reduced .projects__item,
.projects--reduced .projects__img {
  opacity: 1 !important;
  visibility: visible !important;
  transform: none !important;
}
</style>
