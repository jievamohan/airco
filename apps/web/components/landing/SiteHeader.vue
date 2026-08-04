<template>
  <header class="site-header" :class="{ 'is-scrolled': scrolled }">
    <div class="site-header__inner container">
      <a href="#top" class="site-header__logo" aria-label="KlimaatX home">KlimaatX</a>

      <nav class="site-header__nav" aria-label="Hoofdmenu">
        <a href="#aircos">Airco’s</a>
        <a href="#verwarmen">Verwarmen</a>
        <a href="#werkwijze">Werkwijze</a>
        <a href="#projecten">Projecten</a>
        <a href="#reviews">Reviews</a>
        <a href="#contact">Contact</a>
      </nav>

      <a href="#offerte" class="btn-primary site-header__cta">Vrijblijvende offerte</a>

      <button
        type="button"
        class="site-header__menu"
        :aria-expanded="open"
        aria-controls="mobile-nav"
        @click="open = !open"
      >
        <span class="sr-only">Menu</span>
        <span class="site-header__burger" :class="{ open }" />
      </button>
    </div>

    <div v-show="open" id="mobile-nav" class="site-header__drawer">
      <a href="#aircos" @click="open = false">Airco’s</a>
      <a href="#verwarmen" @click="open = false">Verwarmen</a>
      <a href="#werkwijze" @click="open = false">Werkwijze</a>
      <a href="#projecten" @click="open = false">Projecten</a>
      <a href="#reviews" @click="open = false">Reviews</a>
      <a href="#contact" @click="open = false">Contact</a>
      <a href="#offerte" class="btn-primary" @click="open = false">Vrijblijvende offerte</a>
    </div>
  </header>
</template>

<script setup lang="ts">
const scrolled = ref(false)
const open = ref(false)

onMounted(() => {
  const onScroll = () => {
    scrolled.value = window.scrollY > 8
  }
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
  onUnmounted(() => window.removeEventListener('scroll', onScroll))
})
</script>

<style scoped>
.site-header {
  position: sticky;
  top: 0;
  z-index: 50;
  height: var(--header-h);
  background: transparent;
  transition: background 0.25s ease, backdrop-filter 0.25s ease;
}

.site-header.is-scrolled {
  background: rgba(255, 255, 255, 0.86);
  backdrop-filter: blur(12px);
}

.site-header__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  height: var(--header-h);
}

.site-header__logo {
  font-weight: 600;
  font-size: 18px;
  letter-spacing: -0.02em;
  flex: 0 0 auto;
}

.site-header__nav {
  display: none;
  gap: 28px;
  flex: 1 1 auto;
  justify-content: center;
}

.site-header__nav a {
  color: var(--color-ink-muted);
  font-size: 14px;
  font-weight: 400;
  transition: color 0.2s ease;
}

.site-header__nav a:hover {
  color: var(--color-ink);
}

.site-header__cta {
  display: none;
  flex: 0 0 auto;
}

.site-header__menu {
  flex: 0 0 auto;
  width: 40px;
  height: 40px;
  border: none;
  background: transparent;
  cursor: pointer;
  position: relative;
}

.site-header__burger,
.site-header__burger::before,
.site-header__burger::after {
  display: block;
  width: 18px;
  height: 1.5px;
  background: var(--color-ink);
  position: absolute;
  left: 11px;
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.site-header__burger {
  top: 19px;
}

.site-header__burger::before,
.site-header__burger::after {
  content: '';
  left: 0;
}

.site-header__burger::before {
  top: -6px;
}

.site-header__burger::after {
  top: 6px;
}

.site-header__burger.open {
  background: transparent;
}

.site-header__burger.open::before {
  top: 0;
  transform: rotate(45deg);
}

.site-header__burger.open::after {
  top: 0;
  transform: rotate(-45deg);
}

.site-header__drawer {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px 16px 32px;
  background: #fff;
  border-bottom: 1px solid var(--color-line);
}

@media (max-width: 767px) {
  .site-header {
    width: 100%;
  }

  .site-header__inner {
    max-width: 100%;
  }
}

.site-header__drawer a {
  font-size: 16px;
  color: var(--color-ink-muted);
}

@media (min-width: 1024px) {
  .site-header__nav {
    display: flex;
  }

  .site-header__cta {
    display: inline-flex;
  }

  .site-header__menu,
  .site-header__drawer {
    display: none;
  }
}
</style>
