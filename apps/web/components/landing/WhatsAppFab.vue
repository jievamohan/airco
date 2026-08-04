<template>
  <a
    class="whatsapp-fab"
    :class="{ 'is-branded': branded }"
    href="https://wa.me/31624542592"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="WhatsApp"
    data-testid="whatsapp-fab"
  >
    <svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true" focusable="false">
      <path
        fill="currentColor"
        d="M12.04 2C6.58 2 2.15 6.4 2.15 11.84c0 1.99.59 3.84 1.61 5.4L2 22l4.92-1.7a9.9 9.9 0 0 0 5.12 1.4h.01c5.46 0 9.89-4.4 9.89-9.86C21.94 6.4 17.5 2 12.04 2zm5.78 13.99c-.24.68-1.4 1.24-1.93 1.32-.5.07-1.13.1-1.82-.11-.42-.13-.96-.31-1.66-.61-2.92-1.26-4.82-4.2-4.97-4.39-.14-.2-1.2-1.6-1.2-3.05 0-1.45.76-2.16 1.03-2.45.27-.29.59-.36.79-.36h.57c.18 0 .43-.07.67.51.24.6.83 2.03.9 2.18.07.15.12.32.02.52-.1.2-.15.32-.29.5-.15.17-.3.38-.43.51-.15.15-.3.31-.13.6.17.3.76 1.25 1.63 2.03 1.12 1 2.07 1.31 2.36 1.46.3.15.47.12.64-.07.17-.2.74-.86.94-1.15.2-.3.4-.24.67-.15.27.1 1.72.81 2.02.96.3.15.5.22.57.34.08.13.08.74-.16 1.42z"
      />
    </svg>
  </a>
</template>

<script setup lang="ts">
const branded = ref(false)

onMounted(() => {
  const heating = document.getElementById('verwarmen')
  if (!heating) return

  const update = () => {
    // Brand once Heating reaches the lower viewport; reverse when scrolling back up
    branded.value = heating.getBoundingClientRect().top <= window.innerHeight * 0.85
  }

  update()
  window.addEventListener('scroll', update, { passive: true })
  window.addEventListener('resize', update, { passive: true })

  onUnmounted(() => {
    window.removeEventListener('scroll', update)
    window.removeEventListener('resize', update)
  })
})
</script>

<style scoped>
.whatsapp-fab {
  position: fixed;
  z-index: 60;
  right: max(16px, env(safe-area-inset-right, 0px));
  bottom: max(24px, calc(16px + env(safe-area-inset-bottom, 0px)));
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  color: var(--color-ink);
  background: transparent;
  border: none;
  filter: drop-shadow(0 1px 2px rgba(255, 255, 255, 0.85)) drop-shadow(0 1px 3px rgba(0, 0, 0, 0.28));
  transition:
    color 0.85s ease,
    opacity 0.2s ease;
}

.whatsapp-fab.is-branded {
  color: #25d366;
}

.whatsapp-fab:hover {
  opacity: 0.72;
}

.whatsapp-fab:focus-visible {
  outline: 2px solid var(--color-ink);
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .whatsapp-fab {
    transition: opacity 0.2s ease;
  }
}
</style>
