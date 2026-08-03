<template>
  <section id="verwarmen" ref="root" class="heat">
    <div class="heat__inner container">
      <div class="heat__visual" aria-hidden="true">
        <svg class="heat__svg" viewBox="0 0 640 420" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="180" y="80" width="280" height="240" stroke="#d0d0d0" stroke-width="1.2" />
          <path d="M160 80 L320 20 L480 80" stroke="#d0d0d0" stroke-width="1.2" />
          <line x1="180" y1="320" x2="140" y2="360" stroke="#d0d0d0" stroke-width="1" />
          <line x1="460" y1="320" x2="500" y2="360" stroke="#d0d0d0" stroke-width="1" />
          <rect x="210" y="140" width="90" height="110" stroke="#c8c8c8" stroke-width="1" opacity="0.7" />
          <rect
            x="330"
            y="150"
            width="78"
            height="28"
            rx="4"
            fill="#f7f7f7"
            stroke="#bdbdbd"
            stroke-width="1"
          />
          <rect x="520" y="250" width="48" height="70" rx="4" stroke="#c8c8c8" stroke-width="1" />
          <path
            class="heat__flow"
            :style="{ opacity: flowOpacity }"
            d="M544 240 C 520 180, 460 150, 400 164"
            stroke="url(#warmFlow)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-dasharray="4 6"
          />
          <path
            class="heat__flow"
            :style="{ opacity: flowOpacity }"
            d="M368 178 C 360 200, 355 220, 360 240"
            stroke="url(#warmFlow2)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-dasharray="4 6"
          />
          <defs>
            <linearGradient id="warmFlow" x1="544" y1="240" x2="400" y2="164">
              <stop stop-color="#4aa8ff" stop-opacity="0.35" />
              <stop offset="1" stop-color="#ff8a3d" />
            </linearGradient>
            <linearGradient id="warmFlow2" x1="368" y1="178" x2="360" y2="240">
              <stop stop-color="#ff8a3d" />
              <stop offset="1" stop-color="#ff8a3d" stop-opacity="0.2" />
            </linearGradient>
          </defs>
          <circle cx="560" cy="70" r="18" fill="#f0f4f8" stroke="#d7dde5" />
          <text x="200" y="390" fill="#9a9a9a" font-size="12" font-family="Outfit, sans-serif">
            Buiten · winter
          </text>
          <text x="360" y="390" fill="#9a9a9a" font-size="12" font-family="Outfit, sans-serif">
            Binnen · warm
          </text>
        </svg>
      </div>

      <div class="heat__copy">
        <h2 class="heat__title">
          Airco (warmtepomp)<br />
          <span class="heat__vs">tegenover</span><br />
          CV-ketel
        </h2>
        <p class="heat__claim" :style="{ opacity: claimOpacity, transform: `translateY(${(1 - claimOpacity) * 12}px)` }">
          Tot <span class="gradient-season">70%</span> goedkoper dan verwarmen met aardgas.*
        </p>
        <p class="heat__note">* Indicatief bij typische woning; afhankelijk van isolatie en tarieven.</p>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const root = ref<HTMLElement | null>(null)
const { progress } = useScrollProgress(root)

const flowOpacity = computed(() => Math.min(1, Math.max(0, (progress.value - 0.2) / 0.35)))
const claimOpacity = computed(() => Math.min(1, Math.max(0, (progress.value - 0.45) / 0.3)))
</script>

<style scoped>
.heat {
  padding-block: calc(var(--space) * 16) calc(var(--space) * 20);
  background: #fff;
}

.heat__inner {
  display: grid;
  gap: calc(var(--space) * 8);
  align-items: center;
}

.heat__svg {
  width: 100%;
  height: auto;
}

.heat__title {
  margin: 0 0 calc(var(--space) * 5);
  font-size: clamp(32px, 4.5vw, 56px);
  font-weight: 600;
  letter-spacing: -0.04em;
  line-height: 1.1;
}

.heat__vs {
  color: var(--color-ink-soft);
  font-weight: 400;
  font-size: 0.55em;
  letter-spacing: 0.02em;
}

.heat__claim {
  margin: 0 0 12px;
  font-size: clamp(22px, 2.4vw, 32px);
  font-weight: 500;
  letter-spacing: -0.02em;
  line-height: 1.25;
  color: var(--color-ink);
}

.heat__note {
  margin: 0;
  color: var(--color-ink-soft);
  font-size: 12px;
}

@media (min-width: 960px) {
  .heat__inner {
    grid-template-columns: 1.1fr 0.9fr;
  }
}
</style>
