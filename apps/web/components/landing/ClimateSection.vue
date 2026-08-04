<template>
  <section
    id="werkwijze"
    ref="container"
    class="climate"
    :class="{
      'climate--mobile': isMobile,
      'climate--reduced': reduced,
      'climate--exiting': exitProgress > 0 && !reduced,
    }"
    data-testid="climate-scrub"
    :data-scrub-progress="scrubProgress.toFixed(3)"
    :data-track-progress="trackProgress.toFixed(3)"
    :data-scroll-phase="displayPhase"
  >
    <div class="climate__pin" :style="pinWipeStyle">
      <div class="climate__copy">
        <div
          v-for="beat in captionBeats"
          :key="beat.id"
          class="climate__beat"
          :style="{ opacity: beat.opacity }"
        >
          <h2 class="climate__title" v-html="beat.titleHtml" />
          <p class="climate__body">{{ beat.body }}</p>
        </div>
      </div>

      <div class="climate__stage">
        <div class="climate__frame" :style="frameEnterStyle">
          <img
            src="/media/2nd-start.png"
            alt=""
            width="1536"
            height="1024"
            class="climate__poster"
            aria-hidden="true"
          />

          <img
            v-if="reduced || error"
            src="/media/2nd-start.png"
            alt="Nederlandse woonwijk met comfortabel interieur"
            width="1536"
            height="1024"
            class="climate__media"
          />

          <video
            v-else
            ref="video"
            class="climate__media"
            muted
            defaultmuted
            playsinline
            webkit-playsinline
            preload="auto"
            poster="/media/2nd-start.png"
            disablepictureinpicture
            disableremoteplayback
            tabindex="-1"
            aria-hidden="true"
            @loadedmetadata="markReady"
            @loadeddata="markReady"
            @canplay="markReady"
            @error="onError"
          >
            <source src="/media/2nd-animated.mp4?v=ios-main" type="video/mp4" />
          </video>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  easeEnter,
  easeExit,
  HANDOFF_HOLD,
  SCRUB_PHASE_DESKTOP,
  SCRUB_PHASE_MOBILE,
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
  isMobile.value ? SCRUB_PHASE_MOBILE : SCRUB_PHASE_DESKTOP,
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

const visualEnter = computed(() => {
  if (reduced.value || hashSnap.value) return 1
  return enterProgress.value
})

const displayPhase = computed<ScrollPhase>(() => {
  if (reduced.value) return 'scrub'
  if (hashSnap.value && phase.value === 'intro') return 'scrub'
  return phase.value
})

type Beat = {
  id: string
  titleHtml: string
  body: string
  from: number
  to: number
}

const beats: Beat[] = [
  {
    id: 'warmer',
    titleHtml: 'Nederland wordt <span class="gradient-warm">warmer</span>.',
    body: 'Meer tropische dagen. Langere hittegolven. En woningen die die warmte steeds langer vasthouden.',
    from: 0.02,
    to: 0.18,
  },
  {
    id: 'street',
    titleHtml: 'Buiten voelt de zomer zwaarder.',
    body: 'Straten, gevels en daken warmen op. ’s Avonds blijft die warmte hangen — precies wanneer je thuis wilt afkoelen.',
    from: 0.20,
    to: 0.36,
  },
  {
    id: 'window',
    titleHtml: 'Comfort begint achter het raam.',
    body: 'Terwijl de zon buiten steekt, wil je binnen een rustig, leefbaar klimaat. Zonder zware lucht. Zonder pieken.',
    from: 0.38,
    to: 0.54,
  },
  {
    id: 'unit',
    titleHtml: 'De binnenunit werkt onzichtbaar mee.',
    body: 'Stil, strak gemonteerd, en precies afgestemd op de ruimte. Geen gedoe — wel een stabiele temperatuur.',
    from: 0.56,
    to: 0.72,
  },
  {
    id: 'home',
    titleHtml: 'Thuis, precies goed.',
    body: 'Koelen wanneer het oploopt. En klaar om te verwarmen wanneer de seizoenen draaien — in één systeem.',
    from: 0.74,
    to: 0.96,
  },
]

