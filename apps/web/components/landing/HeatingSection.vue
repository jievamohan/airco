<template>
  <section
    id="verwarmen"
    ref="container"
    class="heat"
    :class="{
      'heat--mobile': isMobile,
      'heat--reduced': reduced,
      'heat--exiting': exitProgress > 0 && !reduced,
      'heat--entering': isEntering,
    }"
    data-testid="heating-story"
    :data-track-progress="trackProgress.toFixed(3)"
    :data-scrub-progress="scrubProgress.toFixed(3)"
    :data-scroll-phase="displayPhase"
  >
    <!-- Reduced motion: stacked editorial story -->
    <div v-if="reduced" class="heat__reduced container">
      <h2 class="heat__reduced-title">Warmte is warmte.</h2>
      <p class="heat__reduced-sub">
        Maar niet elke manier om haar op te wekken kost hetzelfde.
      </p>

      <figure class="heat__reduced-figure">
        <img
          :src="aircoSrc"
          alt="Witte airco-binnenunit"
          width="680"
          height="265"
          loading="eager"
          decoding="async"
        />
        <figcaption>
          <p><strong>1 kWh stroom</strong> wordt <strong class="heat__blue">4 kWh warmte</strong> (COP 4).</p>
          <p>
            Een airco verplaatst warmte. Daardoor kan 1 kWh elektriciteit ongeveer 4 kWh warmte
            leveren.
          </p>
          <p class="heat__figure-xl heat__blue">{{ figures.aircoPerKwh }}</p>
          <p class="heat__muted">per kWh warmte · {{ figures.powerPrice }} ÷ 4</p>
        </figcaption>
      </figure>

      <figure class="heat__reduced-figure">
        <img
          :src="boilerSrc"
          alt="Witte cv-ketel"
          width="360"
          height="630"
          loading="lazy"
          decoding="async"
        />
        <figcaption>
          <p>
            <strong>1 m³ aardgas</strong> bevat afgerond <strong>10 kWh</strong> energie.
            Bij <strong class="heat__orange">{{ figures.boilerEfficiency }}</strong> rendement:
            <strong class="heat__orange">{{ figures.boilerHeat }}</strong>.
          </p>
          <p class="heat__figure-xl heat__orange">{{ figures.boilerPerKwh }}</p>
          <p class="heat__muted">per kWh warmte · {{ figures.gasPrice }} ÷ 8,07</p>
        </figcaption>
      </figure>

      <div class="heat__reduced-compare" aria-label="Kostenvergelijking per kWh warmte">
        <p class="heat__muted">Kosten per geleverde kWh warmte</p>
        <div class="heat__bars heat__bars--static">
          <div class="heat__bar-col">
            <p class="heat__bar-price heat__blue">{{ figures.aircoPerKwh }}</p>
            <p class="heat__bar-label">Airco</p>
            <div class="heat__bar heat__bar--blue" style="height: 40%" />
          </div>
          <div class="heat__bar-col">
            <p class="heat__bar-price heat__orange">{{ figures.boilerPerKwh }}</p>
            <p class="heat__bar-label">CV-ketel</p>
            <div class="heat__bar heat__bar--orange" style="height: 97%" />
          </div>
        </div>
      </div>

      <p class="heat__reduced-lead">Dat verschil telt op.</p>
      <p class="heat__figure-hero heat__blue">{{ figures.savingPct }}</p>
      <p class="heat__reduced-goodkoper">goedkoper</p>
      <p class="heat__reduced-body">
        Verwarmen met een airco kan in dit rekenvoorbeeld ongeveer 59% goedkoper zijn per kWh warmte.
      </p>
      <p class="heat__reduced-body heat__muted">
        De praktijk hangt af van de buitentemperatuur, de werkelijke COP, de woning, de installatie en
        het gebruik.
      </p>
      <p class="heat__reduced-range">
        Een realistische bandbreedte is ongeveer {{ figures.rangeLow }} tot {{ figures.rangeHigh }}.
      </p>
      <ol class="heat__notes">
        <li>
          <span class="heat__note-num" aria-hidden="true">01</span>
          <span>De werkelijke COP verandert met temperatuur en belasting.</span>
        </li>
        <li>
          <span class="heat__note-num" aria-hidden="true">02</span>
          <span>Een airco kan gericht alleen de gebruikte ruimte verwarmen.</span>
        </li>
        <li>
          <span class="heat__note-num" aria-hidden="true">03</span>
          <span>Een moderne HR-ketel verkleint het verschil met deze oudere ketel.</span>
        </li>
      </ol>
      <p class="heat__footnote">
        Rekenvoorbeeld met vaste aannames; geen universele garantie. Energieprijzen: stroom
        {{ figures.powerPrice }}/kWh, aardgas {{ figures.gasPrice }}/m³.
      </p>
    </div>

    <!-- Animated sticky stage -->
    <template v-else>
      <div class="heat__pin" :style="pinHandoffStyle">
        <div class="heat__stage">
          <!-- Scene 1: Intro -->
          <div
            class="heat__scene heat__scene--intro"
            :style="layer(intro.opacity)"
            aria-hidden="true"
          >
            <p class="heat__hero" :style="reveal(intro.line1)">Warmte is warmte.</p>
            <p class="heat__sub" :style="reveal(intro.line2)">
              Maar niet elke manier om haar op te wekken kost hetzelfde.
            </p>
          </div>

          <!-- Scene 2: Airco output -->
          <div
            class="heat__scene heat__scene--airco"
            :style="layer(aircoOut.opacity)"
            aria-hidden="true"
          >
            <div class="heat__product heat__product--airco" :style="aircoOut.productStyle">
              <picture>
                <source :srcset="aircoWebp" type="image/webp" />
                <img
                  :src="aircoSrc"
                  alt=""
                  width="680"
                  height="265"
                  decoding="async"
                  fetchpriority="high"
                />
              </picture>
            </div>
            <div class="heat__copy heat__copy--airco">
              <p class="heat__line" :style="reveal(aircoOut.b1)">1 kWh stroom</p>
              <p class="heat__line heat__line--quiet" :style="reveal(aircoOut.b2)">wordt</p>
              <p class="heat__feature" :style="reveal(aircoOut.b3)">
                <span class="heat__blue heat__giant">4</span>
                <span class="heat__feature-rest"> kWh warmte</span>
              </p>
              <p class="heat__cop heat__blue" :style="reveal(aircoOut.b4)">COP 4</p>
              <p class="heat__explain" :style="reveal(aircoOut.b5)">
                Een airco verplaatst warmte. Daardoor kan 1&nbsp;kWh elektriciteit ongeveer
                4&nbsp;kWh warmte leveren.
              </p>
            </div>
          </div>

          <!-- Scene 3: Airco cost -->
          <div
            class="heat__scene heat__scene--center"
            :style="layer(aircoCost.opacity)"
            aria-hidden="true"
          >
            <p
              class="heat__price"
              :class="{ 'heat__price--formula': aircoCost.showResult <= 0.5 && aircoCost.display.includes('÷') }"
              :style="reveal(aircoCost.step1)"
            >
              <span :class="{ heat__blue: aircoCost.showResult > 0.5 }">{{ aircoCost.display }}</span>
            </p>
            <p class="heat__price-sub" :style="reveal(aircoCost.step1)">
              {{ aircoCost.subtitle }}
            </p>
          </div>

          <!-- Scene 4: Boiler output -->
          <div
            class="heat__scene heat__scene--boiler"
            :style="layer(boilerOut.opacity)"
            aria-hidden="true"
          >
            <div class="heat__copy heat__copy--boiler">
              <p class="heat__line" :style="reveal(boilerOut.b1)">1 m³ aardgas</p>
              <p class="heat__line heat__line--quiet" :style="reveal(boilerOut.b2)">
                bevat afgerond
              </p>
              <p class="heat__feature heat__feature--sm" :style="reveal(boilerOut.b3)">
                <span class="heat__giant heat__giant--sm">10</span>
                <span class="heat__feature-rest"> kWh energie</span>
              </p>
              <p class="heat__cop heat__orange" :style="reveal(boilerOut.b4)">80,7% rendement</p>
              <p class="heat__feature heat__feature--sm" :style="reveal(boilerOut.b5)">
                <span class="heat__orange heat__giant heat__giant--md">8,07</span>
                <span class="heat__feature-rest"> kWh warmte</span>
              </p>
              <p class="heat__explain" :style="reveal(boilerOut.b6)">
                Deze specifieke oudere cv-ketel zet daarvan ongeveer 8,07&nbsp;kWh om in bruikbare
                warmte.
              </p>
            </div>
            <div class="heat__product heat__product--boiler" :style="boilerOut.productStyle">
              <picture>
                <source :srcset="boilerWebp" type="image/webp" />
                <img
                  :src="boilerSrc"
                  alt=""
                  width="360"
                  height="630"
                  loading="lazy"
                  decoding="async"
                />
              </picture>
            </div>
          </div>

          <!-- Scene 5: Boiler cost -->
          <div
            class="heat__scene heat__scene--center heat__scene--boiler-cost"
            :style="layer(boilerCost.opacity)"
            aria-hidden="true"
          >
            <p
              class="heat__price"
              :class="{
                'heat__price--formula':
                  boilerCost.showResult <= 0.5 && boilerCost.display.includes('÷'),
              }"
              :style="reveal(boilerCost.step1)"
            >
              <span :class="{ heat__orange: boilerCost.showResult > 0.5 }">{{
                boilerCost.display
              }}</span>
            </p>
            <p class="heat__price-sub" :style="reveal(boilerCost.step1)">
              {{ boilerCost.subtitle }}
            </p>
          </div>

          <!-- Scene 6: Compare -->
          <div
            class="heat__scene heat__scene--compare"
            :style="layer(compare.opacity)"
            aria-hidden="true"
          >
            <p class="heat__compare-sub" :style="reveal(compare.sub)">
              Kosten per geleverde kWh warmte
            </p>
            <div class="heat__compare" :style="reveal(compare.main)">
              <div class="heat__bar-col">
                <p class="heat__bar-price heat__blue">{{ figures.aircoPerKwh }}</p>
                <p class="heat__bar-label">Airco</p>
                <div class="heat__bar-track">
                  <div
                    class="heat__bar heat__bar--blue"
                    :style="{ height: `${compare.aircoH}%` }"
                  />
                </div>
              </div>
              <div class="heat__compare-rule" aria-hidden="true" />
              <div class="heat__bar-col">
                <p class="heat__bar-price heat__orange">{{ figures.boilerPerKwh }}</p>
                <p class="heat__bar-label">CV-ketel</p>
                <div class="heat__bar-track">
                  <div
                    class="heat__bar heat__bar--orange"
                    :style="{ height: `${compare.boilerH}%` }"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Scene 7: Saving -->
          <div
            class="heat__scene heat__scene--saving"
            :style="layer(saving.opacity)"
            aria-hidden="true"
          >
            <p class="heat__lead" :style="reveal(saving.lead)">Dat verschil telt op.</p>
            <p class="heat__hero-pct heat__blue" :style="reveal(saving.pct)">59%</p>
            <p class="heat__goodkoper" :style="reveal(saving.pct)">goedkoper</p>
            <p class="heat__explain heat__explain--wide" :style="reveal(saving.body)">
              Verwarmen met een airco kan in dit rekenvoorbeeld ongeveer 59% goedkoper zijn per kWh
              warmte.
            </p>
          </div>

          <!-- Scene 8: Context -->
          <div
            class="heat__scene heat__scene--context"
            :style="layer(context.opacity)"
            aria-hidden="true"
          >
            <p class="heat__saving-mini heat__blue" :style="reveal(context.mini)">59%</p>
            <p class="heat__explain heat__explain--wide" :style="reveal(context.nuance)">
              De praktijk hangt af van de buitentemperatuur, de werkelijke COP, de woning, de
              installatie en het gebruik.
            </p>
            <p class="heat__range" :style="reveal(context.range)">
              Een realistische bandbreedte is ongeveer 35% tot 60%.
            </p>
            <ol class="heat__notes heat__notes--stage" :style="reveal(context.notes)">
              <li>
                <span class="heat__note-num" aria-hidden="true">01</span>
                <span>De werkelijke COP verandert met temperatuur en belasting.</span>
              </li>
              <li>
                <span class="heat__note-num" aria-hidden="true">02</span>
                <span>Een airco kan gericht alleen de gebruikte ruimte verwarmen.</span>
              </li>
              <li>
                <span class="heat__note-num" aria-hidden="true">03</span>
                <span>Een moderne HR-ketel verkleint het verschil met deze oudere ketel.</span>
              </li>
            </ol>
          </div>
        </div>
      </div>

      <!-- Screenreader / SEO narrative (visual choreography is aria-hidden) -->
      <div class="sr-only">
        <h2>Verwarmen met airco versus cv-ketel — rekenvoorbeeld</h2>
        <p>Warmte is warmte. Maar niet elke manier om haar op te wekken kost hetzelfde.</p>
        <p>
          Met COP 4 levert 1 kWh stroom ongeveer 4 kWh warmte. Bij {{ figures.powerPrice }} per kWh
          stroom is dat {{ figures.aircoPerKwh }} per kWh warmte.
        </p>
        <p>
          1 m³ aardgas bevat afgerond 10 kWh energie. Bij {{ figures.boilerEfficiency }} rendement
          levert deze oudere cv-ketel {{ figures.boilerHeat }}. Bij {{ figures.gasPrice }} per m³ is
          dat circa {{ figures.boilerPerKwh }} per kWh warmte.
        </p>
        <p>
          In dit rekenvoorbeeld kan verwarmen met een airco ongeveer 59% goedkoper zijn per
          geleverde kWh warmte. De praktijk hangt af van buitentemperatuur, COP, woning, installatie
          en gebruik; een realistische bandbreedte is ongeveer 35% tot 60%.
        </p>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import {
  HEATING_FIGURES,
  HEATING_PHASE_DESKTOP,
  HEATING_PHASE_MOBILE,
  HEATING_SCENES,
  beatOpacity,
  beatWindow,
  productScale,
  sceneLocal,
  sceneOpacity,
  translateYReveal,
} from '../../composables/heatingStory'
import {
  handoffPinStyle,
  HANDOFF_SCROLL_VH,
  HANDOFF_SCROLL_VH_MOBILE,
  type ScrollPhase,
} from '../../composables/mapScrollPhases'

