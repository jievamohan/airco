<template>
  <section
    id="verwarmen"
    ref="container"
    class="heat"
    :class="{
      'heat--mobile': isMobile,
      'heat--reduced': staticStory,
    }"
    data-testid="heating-story"
    :data-scroll-phase="displayPhase"
    :data-track-progress="trackProgress.toFixed(3)"
    :style="trackStyle"
  >
    <div class="heat__pin">
      <div class="heat__stage">
        <div class="heat__s1">
          <h2 class="heat__title">{{ HEATING_COPY.s1Title }}</h2>
          <p class="heat__sub">{{ HEATING_COPY.s1Sub }}</p>
        </div>

        <div class="heat__products">
          <figure class="heat__product">
            <picture>
              <source :srcset="aircoWebp" type="image/webp" />
              <img
                :src="aircoPng"
                alt="Airco binnenunit"
                width="680"
                height="265"
                class="heat__img heat__img--airco"
                loading="lazy"
                decoding="async"
              />
            </picture>
            <div class="heat__meta">
              <p class="heat__s2-label">{{ HEATING_FIGURES.aircoLabel }}</p>
              <p class="heat__s2-price">
                <span class="heat__amount">{{ HEATING_FIGURES.aircoPrice }}</span>
                <span class="heat__unit">{{ HEATING_FIGURES.perKwh }}</span>
              </p>
              <div class="heat__warmte" aria-hidden="true" />
              <div
                class="heat__bars"
                :style="{ '--heat-bar-ratio': String(HEATING_BAR_RATIO) }"
                :data-bar-ratio="HEATING_BAR_RATIO"
              >
                <div class="heat__bar">
                  <div class="heat__bar-fill heat__bar-fill--airco" />
                </div>
              </div>
            </div>
          </figure>

          <figure class="heat__product">
            <picture>
              <source :srcset="cvWebp" type="image/webp" />
              <img
                :src="cvPng"
                alt="CV-ketel"
                width="370"
                height="644"
                class="heat__img heat__img--cv"
                loading="lazy"
                decoding="async"
              />
            </picture>
            <div class="heat__meta">
              <p class="heat__s2-label">{{ HEATING_FIGURES.cvLabel }}</p>
              <p class="heat__s2-price">
                <span class="heat__amount">{{ HEATING_FIGURES.cvPrice }}</span>
                <span class="heat__unit">{{ HEATING_FIGURES.perKwh }}</span>
              </p>
              <div class="heat__warmte" aria-hidden="true" />
              <div
                class="heat__bars"
                :style="{ '--heat-bar-ratio': String(HEATING_BAR_RATIO) }"
                :data-bar-ratio="HEATING_BAR_RATIO"
              >
                <div class="heat__bar heat__bar--cv">
                  <div class="heat__bar-fill heat__bar-fill--cv" />
                </div>
              </div>
            </div>
          </figure>
        </div>

        <p class="heat__s2-line">{{ HEATING_COPY.s2Line }}</p>

        <div class="heat__s3">
          <div class="heat__s3-main">
            <div class="heat__pct-wrap">
              <svg
                class="heat__ring"
                viewBox="0 0 120 120"
                width="120"
                height="120"
                aria-hidden="true"
              >
                <circle class="heat__ring-track" cx="60" cy="60" r="54" fill="none" />
                <circle class="heat__ring-progress" cx="60" cy="60" r="54" fill="none" />
              </svg>
              <div class="heat__pct-inner">
                <p class="heat__pct-prefix">{{ HEATING_COPY.s3Prefix }}</p>
                <p class="heat__pct">{{ percentDisplay }}</p>
                <p class="heat__pct-sub">{{ HEATING_COPY.s3Save }}</p>
              </div>
            </div>
            <p class="heat__same">{{ HEATING_COPY.s3Same }}</p>
          </div>
        </div>
      </div>

      <aside class="heat__footnotes">
        <p class="heat__assumptions-line">{{ HEATING_ASSUMPTIONS_LINE }}</p>
        <p class="heat__disclaimer">{{ HEATING_COPY.disclaimer }}</p>
      </aside>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  HEATING_ASSUMPTIONS_LINE,
  HEATING_BAR_RATIO,
  HEATING_COPY,
  HEATING_FIGURES,
  HEATING_TRACK_VH,
  type HeatingScrollPhase,
} from '../../composables/heatingFigures'

