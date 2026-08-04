<template>
  <div class="legal">
    <LandingSiteHeader />
    <main class="legal__main container">
      <h1>Algemene voorwaarden</h1>
      <p>
        Onze algemene voorwaarden zijn beschikbaar op de website van Solar LEX.
      </p>
      <p>
        <a
          href="https://www.solarlex.nl/algemene-voorwaarden/"
          target="_blank"
          rel="noopener noreferrer"
        >
          Bekijk de algemene voorwaarden
        </a>
      </p>
      <p>
        Vragen? Mail
        <a href="mailto:sales@lex.solar">sales@lex.solar</a>
        of bel
        <a href="tel:+31624542592">06 24542592</a>.
      </p>
      <p>
        <NuxtLink to="/">Terug naar home</NuxtLink>
      </p>
    </main>
    <LandingSiteFooter />
  </div>
</template>

<style scoped>
.legal {
  background: #fff;
  min-height: 100vh;
}

.legal__main {
  padding-block: calc(var(--space) * 16) calc(var(--space) * 18);
  max-width: 720px;
}

.legal__main h1 {
  margin: 0 0 calc(var(--space) * 6);
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 600;
  letter-spacing: -0.03em;
}

.legal__main p {
  margin: 0 0 16px;
  color: var(--color-ink-muted);
  font-size: 16px;
  line-height: 1.55;
}

.legal__main a {
  color: var(--color-ink);
  text-decoration: underline;
  text-underline-offset: 3px;
}
</style>