const figures = HEATING_FIGURES
const aircoSrc = '/media/airco-indoor-unit.png'
const aircoWebp = '/media/airco-indoor-unit.webp'
const boilerSrc = '/media/cv-ketel.png'
const boilerWebp = '/media/cv-ketel.webp'

const container = ref<HTMLElement | null>(null)
const reduced = usePrefersReducedMotion()
const isMobile = ref(false)
const hashSnap = ref(false)

const scrubRange = computed(() =>
  isMobile.value ? { ...HEATING_PHASE_MOBILE } : { ...HEATING_PHASE_DESKTOP },
)

const enabled = computed(() => !reduced.value)

const { trackProgress, scrubProgress, enterProgress, exitProgress, phase } = useStickyTrack({
  container,
  enabled,
  scrubRange,
})

const displayPhase = computed<ScrollPhase>(() => {
  if (reduced.value) return 'scrub'
  if (hashSnap.value && phase.value === 'intro') return 'scrub'
  return phase.value
})

const isEntering = computed(
  () => !reduced.value && !hashSnap.value && enterProgress.value < 1 && exitProgress.value <= 0,
)

const pinHandoffStyle = computed(() => {
  if (reduced.value) return undefined

  if (exitProgress.value > 0) {
    const distanceVh = isMobile.value ? HANDOFF_SCROLL_VH_MOBILE : HANDOFF_SCROLL_VH
    return handoffPinStyle(exitProgress.value, 'out', distanceVh)
  }

  if (hashSnap.value) return undefined

  if (enterProgress.value < 1) {
    const distanceVh = isMobile.value ? HANDOFF_SCROLL_VH_MOBILE : HANDOFF_SCROLL_VH
    return handoffPinStyle(enterProgress.value, 'in', distanceVh)
  }

  return undefined
})