const aircoWebp = '/media/airco-indoor-unit.webp'
const aircoPng = '/media/airco-indoor-unit.png'
const cvWebp = '/media/cv-ketel.webp'
const cvPng = '/media/cv-ketel.png'

const container = ref<HTMLElement | null>(null)
const reduced = usePrefersReducedMotion()
const isMobile = ref(false)
const percentDisplay = ref('0%')

onMounted(() => {
  const mq = window.matchMedia('(max-width: 767px)')
  const apply = () => {
    isMobile.value = mq.matches
  }
  apply()
  mq.addEventListener('change', apply)
  onUnmounted(() => mq.removeEventListener('change', apply))
})

const staticStory = computed(() => reduced.value || isMobile.value)
const enabled = computed(() => !staticStory.value)

const { phase, trackProgress } = useHeatingStoryScroll({
  container,
  enabled,
  percentDisplay,
})
const displayPhase = computed<HeatingScrollPhase>(() =>
  staticStory.value ? 'reduced' : phase.value,
)

const trackStyle = computed(() => {
  if (staticStory.value) return undefined
  return { minHeight: `${HEATING_TRACK_VH}vh` }
})
</script>

<style scoped>
.heat {
  position: relative;
  z-index: 1;
  margin-top: calc(-1 * 40vh);
  background: #fff;
  color: #0a0a0a;
}

@media (max-width: 767px) {
  .heat {
    margin-top: calc(-1 * 20vh);
  }
}

