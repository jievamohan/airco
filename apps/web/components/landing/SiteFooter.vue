<template>
  <footer id="contact" class="footer">
    <div class="footer__inner container">
      <div class="footer__cols">
        <div>
          <p class="footer__heading">Airco’s</p>
          <a href="#aircos">Modellen</a>
          <a href="#aircos">Voordelen</a>
        </div>
        <div>
          <p class="footer__heading">Verwarmen</p>
          <a href="#verwarmen">Warmtepomp</a>
          <a href="#verwarmen">Vergelijking</a>
        </div>
        <div>
          <p class="footer__heading">Projecten</p>
          <a href="#projecten">Overzicht</a>
        </div>
        <div>
          <p class="footer__heading">Contact</p>
          <a href="#offerte">Offerte</a>
          <a href="mailto:hallo@klimaatx.nl">hallo@klimaatx.nl</a>
        </div>
        <div>
          <p class="footer__heading">Privacy</p>
          <a href="#offerte">Gegevens</a>
        </div>
        <div>
          <p class="footer__heading">Social</p>
          <a href="#" aria-label="Instagram">Instagram</a>
          <a href="#" aria-label="LinkedIn">LinkedIn</a>
        </div>
      </div>

      <div class="footer__certs" aria-label="Certificeringen">
        <span>STEK</span>
        <span>F-gassen</span>
        <span>InstallQ</span>
        <span>KvK</span>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.footer {
  border-top: 1px solid var(--color-line);
  padding-block: calc(var(--space) * 10) calc(var(--space) * 8);
  background: #fff;
}

.footer__cols {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 28px;
}

.footer__heading {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-ink);
}

.footer__cols a {
  display: block;
  margin-bottom: 8px;
  color: var(--color-ink-muted);
  font-size: 14px;
}

.footer__cols a:hover {
  color: var(--color-ink);
}

.footer__certs {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
  margin-top: calc(var(--space) * 10);
  color: var(--color-ink-soft);
  font-size: 12px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

@media (min-width: 900px) {
  .footer__cols {
    grid-template-columns: repeat(6, 1fr);
  }
}
</style>