function band(id: (typeof HEATING_SCENES)[number]['id']) {
  const s = HEATING_SCENES.find((x) => x.id === id)!
  return s
}

function localOf(id: (typeof HEATING_SCENES)[number]['id']) {
  const s = band(id)
  return sceneLocal(scrubProgress.value, s.from, s.to)
}

function layer(opacity: number): Record<string, string> {
  const o = Math.max(0, Math.min(1, opacity))
  return {
    opacity: String(o),
    visibility: o < 0.02 ? 'hidden' : 'visible',
    pointerEvents: 'none',
  }
}

function reveal(opacity: number, maxPx = 28): Record<string, string> {
  return {
    opacity: String(opacity),
    transform: translateYReveal(opacity, maxPx),
  }
}

/* —— Scene computations —— */

const intro = computed(() => {
  const local = localOf('intro')
  return {
    opacity: sceneOpacity(local, 0.08, 0.88),
    line1: beatOpacity(local, 0.05, 0.22),
    line2: beatOpacity(local, 0.35, 0.55),
  }
})

const aircoOut = computed(() => {
  const local = localOf('aircoOutput')
  const productOp = beatOpacity(local, 0.05, 0.25)
  return {
    opacity: sceneOpacity(local, 0.1, 0.88),
    productStyle: {
      opacity: String(productOp),
      transform: `scale(${productScale(productOp)}) translate3d(0, ${(1 - productOp) * 16}px, 0)`,
    },
    b1: beatWindow(local, 0.18, 0.55),
    b2: beatWindow(local, 0.32, 0.62),
    b3: beatOpacity(local, 0.42, 0.62),
    b4: beatOpacity(local, 0.58, 0.72),
    b5: beatOpacity(local, 0.68, 0.84),
  }
})