const captionBeats = computed(() => {
  if (reduced.value) {
    return [
      {
        id: 'reduced',
        titleHtml: 'Nederland wordt <span class="gradient-warm">warmer</span>.',
        body: 'Een comfortabel huis wordt steeds belangrijker — met koelen én verwarmen in één systeem.',
        opacity: 1,
      },
    ]
  }

  return beats.map((b) => ({
    id: b.id,
    titleHtml: b.titleHtml,
    body: b.body,
    opacity: scrubCaptionOpacity(b.from, b.to, scrubProgress.value, 0.55),
  }))
})

const frameEnterStyle = computed(() => {
  const t = easeEnter(visualEnter.value)
  const from = isMobile.value ? 1.1 : 1.15
  const scale = from + (1 - from) * t
  // Keep a floor so S1 wipe always reveals a visible frame.
  const opacity = Math.min(1, 0.25 + (0.75 * t) / HANDOFF_HOLD)
  return {
    opacity: String(opacity),
    transform: `scale(${scale})`,
  }
})

const pinWipeStyle = computed(() => {
  if (reduced.value || exitProgress.value <= 0) {
    return undefined
  }
  const raw = Math.max(0, (exitProgress.value - HANDOFF_HOLD) / (1 - HANDOFF_HOLD))
  const wipe = easeExit(raw)
  return {
    opacity: String(1 - wipe),
    clipPath: `inset(0 0 ${wipe * 100}% 0)`,
  }
})

onMounted(() => {
  if (window.location.hash === '#werkwijze') {
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
.climate {
  position: relative;
  height: 220vh;
  /* Pull under S1 outro band only — avoid starting S2 scrub mid-S1 */
  margin-top: -25vh;
  background: transparent;
  z-index: 3;
}

.climate--mobile {
  height: 165vh;
  margin-top: -18vh;
}

.climate--reduced {
  height: 110vh;
  margin-top: 0;
}

.climate--reduced.climate--mobile {
  height: 105vh;
  margin-top: 0;
}

.climate__pin {
  position: sticky;
  top: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: clamp(0.65rem, 1.8vh, 1.35rem);
  background: #fff;
  padding: calc(var(--header-h) + 0.35rem) 0 2vh;
  box-sizing: border-box;
  z-index: 3;
  will-change: clip-path, opacity;
}

.climate--exiting .climate__pin {
  z-index: 4;
}

.climate__copy {
  position: relative;
  z-index: 2;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  width: min(40rem, 92vw);
  min-height: 7.5rem;
  padding: 0 clamp(1rem, 3vw, 1.5rem);
  text-align: center;
}

.climate__beat {
  grid-area: 1 / 1;
  width: 100%;
  pointer-events: none;
  transition: opacity 0.05s linear;
}

.climate__title {
  margin: 0 0 0.65rem;
  font-size: clamp(1.75rem, 4.2vw, 3rem);
  font-weight: 600;
  letter-spacing: -0.035em;
  line-height: 1.12;
  color: var(--color-ink);
}

.climate__body {
  margin: 0 auto;
  max-width: 34rem;
  font-size: clamp(1rem, 1.7vw, 1.2rem);
  font-weight: 400;
  line-height: 1.45;
  letter-spacing: -0.01em;
  color: var(--color-ink-muted);
}

.climate__stage {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 1 auto;
  min-height: 0;
  width: 100%;
  padding: 0 3vw;
  overflow: hidden;
}

.climate__frame {
  position: relative;
  width: min(1100px, 94vw);
  transform-origin: center center;
  will-change: transform, opacity;
}

.climate__frame::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 2;
  pointer-events: none;
  background:
    linear-gradient(to right, #fff 0%, transparent 14%, transparent 86%, #fff 100%),
    linear-gradient(to bottom, #fff 0%, transparent 12%, transparent 88%, #fff 100%);
}

.climate__poster {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  pointer-events: none;
}

.climate__media {
  position: relative;
  z-index: 1;
  display: block;
  width: 100%;
  height: auto;
  max-height: min(58vh, 680px);
  object-fit: contain;
  margin: 0 auto;
  border: 0;
  outline: none;
  background: transparent;
  pointer-events: none;
  -webkit-touch-callout: none;
}

.climate__media::-webkit-media-controls {
  display: none !important;
}

@media (max-width: 767px) {
  .climate__copy {
    min-height: 8.5rem;
  }

  .climate__title {
    font-size: clamp(1.5rem, 6.5vw, 2.1rem);
  }

  .climate__body {
    font-size: 0.98rem;
  }

  .climate__media {
    max-height: 48vh;
  }
}
</style>
