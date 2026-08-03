<template>
  <section id="projecten" class="projects">
    <div class="projects__head container">
      <h2 class="projects__title">Uitgevoerde projecten</h2>
    </div>
    <div class="projects__strip" role="list">
      <article
        v-for="project in projects"
        :key="project.id"
        class="projects__item"
        role="listitem"
      >
        <div class="projects__media" :style="{ background: project.tone }">
          <span class="projects__ghost" aria-hidden="true">{{ project.place }}</span>
        </div>
        <div class="projects__meta">
          <p>{{ project.place }}</p>
          <p>{{ project.type }}</p>
          <p>{{ project.area }}</p>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
const projects = [
  { id: 1, place: 'Utrecht', type: 'Split-unit woonkamer', area: '72 m²', tone: 'linear-gradient(145deg, #f4f4f4, #e8e8e8)' },
  { id: 2, place: 'Amersfoort', type: 'Multi-split', area: '110 m²', tone: 'linear-gradient(145deg, #f7f5f2, #ebe7e1)' },
  { id: 3, place: 'Hilversum', type: 'Slaapkamer + woonkamer', area: '85 m²', tone: 'linear-gradient(145deg, #f2f5f7, #e4eaee)' },
  { id: 4, place: 'Amsterdam', type: 'Appartement', area: '64 m²', tone: 'linear-gradient(145deg, #f6f6f6, #ececec)' },
  { id: 5, place: 'Haarlem', type: 'Nieuwbouw', area: '98 m²', tone: 'linear-gradient(145deg, #f5f3ef, #e9e4dc)' },
]
</script>

<style scoped>
.projects {
  padding-block: calc(var(--space) * 14) calc(var(--space) * 16);
}

.projects__title {
  margin: 0 0 calc(var(--space) * 6);
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 600;
  letter-spacing: -0.03em;
}

.projects__strip {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(240px, 1fr);
  gap: 2px;
  overflow-x: auto;
  scrollbar-width: none;
}

.projects__strip::-webkit-scrollbar {
  display: none;
}

.projects__item {
  position: relative;
  min-height: 320px;
}

.projects__media {
  height: 100%;
  min-height: 320px;
  display: grid;
  place-items: center;
}

.projects__ghost {
  font-size: 14px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgba(0, 0, 0, 0.18);
  font-weight: 500;
}

.projects__meta {
  position: absolute;
  left: 20px;
  bottom: 20px;
  opacity: 0;
  transform: translateY(6px);
  transition: opacity 0.25s ease, transform 0.25s ease;
  color: var(--color-ink);
  font-size: 13px;
  line-height: 1.45;
}

.projects__meta p {
  margin: 0;
}

.projects__item:hover .projects__meta,
.projects__item:focus-within .projects__meta {
  opacity: 1;
  transform: translateY(0);
}

@media (max-width: 767px) {
  .projects__meta {
    opacity: 1;
    transform: none;
  }
}
</style>