const aircoCost = computed(() => {
  const local = localOf('aircoCost')
  const showFormula = beatWindow(local, 0.28, 0.58, 0.4)
  const showResult = beatOpacity(local, 0.58, 0.75)
  const showStart = beatOpacity(local, 0.08, 0.25)
  let display = '€ 0,28'
  let subtitle = 'per kWh stroom'
  if (showResult > 0.5) {
    display = '€ 0,07'
    subtitle = 'per kWh warmte'
  } else if (showFormula > 0.35) {
    display = '€ 0,28 ÷ 4'
    subtitle = 'per kWh stroom'
  }
  return {
    opacity: sceneOpacity(local, 0.1, 0.88),
    step1: Math.max(showStart, showFormula, showResult),
    formula: 0,
    showResult,
    display,
    subtitle,
  }
})

const boilerOut = computed(() => {
  const local = localOf('boilerOutput')
  const productOp = beatOpacity(local, 0.05, 0.28)
  return {
    opacity: sceneOpacity(local, 0.1, 0.88),
    productStyle: {
      opacity: String(productOp),
      transform: `scale(${productScale(productOp)}) translate3d(0, ${(1 - productOp) * 18}px, 0)`,
    },
    b1: beatWindow(local, 0.12, 0.45),
    b2: beatWindow(local, 0.22, 0.5),
    b3: beatWindow(local, 0.32, 0.58),
    b4: beatWindow(local, 0.48, 0.72),
    b5: beatOpacity(local, 0.6, 0.78),
    b6: beatOpacity(local, 0.72, 0.88),
  }
})

