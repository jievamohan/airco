<template>
  <section
    id="top"
    ref="container"
    class="hero"
    :class="{ 'hero--reduced': reduced }"
    data-testid="hero-handoff"
    :data-track-progress="trackProgress.toFixed(3)"
    :data-scroll-phase="scrollPhase"
  >
    <div class="hero__pin" :style="pinStyle">
      <div class="hero__stage">
        <img
          class="hero__bg"
          src="/media/hero.png?v=no-filter"
          alt=""
          width="1536"
          height="1024"
          aria-hidden="true"
        />

        <div class="hero__copy">
          <h1 class="hero__title">
            Perfect klimaat.<br />
            Elk <span class="gradient-season">seizoen</span>.
          </h1>
          <p class="hero__lede">
            Koelen in de zomer.<br />
            Verwarmen in de winter.<br />
            Comfortabel. Duurzaam.<br />
            Voordeliger dan aardgas.
          </p>
          <div class="hero__actions">
            <a href="#offerte" class="btn-ghost">Vrijblijvende offerte</a>
            <a href="#aircos" class="btn-text">Bekijk voordelen <span aria-hidden="true">›</span></a>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { clamp01, easeExit } from '../../composables/mapScrollPhases'

const container = ref<HTMLElement | null>(null)
const reduced = usePrefersReducedMotion()
const trackProgress = ref(0)

const scrollPhase = computed(() => {
  if (reduced.value) return 'scrub'
  if (trackProgress.value <= 0.02) return 'intro'
  if (trackProgress.value >= 0.98) return 'outro'
  return 'outro'
})

/**
 * Exit progress while the hero leaves the viewport (natural document scroll).
 * 0 = fully in view at top; 1 = fully scrolled above the viewport.
 */
const readScrollExitProgress = (el: HTMLElement | null) => {
  if (!el || typeof window === 'undefined') return 0
  const rect = el.getBoundingClientRect()
  const span = Math.max(rect.height, 1)
  return clamp01(-rect.top / span)
}

/** Fade across the full exit so opacity tracks continuous upward motion. */
const exitFade = computed(() => easeExit(trackProgress.value))

const pinStyle = computed(() => {
  if (reduced.value || trackProgress.value <= 0) return undefined
  const fade = exitFade.value
  return {
    opacity: String(1 - fade),
    pointerEvents: fade >= 0.98 ? ('none' as const) : undefined,
  }
})

let raf = 0
let ticking = false

const measure = () => {
  trackProgress.value = readScrollExitProgress(container.value)
}

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
  if (!reduced.value) {
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll, { passive: true })
  }
})

onUnmounted(() => {
  cancelAnimationFrame(raf)
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('resize', onScroll)
})
</script>

<style scoped>
.hero {
  position: relative;
  /* One viewport — exits via natural scroll (no sticky wipe track) */
  height: 100vh;
  width: 100%;
  background: #fff;
}

.hero--reduced {
  height: auto;
  min-height: 0;
}

.hero__pin {
  position: relative;
  height: 100vh;
  background: #fff;
  overflow: hidden;
  display: flex;
  align-items: stretch;
  will-change: opacity;
  box-sizing: border-box;
}

.hero--reduced .hero__pin {
  height: auto;
  min-height: clamp(520px, 70vh, 760px);
}

.hero__stage {
  position: relative;
  isolation: isolate;
  width: 100%;
  max-width: 1440px;
  margin-inline: auto;
  min-height: clamp(520px, 70vh, 760px);
  height: 100%;
  display: flex;
  align-items: center;
  padding-left: clamp(24px, 5.5vw, 80px);
  padding-right: clamp(24px, 5.5vw, 80px);
  padding-block: calc(var(--space) * 6) calc(var(--space) * 10);
  box-sizing: border-box;
}

/* Afbeelding = achtergrond van de stage (niet een tweede kolom) */
.hero__bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: right center;
  pointer-events: none;
  user-select: none;
  /* Linker kant van de visual zacht naar wit */
  -webkit-mask-image: linear-gradient(
    to right,
    transparent 0%,
    rgba(0, 0, 0, 0.35) 18%,
    #000 32%,
    #000 100%
  );
  mask-image: linear-gradient(
    to right,
    transparent 0%,
    rgba(0, 0, 0, 0.35) 18%,
    #000 32%,
    #000 100%
  );
}

.hero__copy {
  position: relative;
  z-index: 1;
  max-width: 32rem;
}

.hero__title {
  margin: 0 0 calc(var(--space) * 4);
  font-size: clamp(42px, 5.4vw, 72px);
  font-weight: 600;
  letter-spacing: -0.04em;
  line-height: 1.05;
}

.hero__lede {
  margin: 0 0 calc(var(--space) * 5);
  color: var(--color-ink-muted);
  font-size: clamp(16px, 1.45vw, 20px);
  font-weight: 400;
  line-height: 1.55;
}

.hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

@media (max-width: 959px) {
  .hero--reduced {
    height: auto;
  }

  .hero__stage {
    min-height: clamp(420px, 78vw, 560px);
    align-items: flex-end;
    padding-block: calc(var(--space) * 4) calc(var(--space) * 6);
  }

  .hero--reduced .hero__pin {
    min-height: clamp(420px, 78vw, 560px);
  }

  .hero__bg {
    object-position: 70% center;
    -webkit-mask-image: linear-gradient(
      to bottom,
      transparent 0%,
      rgba(0, 0, 0, 0.45) 12%,
      #000 28%,
      #000 100%
    );
    mask-image: linear-gradient(
      to bottom,
      transparent 0%,
      rgba(0, 0, 0, 0.45) 12%,
      #000 28%,
      #000 100%
    );
  }

  .hero__copy {
    max-width: none;
  }
}
</style>
