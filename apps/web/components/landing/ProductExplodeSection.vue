<template>
  <section
    id="aircos"
    ref="container"
    class="explode"
    :class="{ 'explode--mobile': isMobile }"
    data-testid="product-scrub"
    :data-scrub-progress="progress.toFixed(3)"
  >
    <div class="explode__pin">
      <div class="explode__copy" aria-live="polite">
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
        <div class="explode__frame">
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
const container = ref<HTMLElement | null>(null)
const video = ref<HTMLVideoElement | null>(null)
const reduced = usePrefersReducedMotion()
const isMobile = ref(false)

const { ready, error, markReady, onError } = useScrubVideo(video)

const enabled = computed(() => !reduced.value && !error.value && ready.value)

const { progress } = useScrollScrub({
  container,
  video,
  enabled,
})

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
    to: 0.94,
  },
]

const captionLines = computed(() => {
  if (reduced.value) {
    return [
      {
        id: 'reduced',
        text: 'Airco-units zijn veelzijdig — koelen én verwarmen.',
        opacity: 1,
      },
    ]
  }

  return lines.map((line) => ({
    id: line.id,
    text: line.text,
    emphasis: line.emphasis,
    after: line.after,
    // Lange peak-hold zodat elke zin even blijft staan
    opacity: scrubCaptionOpacity(line.from, line.to, progress.value, 0.72),
  }))
})

onMounted(() => {
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
  background: #fff;
}

.explode--mobile {
  height: 175vh;
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
}

.explode__frame {
  position: relative;
  width: min(820px, 92vw);
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

  .explode__media {
    max-height: 52vh;
  }
}
</style>