const boilerCost = computed(() => {
  const local = localOf('boilerCost')
  const showFormula = beatWindow(local, 0.28, 0.58, 0.4)
  const showResult = beatOpacity(local, 0.58, 0.75)
  const showStart = beatOpacity(local, 0.08, 0.25)
  let display = '€ 1,40'
  let subtitle = 'per m³ aardgas'
  if (showResult > 0.5) {
    display = '€ 0,17'
    subtitle = 'per kWh warmte'
  } else if (showFormula > 0.35) {
    display = '€ 1,40 ÷ 8,07'
    subtitle = 'per m³ aardgas'
  }
  return {
    opacity: sceneOpacity(local, 0.1, 0.88),
    step1: Math.max(showStart, showFormula, showResult),
    formula: 0,
    showResult,
    display,
    subtitle,
  }
})

const compare = computed(() => {
  const local = localOf('compare')
  const main = beatOpacity(local, 0.15, 0.4)
  const grow = beatOpacity(local, 0.35, 0.65)
  // Bars: airco baseline 41% of track; boiler 41 * 2.43 ≈ 100
  const aircoH = 41 * grow
  const boilerH = Math.min(100, 41 * 2.43 * grow)
  return {
    opacity: sceneOpacity(local, 0.1, 0.88),
    sub: beatOpacity(local, 0.05, 0.25),
    main,
    aircoH,
    boilerH,
  }
})

const saving = computed(() => {
  const local = localOf('saving')
  return {
    opacity: sceneOpacity(local, 0.08, 0.9),
    lead: beatWindow(local, 0.05, 0.4),
    pct: beatOpacity(local, 0.28, 0.5),
    body: beatOpacity(local, 0.55, 0.75),
  }
})

const context = computed(() => {
  const local = localOf('context')
  return {
    opacity: sceneOpacity(local, 0.08, 1),
    mini: beatOpacity(local, 0.0, 0.2),
    nuance: beatOpacity(local, 0.18, 0.4),
    range: beatOpacity(local, 0.38, 0.55),
    notes: beatOpacity(local, 0.55, 0.75),
  }
})

onMounted(() => {
  if (window.location.hash === '#verwarmen') {
    hashSnap.value = true
  }

  const mq = window.matchMedia('(max-width: 767px)')
  const apply = () => {
    isMobile.value = mq.matches
  }
  apply()
  mq.addEventListener('change', apply)
  onUnmounted(() => mq.removeEventListener('change', apply))
})
</script>

