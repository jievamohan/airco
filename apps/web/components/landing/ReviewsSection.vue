<template>
  <section id="reviews" class="reviews">
    <div class="reviews__inner container">
      <h2 class="reviews__title">Wat klanten zeggen</h2>
      <div class="reviews__grid">
        <blockquote v-for="review in reviews" :key="review.id" class="reviews__quote">
          <p>“{{ review.text }}”</p>
          <footer>{{ review.name }} · {{ review.place }}</footer>
        </blockquote>
      </div>
      <div class="reviews__trust" aria-label="Beoordelingen">
        <span>Google 4.9</span>
        <span>InstallQ 9.7</span>
        <span>Trustpilot 4.2</span>
        <span>STEK 5.0</span>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const reviews = [
  {
    id: 1,
    text: 'Stil, strak geplaatst, en in de winter merkbaar goedkoper dan de ketel.',
    name: 'Marieke',
    place: 'Utrecht',
  },
  {
    id: 2,
    text: 'Alles netjes uitgelegd. De installatie voelde precies en rustig.',
    name: 'Thomas',
    place: 'Amersfoort',
  },
  {
    id: 3,
    text: 'Eindelijk koel én warm zonder gedoe. Precies wat we zochten.',
    name: 'Sanne',
    place: 'Haarlem',
  },
]
</script>

<style scoped>
.reviews {
  padding-block: calc(var(--space) * 16) calc(var(--space) * 18);
}

.reviews__title {
  margin: 0 0 calc(var(--space) * 10);
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 600;
  letter-spacing: -0.03em;
}

.reviews__grid {
  display: grid;
  gap: calc(var(--space) * 8);
}

.reviews__quote {
  margin: 0;
}

.reviews__quote p {
  margin: 0 0 16px;
  font-size: clamp(22px, 2.4vw, 30px);
  font-weight: 400;
  letter-spacing: -0.02em;
  line-height: 1.35;
  color: var(--color-ink);
}

.reviews__quote footer {
  color: var(--color-ink-soft);
  font-size: 14px;
}

.reviews__trust {
  display: flex;
  flex-wrap: wrap;
  gap: 28px;
  margin-top: calc(var(--space) * 12);
  color: var(--color-ink-soft);
  font-size: 13px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

@media (min-width: 900px) {
  .reviews__grid {
    grid-template-columns: repeat(3, 1fr);
    gap: calc(var(--space) * 6);
  }
}
</style>
