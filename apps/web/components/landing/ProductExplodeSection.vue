<template>
  <section
    id="aircos"
    ref="container"
    class="explode"
    :class="{
      'explode--mobile': isMobile,
      'explode--reduced': reduced,
      'explode--exiting': exitProgress > 0 && !reduced,
      'explode--entering': isEntering,
    }"
    data-testid="product-scrub"
    :data-scrub-progress="scrubProgress.toFixed(3)"
    :data-track-progress="trackProgress.toFixed(3)"
    :data-scroll-phase="displayPhase"
  >
    <div class="explode__pin" :style="pinHandoffStyle">
      <div class="explode__copy">
        <p
          v-for="line in captionLines"
          :key="line.id"
          class="explode__caption"
          :style="{ opacity: line.opacity }"
        >
          <template v-if="line.emphasis">
            <span class="explode__en">{{ line.emphasis }}</span>{{ line.after }}
          </template>
          <template v-else>
            {{ line.text }}
          </template>
        </p>
      </div>

      <div class="explode__stage">
        <div class="explode__frame" :style="frameScaleStyle">
          <!-- Poster always under video (iOS often shows blank until primed) -->
          <img
            src="/media/1st-start.png"
            alt=""
            width="1536"
            height="1024"
            class="explode__poster"
            aria-hidden="true"
          />

          <img
            v-if="reduced || error"
            src="/media/1st-start.png"
            alt="KlimaatX binnenunit"
            width="1536"
            height="1024"
            class="explode__media"
          />

          <video
            v-else
            ref="video"
            class="explode__media"
            muted
            defaultmuted
            playsinline
            webkit-playsinline
            preload="auto"
            poster="/media/1st-start.png"
            disablepictureinpicture
            disableremoteplayback
            tabindex="-1"
            aria-hidden="true"
            @loadedmetadata="markReady"
            @loadeddata="markReady"
            @canplay="markReady"
            @error="onError"
          >
            <source src="/media/1st-animated.mp4?v=ios-main" type="video/mp4" />
          </video>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  desktopScrubMediaScale,
  handoffPinStyle,
  PRODUCT_PHASE_DESKTOP,
  PRODUCT_PHASE_MOBILE,
  type ScrollPhase,
} from '../../composables/mapScrollPhases'

const container = ref<HTMLElement | null>(null)
const video = ref<HTMLVideoElement | null>(null)
const reduced = usePrefersReducedMotion()
const isMobile = ref(false)
const hashSnap = ref(false)

const { ready, error, markReady, onError } = useScrubVideo(video)

const enabled = computed(() => !reduced.value && !error.value && ready.value)

const scrubRange = computed(() =>
  isMobile.value ? PRODUCT_PHASE_MOBILE : PRODUCT_PHASE_DESKTOP,
)

const {
  trackProgress,
  scrubProgress,
  phase,
  enterProgress,
  exitProgress,
} = useScrollScrub({
  container,
  video,
  enabled,
  scrubRange,
  pauseSeekOffscreen: true,
})

const displayPhase = computed<ScrollPhase>(() => {
  if (reduced.value) return 'scrub'
  if (hashSnap.value && phase.value === 'intro') return 'scrub'
  return phase.value
})

const isEntering = computed(
  () => !reduced.value && !hashSnap.value && enterProgress.value < 1 && exitProgress.value <= 0,
)

type CaptionLine = {
  id: string
  from: number
  to: number
  text?: string
  emphasis?: string
  after?: string
}

const lines: CaptionLine[] = [
  { id: 'versatile', text: 'Airco-units zijn veelzijdig.', from: 0.06, to: 0.26 },
  { id: 'inside', text: 'Ze hebben alles in zich', from: 0.28, to: 0.48 },
  { id: 'cool', text: 'om te kunnen koelen...', from: 0.50, to: 0.70 },
  {
    id: 'heat',
    emphasis: 'én',
    after: ' te kunnen verwarmen',
    from: 0.72,
    to: 1,
  },
]

const captionLines = computed(() => {
  if (reduced.value) {
    return [
      {
        id: 'reduced',
        text: 'Airco-units zijn veelzijdig — koelen én verwarmen.',
        emphasis: undefined as string | undefined,
        after: undefined as string | undefined,
        opacity: 1,
      },
    ]
  }

  // Last caption stays at full opacity once shown; pin handoff fades the section.
  return lines.map((line) => {
    const isLast = line.id === 'heat'
    const opacity =
      isLast && scrubProgress.value >= line.from
        ? 1
        : scrubCaptionOpacity(line.from, line.to, scrubProgress.value, 0.72)
    return {
      id: line.id,
      text: line.text,
      emphasis: line.emphasis,
      after: line.after,
      opacity,
    }
  })
})