<style scoped>
.heat {
  --heat-ink: #000000;
  --heat-muted: #6e6e73;
  --heat-line: #d2d2d7;
  --heat-blue: #0071e3;
  --heat-blue-soft: #2997ff;
  --heat-orange: #bf4800;
  --heat-orange-soft: #f56300;
  --heat-ease: cubic-bezier(0.22, 1, 0.36, 1);
  --heat-max: 1440px;

  position: relative;
  height: 560vh;
  /* Sit under Winter pin during scroll+fade exit */
  margin-top: calc(-1 * 40vh);
  background: transparent;
  z-index: 1;
  color: var(--heat-ink);
}

.heat--mobile {
  height: 420vh;
  margin-top: calc(-1 * 20vh);
}

.heat--reduced {
  height: auto;
  margin-top: 0;
  background: #fff;
  padding-block: calc(var(--space) * 12) calc(var(--space) * 16);
}

.heat--entering .heat__pin,
.heat--exiting .heat__pin {
  z-index: 3;
}

.heat__pin {
  position: sticky;
  top: 0;
  height: 100vh;
  background: #ffffff;
  z-index: 2;
  overflow: hidden;
  box-sizing: border-box;
  padding-top: var(--header-h);
  will-change: opacity, transform;
}

.heat__stage {
  position: relative;
  width: 100%;
  max-width: var(--heat-max);
  height: calc(100vh - var(--header-h));
  margin-inline: auto;
}

.heat__scene {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 2vh clamp(1.5rem, 5vw, 4.5rem) 4vh;
  box-sizing: border-box;
  will-change: opacity;
}

.heat__scene--center {
  align-items: center;
  text-align: center;
}

.heat__scene--airco {
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  align-items: center;
  gap: clamp(1.5rem, 4vw, 4rem);
}

.heat__scene--boiler {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  align-items: center;
  gap: clamp(1.5rem, 4vw, 4rem);
}

.heat__scene--boiler-cost {
  /* Slightly left-biased vs airco cost — avoid mechanical symmetry */
  padding-left: clamp(1.5rem, 8vw, 7rem);
  align-items: flex-start;
  text-align: left;
}

.heat__scene--compare {
  align-items: center;
}

.heat__scene--saving,
.heat__scene--context {
  align-items: center;
  text-align: center;
}

.heat__hero {
  margin: 0;
  max-width: 18ch;
  font-size: clamp(48px, 9vw, 112px);
  font-weight: 600;
  letter-spacing: -0.045em;
  line-height: 0.98;
  color: var(--heat-ink);
}

.heat__sub {
  margin: clamp(1rem, 2.5vh, 1.75rem) 0 0;
  max-width: 22ch;
  font-size: clamp(22px, 3.2vw, 40px);
  font-weight: 500;
  letter-spacing: -0.03em;
  line-height: 1.2;
  color: var(--heat-muted);
}

.heat__product {
  display: flex;
  align-items: center;
  justify-content: center;
  will-change: transform, opacity;
}

.heat__product img {
  width: 100%;
  height: auto;
  filter: drop-shadow(0 28px 48px rgba(0, 0, 0, 0.12));
  user-select: none;
  -webkit-user-drag: none;
}

.heat__product--airco img {
  width: min(52vw, 640px);
  max-width: 100%;
}

.heat__product--boiler img {
  width: min(28vw, 340px);
  max-width: 100%;
}

.heat__copy--airco {
  padding-top: 2vh;
}

.heat__copy--boiler {
  padding-left: clamp(0rem, 2vw, 2rem);
}

.heat__line {
  margin: 0 0 0.35rem;
  font-size: clamp(22px, 2.6vw, 36px);
  font-weight: 600;
  letter-spacing: -0.03em;
  color: var(--heat-ink);
}

.heat__line--quiet {
  font-weight: 500;
  color: var(--heat-muted);
  font-size: clamp(18px, 2vw, 28px);
}

.heat__feature {
  margin: 0.4rem 0 0;
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.15em;
  line-height: 0.95;
}

.heat__giant {
  font-size: clamp(96px, 16vw, 220px);
  font-weight: 600;
  letter-spacing: -0.055em;
  line-height: 0.9;
}

.heat__giant--sm {
  font-size: clamp(72px, 11vw, 160px);
  color: var(--heat-ink);
}

.heat__giant--md {
  font-size: clamp(80px, 12vw, 180px);
}

