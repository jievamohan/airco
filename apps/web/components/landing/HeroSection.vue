<template>
  <section id="top" class="hero">
    <div class="hero__stage">
      <img
        class="hero__bg"
        src="/media/hero.png?v=no-filter"
        alt=""
        width="1536"
        height="1024"
        aria-hidden="true"
      />

      <div class="hero__copy">
        <h1 class="hero__title">
          Perfect klimaat.<br />
          Elk <span class="gradient-season">seizoen</span>.
        </h1>
        <p class="hero__lede">
          Koelen in de zomer.<br />
          Verwarmen in de winter.<br />
          Comfortabel. Duurzaam.<br />
          Voordeliger dan aardgas.
        </p>
        <div class="hero__actions">
          <a href="#offerte" class="btn-ghost">Vrijblijvende offerte</a>
          <a href="#aircos" class="btn-text">Bekijk voordelen <span aria-hidden="true">›</span></a>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.hero {
  width: 100%;
  background: #fff;
  overflow: hidden;
}

.hero__stage {
  position: relative;
  isolation: isolate;
  width: 100%;
  max-width: 1440px;
  margin-inline: auto;
  min-height: clamp(520px, 70vh, 760px);
  display: flex;
  align-items: center;
  padding-left: clamp(24px, 5.5vw, 80px);
  padding-right: clamp(24px, 5.5vw, 80px);
  padding-block: calc(var(--space) * 6) calc(var(--space) * 10);
}

/* Afbeelding = achtergrond van de stage (niet een tweede kolom) */
.hero__bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: right center;
  pointer-events: none;
  user-select: none;
  /* Linker kant van de visual zacht naar wit */
  -webkit-mask-image: linear-gradient(
    to right,
    transparent 0%,
    rgba(0, 0, 0, 0.35) 18%,
    #000 32%,
    #000 100%
  );
  mask-image: linear-gradient(
    to right,
    transparent 0%,
    rgba(0, 0, 0, 0.35) 18%,
    #000 32%,
    #000 100%
  );
}

.hero__copy {
  position: relative;
  z-index: 1;
  max-width: 32rem;
}

.hero__title {
  margin: 0 0 calc(var(--space) * 4);
  font-size: clamp(42px, 5.4vw, 72px);
  font-weight: 600;
  letter-spacing: -0.04em;
  line-height: 1.05;
}

.hero__lede {
  margin: 0 0 calc(var(--space) * 5);
  color: var(--color-ink-muted);
  font-size: clamp(16px, 1.45vw, 20px);
  font-weight: 400;
  line-height: 1.55;
}

.hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

@media (max-width: 959px) {
  .hero__stage {
    min-height: clamp(420px, 78vw, 560px);
    align-items: flex-end;
    padding-block: calc(var(--space) * 4) calc(var(--space) * 6);
  }

  .hero__bg {
    object-position: 70% center;
    -webkit-mask-image: linear-gradient(
      to bottom,
      transparent 0%,
      rgba(0, 0, 0, 0.45) 12%,
      #000 28%,
      #000 100%
    );
    mask-image: linear-gradient(
      to bottom,
      transparent 0%,
      rgba(0, 0, 0, 0.45) 12%,
      #000 28%,
      #000 100%
    );
  }

  .hero__copy {
    max-width: none;
  }
}
</style>