/**
 * Enter: rise + fade in lockstep with hero exit (same distance/speed).
 * Exit: scroll up + fade in lockstep with Climate enter.
 */
const pinHandoffStyle = computed(() => {
  if (reduced.value) return undefined

  if (exitProgress.value > 0) {
    return handoffPinStyle(exitProgress.value, 'out')
  }

  if (hashSnap.value) return undefined

  if (enterProgress.value < 1) {
    return handoffPinStyle(enterProgress.value, 'in')
  }

  return undefined
})

/**
 * Desktop: scrub media rests at +30%.
 * On hero→S1 enter: start +60% and ease down to +30% while sliding in.
 */
const frameScaleStyle = computed(() => {
  if (isMobile.value) return undefined

  const animateEnter =
    !reduced.value &&
    !hashSnap.value &&
    enterProgress.value < 1 &&
    exitProgress.value <= 0

  const scale = desktopScrubMediaScale(
    animateEnter ? enterProgress.value : 1,
    animateEnter,
  )

  return { transform: `scale(${scale})` }
})

onMounted(() => {
  if (window.location.hash === '#aircos') {
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
.explode {
  position: relative;
  /* Langzamere scrub-track voor de productanimatie */
  height: 230vh;
  /*
   * Pull under hero track (100vh pin + 40vh handoff) so S1 intro
   * runs in parallel with the hero scroll/fade — no long empty gap.
   */
  margin-top: calc(-100vh - 40vh);
  /* Transparent section bg so S1→S2 pin handoff can reveal Climate beneath */
  background: transparent;
  z-index: 4;
}

.explode--mobile {
  height: 175vh;
  margin-top: calc(-100vh - 40vh);
}

.explode--reduced {
  height: 110vh;
  margin-top: 0;
}

.explode--reduced.explode--mobile {
  height: 105vh;
  margin-top: 0;
}

.explode__pin {
  position: sticky;
  top: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: clamp(0.5rem, 1.5vh, 1.25rem);
  background: #fff;
  padding: calc(var(--header-h) + 0.5rem) 0 2vh;
  box-sizing: border-box;
  z-index: 4;
  will-change: transform, opacity;
}

.explode--entering .explode__pin {
  z-index: 5;
}

.explode--exiting .explode__pin {
  z-index: 5;
}

.explode__copy {
  position: relative;
  z-index: 2;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  padding: 0 clamp(1.25rem, 4vw, 2.5rem);
  min-height: 4.5rem;
}

.explode__caption {
  grid-area: 1 / 1;
  margin: 0;
  width: min(18ch, 92vw);
  text-align: center;
  font-size: clamp(2rem, 5.2vw, 3.75rem);
  font-weight: 500;
  letter-spacing: -0.035em;
  line-height: 1.12;
  color: var(--color-ink);
  pointer-events: none;
  transition: opacity 0.05s linear;
}

.explode__en {
  font-weight: 600;
  background: linear-gradient(90deg, var(--color-cool) 0%, var(--color-warm) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.explode__stage {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 1 auto;
  min-height: 0;
  padding: 0 3vw;
  /* Allow desktop +30%/+60% frame scale without hard crop */
  overflow: visible;
  width: 100%;
}

.explode__frame {
  position: relative;
  width: min(820px, 92vw);
  transform-origin: center center;
  will-change: transform, opacity;
}

/* Soft white edge fade — overlay (not CSS mask on video; Safari iOS breaks that) */
.explode__frame::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 2;
  pointer-events: none;
  background:
    linear-gradient(to right, #fff 0%, transparent 14%, transparent 86%, #fff 100%),
    linear-gradient(to bottom, #fff 0%, transparent 12%, transparent 88%, #fff 100%);
}

.explode__poster {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  pointer-events: none;
}

.explode__media {
  position: relative;
  z-index: 1;
  display: block;
  width: 100%;
  height: auto;
  max-height: min(62vh, 720px);
  object-fit: contain;
  margin: 0 auto;
  border: 0;
  outline: none;
  background: transparent;
  pointer-events: none;
  -webkit-touch-callout: none;
}

.explode__media::-webkit-media-controls {
  display: none !important;
}

@media (max-width: 767px) {
  .explode__pin {
    gap: 0.35rem;
  }

  .explode__copy {
    min-height: 3.75rem;
  }

  .explode__caption {
    width: min(16ch, 90vw);
    font-size: clamp(1.65rem, 7.5vw, 2.35rem);
  }

  .explode__stage {
    overflow: hidden;
  }

  .explode__media {
    max-height: 52vh;
  }
}
</style>