.heat__feature-rest {
  font-size: clamp(28px, 3.5vw, 48px);
  font-weight: 600;
  letter-spacing: -0.035em;
  color: var(--heat-ink);
}

.heat__cop {
  margin: 0.75rem 0 0;
  font-size: clamp(16px, 1.4vw, 22px);
  font-weight: 600;
  letter-spacing: -0.02em;
}

.heat__explain {
  margin: 1.25rem 0 0;
  max-width: 34ch;
  font-size: clamp(17px, 1.35vw, 22px);
  font-weight: 400;
  line-height: 1.45;
  letter-spacing: -0.01em;
  color: var(--heat-muted);
}

.heat__explain--wide {
  max-width: 38ch;
  margin-inline: auto;
}

.heat__price {
  margin: 0;
  font-size: clamp(80px, 14vw, 200px);
  font-weight: 600;
  letter-spacing: -0.05em;
  line-height: 0.92;
}

.heat__price--formula {
  font-size: clamp(40px, 7vw, 96px);
  letter-spacing: -0.04em;
}

.heat__price-sub {
  margin: 0.5rem 0 0;
  font-size: clamp(20px, 2.2vw, 32px);
  font-weight: 500;
  color: var(--heat-muted);
  letter-spacing: -0.02em;
}

.heat__formula {
  margin: 1.25rem 0 0;
  font-size: clamp(22px, 2.4vw, 36px);
  font-weight: 500;
  color: var(--heat-muted);
  letter-spacing: -0.025em;
}

.heat__blue {
  color: var(--heat-blue);
}

.heat__orange {
  color: var(--heat-orange);
}

.heat__compare-sub {
  margin: 0 0 clamp(1.5rem, 4vh, 2.5rem);
  font-size: clamp(16px, 1.4vw, 21px);
  font-weight: 500;
  color: var(--heat-muted);
  letter-spacing: -0.015em;
  text-align: center;
}

.heat__compare {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: end;
  gap: clamp(2rem, 8vw, 7rem);
  width: min(720px, 92vw);
  border-bottom: 1px solid var(--heat-line);
  padding-bottom: 0;
}

.heat__compare-rule {
  width: 1px;
  align-self: stretch;
  background: transparent;
}

.heat__bar-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.heat__bar-price {
  margin: 0;
  font-size: clamp(40px, 6vw, 72px);
  font-weight: 600;
  letter-spacing: -0.045em;
  line-height: 1;
}

.heat__bar-label {
  margin: 0;
  font-size: clamp(14px, 1.2vw, 18px);
  font-weight: 500;
  color: var(--heat-muted);
}

.heat__bar-track {
  width: clamp(28px, 4vw, 44px);
  height: clamp(140px, 28vh, 260px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.heat__bar {
  width: 100%;
  border-radius: 2px 2px 0 0;
  transform-origin: bottom center;
}

.heat__bar--blue {
  background: var(--heat-blue);
}

.heat__bar--orange {
  background: var(--heat-orange);
}

.heat__lead {
  margin: 0 0 1vh;
  font-size: clamp(24px, 3vw, 40px);
  font-weight: 500;
  letter-spacing: -0.03em;
  color: var(--heat-muted);
}

.heat__hero-pct {
  margin: 0;
  font-size: clamp(96px, 28vh, 280px);
  font-weight: 600;
  letter-spacing: -0.06em;
  line-height: 0.85;
}

.heat__goodkoper {
  margin: 0.25rem 0 0;
  font-size: clamp(28px, 4vw, 56px);
  font-weight: 600;
  letter-spacing: -0.035em;
}

.heat__saving-mini {
  margin: 0 0 2vh;
  font-size: clamp(40px, 6vw, 72px);
  font-weight: 600;
  letter-spacing: -0.045em;
  opacity: 0.85;
}

.heat__range {
  margin: 1.25rem 0 0;
  max-width: 28ch;
  font-size: clamp(22px, 2.4vw, 32px);
  font-weight: 600;
  letter-spacing: -0.03em;
  line-height: 1.25;
}

.heat__notes {
  list-style: none;
  margin: clamp(2rem, 5vh, 3.5rem) 0 0;
  padding: 0;
  width: min(680px, 92vw);
  text-align: left;
}

.heat__notes li {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 1rem;
  align-items: baseline;
  padding: 1rem 0;
  border-top: 1px solid var(--heat-line);
  font-size: clamp(15px, 1.2vw, 18px);
  line-height: 1.4;
  color: var(--heat-ink);
}

.heat__notes li:last-child {
  border-bottom: 1px solid var(--heat-line);
}

.heat__note-num {
  font-size: clamp(28px, 3vw, 40px);
  font-weight: 600;
  letter-spacing: -0.04em;
  color: #d2d2d7;
  line-height: 1;
}

.heat__notes--stage {
  margin-inline: auto;
}

/* Reduced / static */
.heat__reduced-title {
  margin: 0;
  font-size: clamp(40px, 8vw, 80px);
  font-weight: 600;
  letter-spacing: -0.045em;
  line-height: 1;
}

.heat__reduced-sub {
  margin: 1rem 0 3rem;
  max-width: 34ch;
  font-size: clamp(20px, 2.5vw, 28px);
  color: var(--heat-muted);
  letter-spacing: -0.02em;
}

.heat__reduced-figure {
  margin: 0 0 3.5rem;
  padding: 0;
}

.heat__reduced-figure img {
  width: min(100%, 560px);
  height: auto;
  margin-bottom: 1.25rem;
  filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.1));
}

