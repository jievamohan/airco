<template>
  <section
    id="winter"
    ref="container"
    class="winter"
    :class="{
      'winter--mobile': isMobile,
      'winter--reduced': reduced,
      'winter--exiting': exitProgress > 0 && !reduced,
      'winter--entering': isEntering,
    }"
    data-testid="winter-scrub"
    :data-scrub-progress="scrubProgress.toFixed(3)"
    :data-track-progress="trackProgress.toFixed(3)"
    :data-scroll-phase="displayPhase"
  >
    <div class="winter__pin" :style="pinHandoffStyle">
      <div class="winter__copy">
        <p
          v-for="line in captionLines"
          :key="line.id"
          class="winter__caption"
          :style="{ opacity: line.opacity }"
        >
          {{ line.text }}
        </p>
      </div>

      <div class="winter__stage">
        <div class="winter__frame" :style="frameScaleStyle">
          <img
            src="/media/livingroom-winter-start.png"
            alt=""
            width="1280"
            height="720"
            class="winter__poster"
            aria-hidden="true"
          />

          <img
            v-if="reduced || error"
            src="/media/livingroom-winter-start.png"
            alt="Woonkamer van zomer naar winter"
            width="1280"
            height="720"
            class="winter__media"
          />

          <video
            v-else
            ref="video"
            class="winter__media"
            muted
            defaultmuted
            playsinline
            webkit-playsinline
            preload="auto"
            poster="/media/livingroom-winter-start.png"
            disablepictureinpicture
            disableremoteplayback
            tabindex="-1"
            aria-hidden="true"
            @loadedmetadata="markReady"
            @loadeddata="markReady"
            @canplay="markReady"
            @error="onError"
          >
            <source src="/media/livingroom-winter.mp4?v=1" type="video/mp4" />
          </video>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  DESKTOP_SCRUB_MEDIA_SCALE,
  handoffPinStyle,
  HANDOFF_SCROLL_VH,
  HANDOFF_SCROLL_VH_MOBILE,
  WINTER_PHASE_DESKTOP,
  WINTER_PHASE_MOBILE,
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
  isMobile.value ? WINTER_PHASE_MOBILE : WINTER_PHASE_DESKTOP,
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
  text: string
  from: number
  to: number
}

/**
 * Summer portion of the scrub stays caption-free; lines appear as the
 * season turns toward winter.
 */
const lines: CaptionLine[] = [
  { id: 'winter', text: 'Ook in de winter.', from: 0.48, to: 0.62 },
  { id: 'same', text: 'Dezelfde airco.', from: 0.64, to: 0.78 },
  { id: 'heat', text: 'Verwarmt je woning.', from: 0.80, to: 1 },
]

const captionLines = computed(() => {
  if (reduced.value) {
    return [
      {
        id: 'reduced',
        text: 'Ook in de winter — dezelfde airco verwarmt je woning.',
        opacity: 1,
      },
    ]
  }

  return lines.map((line) => {
    const isLast = line.id === 'heat'
    const opacity =
      isLast && scrubProgress.value >= line.from
        ? 1
        : scrubCaptionOpacity(line.from, line.to, scrubProgress.value, 0.72)
    return {
      id: line.id,
      text: line.text,
      opacity,
    }
  })
})

/**
 * Enter / exit: same scroll+fade handoff as Climate ↔ neighbours.
 */
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

/** Desktop: scrub media rests at +30%. */
const frameScaleStyle = computed(() => {
  if (isMobile.value) return undefined
  return { transform: `scale(${DESKTOP_SCRUB_MEDIA_SCALE})` }
})

onMounted(() => {
  if (window.location.hash === '#winter') {
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
.winter {
  position: relative;
  height: 220vh;
  /*
   * Stick under Climate from outroStart 0.85:
   * -(220 - 0.85 × 120) ≈ -118vh
   */
  margin-top: -118vh;
  background: transparent;
  z-index: 2;
}

.winter--mobile {
  height: 165vh;
  /* Climate 165vh, outroStart 0.82 → ≈ -112vh */
  margin-top: -112vh;
}

.winter--reduced {
  height: 110vh;
  margin-top: 0;
}

.winter--reduced.winter--mobile {
  height: 105vh;
  margin-top: 0;
}

.winter__pin {
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
  z-index: 2;
  overflow: visible;
  will-change: opacity, transform;
}

.winter--entering .winter__pin {
  z-index: 3;
}

.winter--exiting .winter__pin {
  z-index: 3;
}

.winter__copy {
  position: relative;
  z-index: 2;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  padding: 0 clamp(1.25rem, 4vw, 2.5rem);
  min-height: 4.5rem;
}

.winter__caption {
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

.winter__stage {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 1 auto;
  min-height: 0;
  width: 100%;
  padding: 0 3vw;
  overflow: visible;
}

.winter__frame {
  position: relative;
  width: min(1100px, 94vw);
  transform-origin: center center;
  will-change: transform, opacity;
}

.winter__frame::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 2;
  pointer-events: none;
  background:
    linear-gradient(to right, #fff 0%, transparent 14%, transparent 86%, #fff 100%),
    linear-gradient(to bottom, #fff 0%, transparent 12%, transparent 88%, #fff 100%);
}

.winter__poster {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  pointer-events: none;
}

.winter__media {
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

.winter__media::-webkit-media-controls {
  display: none !important;
}

@media (max-width: 767px) {
  .winter__pin {
    width: 100%;
  }

  .winter__copy {
    min-height: 3.75rem;
    width: 100%;
    padding-inline: 16px;
  }

  .winter__caption {
    width: 100%;
    max-width: 16ch;
    font-size: clamp(1.65rem, 7.5vw, 2.35rem);
  }

  .winter__stage {
    overflow: hidden;
    width: 100%;
    padding: 0;
  }

  .winter__frame {
    width: 100%;
  }

  .winter__frame::after {
    background:
      linear-gradient(to right, #fff 0%, transparent 6%, transparent 94%, #fff 100%),
      linear-gradient(to bottom, #fff 0%, transparent 8%, transparent 92%, #fff 100%);
  }

  .winter__media {
    max-height: 48vh;
  }
}
</style>