.heat__pin {
  position: sticky;
  top: 0;
  height: 100vh;
  height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.heat__stage {
  width: min(920px, calc(100% - 48px));
  margin-inline: auto;
  text-align: center;
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: clamp(28px, 4vh, 48px);
  min-height: min(78vh, 640px);
}

.heat__s1 {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  z-index: 2;
}

.heat__title {
  margin: 0;
  font-size: clamp(36px, 5.5vw, 64px);
  font-weight: 600;
  letter-spacing: -0.045em;
  line-height: 1.05;
}

.heat__sub {
  margin: 0;
  font-size: clamp(18px, 2.2vw, 28px);
  font-weight: 400;
  letter-spacing: -0.02em;
  color: #3a3a3a;
  line-height: 1.25;
}

.heat__products {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: clamp(24px, 6vw, 80px);
  align-items: end;
  width: 100%;
  max-width: 720px;
  z-index: 1;
}

.heat__product {
  margin: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  position: relative;
}

.heat__img {
  display: block;
  width: auto;
  height: auto;
  max-height: min(26vh, 200px);
  object-fit: contain;
}

.heat__img--airco {
  max-width: min(100%, 340px);
}

.heat__img--cv {
  max-width: min(100%, 150px);
}

.heat__meta {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: 100%;
}

.heat__s2-label {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  letter-spacing: -0.01em;
}

.heat__s2-price {
  margin: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.heat__amount {
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 600;
  letter-spacing: -0.04em;
  line-height: 1.1;
}

.heat__unit {
  font-size: 13px;
  color: #6a6a6a;
}

.heat__warmte {
  width: 48px;
  height: 48px;
  background: #f0e6dc;
  border-radius: 2px;
}

.heat__bars {
  width: 100%;
  max-width: 200px;
  display: flex;
  justify-content: center;
}

.heat__bar {
  width: calc(100% / var(--heat-bar-ratio, 2.43));
  height: 6px;
  background: #ececec;
  border-radius: 1px;
  overflow: hidden;
}

.heat__bar--cv {
  width: 100%;
}

.heat__bar-fill {
  height: 100%;
  width: 100%;
  background: #0a0a0a;
  transform: scaleX(0);
  transform-origin: left center;
}

.heat__s2-line {
  margin: 0;
  font-size: clamp(18px, 2vw, 24px);
  font-weight: 500;
  letter-spacing: -0.02em;
  z-index: 2;
}

.heat__s3 {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 3;
}

.heat__s3-main {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 24px;
}

.heat__pct-wrap {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: min(280px, 56vw);
  height: min(280px, 56vw);
}

.heat__ring {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  overflow: visible;
}

.heat__ring-track {
  stroke: #ececec;
  stroke-width: 1.25;
}

.heat__ring-progress {
  stroke: #0a0a0a;
  stroke-width: 1.25;
  stroke-linecap: round;
}

.heat__pct-inner {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.heat__pct-prefix {
  margin: 0;
  font-size: clamp(13px, 1.4vw, 16px);
  font-weight: 400;
  letter-spacing: 0.04em;
  color: #6a6a6a;
}

.heat__pct {
  margin: 0;
  font-size: clamp(56px, 11vw, 104px);
  font-weight: 600;
  letter-spacing: -0.06em;
  line-height: 0.95;
  font-variant-numeric: tabular-nums;
}

.heat__pct-sub {
  margin: 4px 0 0;
  font-size: clamp(15px, 1.8vw, 20px);
  font-weight: 400;
  color: #3a3a3a;
}

.heat__same {
  margin: 20px 0 0;
  font-size: clamp(16px, 1.8vw, 20px);
}

.heat__footnotes {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 5;
  box-sizing: border-box;
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 12px 20px calc(12px + env(safe-area-inset-bottom, 0px));
  text-align: center;
  background: linear-gradient(to top, #fff 70%, rgb(255 255 255 / 0));
}

.heat__assumptions-line {
  margin: 0;
  font-size: 12px;
  color: #6a6a6a;
  line-height: 1.4;
}

.heat__disclaimer {
  margin: 6px 0 0;
  font-size: 11px;
  line-height: 1.4;
  color: #8a8a8a;
}

/* Desktop animated: meta + s2-line occupy no Scene 1 layout (GSAP reveals) */
.heat:not(.heat--mobile):not(.heat--reduced) .heat__meta {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 8px);
}

.heat:not(.heat--mobile):not(.heat--reduced) .heat__s2-line {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 8%;
}

.heat--mobile,
.heat--reduced {
  min-height: 0;
  padding-block: calc(var(--space) * 10) calc(var(--space) * 14);
}

.heat--reduced {
  margin-top: 0;
}

.heat--mobile .heat__pin,
.heat--reduced .heat__pin {
  position: relative;
  height: auto;
  overflow: visible;
}

.heat--mobile .heat__stage,
.heat--reduced .heat__stage {
  min-height: 0;
  gap: 32px;
}

.heat--mobile .heat__s1,
.heat--mobile .heat__products,
.heat--mobile .heat__meta,
.heat--mobile .heat__s2-label,
.heat--mobile .heat__s2-price,
.heat--mobile .heat__warmte,
.heat--mobile .heat__bars,
.heat--mobile .heat__s2-line,
.heat--mobile .heat__s3,
.heat--mobile .heat__s3-main,
.heat--mobile .heat__footnotes,
.heat--reduced .heat__s1,
.heat--reduced .heat__products,
.heat--reduced .heat__meta,
.heat--reduced .heat__s2-label,
.heat--reduced .heat__s2-price,
.heat--reduced .heat__warmte,
.heat--reduced .heat__bars,
.heat--reduced .heat__s2-line,
.heat--reduced .heat__s3,
.heat--reduced .heat__s3-main,
.heat--reduced .heat__footnotes {
  opacity: 1 !important;
  transform: none !important;
  visibility: visible !important;
}

.heat--mobile .heat__bar-fill,
.heat--reduced .heat__bar-fill {
  transform: scaleX(1) !important;
}

.heat--mobile .heat__ring-progress,
.heat--reduced .heat__ring-progress {
  stroke-dasharray: 339.292;
  stroke-dashoffset: 139.11; /* 59% filled */
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
}

.heat--mobile .heat__s3,
.heat--reduced .heat__s3 {
  position: relative;
  inset: auto;
  pointer-events: auto;
  padding: 48px 0 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 32px;
}

.heat--mobile .heat__s3-main,
.heat--reduced .heat__s3-main {
  position: relative;
  inset: auto;
}

.heat--mobile .heat__footnotes,
.heat--reduced .heat__footnotes {
  position: relative;
  left: auto;
  bottom: auto;
  background: none;
  padding: 24px 0 0;
}

.heat--mobile .heat__products,
.heat--reduced .heat__products {
  grid-template-columns: 1fr;
  justify-items: center;
  gap: 48px;
}

.heat--mobile .heat__img,
.heat--reduced .heat__img {
  max-height: 180px;
}

@media (min-width: 768px) {
  .heat--reduced {
    margin-top: calc(-1 * 40vh);
  }
}
</style>