.heat__reduced-figure figcaption p {
  margin: 0 0 0.75rem;
  max-width: 42ch;
  font-size: clamp(17px, 1.4vw, 21px);
  line-height: 1.45;
  color: var(--heat-ink);
}

.heat__figure-xl {
  margin: 1rem 0 0.25rem !important;
  font-size: clamp(56px, 10vw, 120px);
  font-weight: 600;
  letter-spacing: -0.05em;
  line-height: 0.95;
}

.heat__figure-hero {
  margin: 0.5rem 0;
  font-size: clamp(80px, 18vw, 200px);
  font-weight: 600;
  letter-spacing: -0.055em;
  line-height: 0.9;
}

.heat__muted {
  color: var(--heat-muted) !important;
}

.heat__reduced-compare {
  margin: 0 0 3.5rem;
}

.heat__bars--static {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3rem;
  width: min(480px, 100%);
  border-bottom: 1px solid var(--heat-line);
  align-items: end;
  margin-top: 1.5rem;
}

.heat__bars--static .heat__bar-track,
.heat__bars--static .heat__bar {
  height: 160px;
}

.heat__bars--static .heat__bar {
  width: 36px;
  margin-inline: auto;
}

.heat__reduced-lead {
  margin: 0;
  font-size: clamp(22px, 2.5vw, 32px);
  color: var(--heat-muted);
  font-weight: 500;
}

.heat__reduced-goodkoper {
  margin: 0 0 1.5rem;
  font-size: clamp(28px, 4vw, 48px);
  font-weight: 600;
}

.heat__reduced-body {
  margin: 0 0 1rem;
  max-width: 42ch;
  font-size: clamp(17px, 1.4vw, 21px);
  line-height: 1.45;
}

.heat__reduced-range {
  margin: 0 0 2rem;
  max-width: 28ch;
  font-size: clamp(22px, 2.4vw, 30px);
  font-weight: 600;
  letter-spacing: -0.03em;
}

.heat__footnote {
  margin: 2.5rem 0 0;
  max-width: 52ch;
  font-size: 14px;
  line-height: 1.45;
  color: var(--heat-muted);
}

@media (max-width: 767px) {
  .heat__scene {
    padding: 1.5vh 24px 3vh;
  }

  .heat__scene--airco,
  .heat__scene--boiler {
    grid-template-columns: 1fr;
    gap: 1.25rem;
    justify-items: center;
    text-align: center;
  }

  .heat__copy--airco,
  .heat__copy--boiler {
    padding: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .heat__explain {
    text-align: center;
  }

  .heat__product--airco img {
    width: min(88vw, 420px);
  }

  .heat__product--boiler {
    order: -1;
  }

  .heat__product--boiler img {
    width: min(42vw, 200px);
  }

  .heat__scene--boiler-cost {
    padding-left: 24px;
    align-items: center;
    text-align: center;
  }

  .heat__hero {
    max-width: 12ch;
    font-size: clamp(40px, 11vw, 56px);
  }

  .heat__giant {
    font-size: clamp(72px, 22vw, 120px);
  }

  .heat__price {
    font-size: clamp(64px, 18vw, 96px);
  }

  .heat__hero-pct {
    font-size: clamp(80px, 22vh, 160px);
  }

  .heat__compare {
    gap: 1.75rem;
    width: 100%;
  }

  .heat__bar-track {
    height: 120px;
  }

  .heat__feature {
    justify-content: center;
  }
}
</style>
