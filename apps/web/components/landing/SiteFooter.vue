<template>
  <footer id="contact" class="footer">
    <div class="footer__inner container">
      <nav class="footer__legal" aria-label="Juridisch">
        <NuxtLink to="/privacy">Privacyverklaring</NuxtLink>
        <NuxtLink to="/algemene-voorwaarden">Algemene voorwaarden</NuxtLink>
      </nav>

      <div class="footer__contact">
        <a href="tel:+31624542592">06 24542592</a>
        <a href="mailto:sales@lex.solar">sales@lex.solar</a>
      </div>

      <div class="footer__social" aria-label="Social media">
        <a
          class="footer__social-btn"
          href="https://www.facebook.com/solarlexramsri"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Facebook"
        >
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
            <path
              fill="currentColor"
              d="M14 8h2.5V5.5H14c-1.9 0-3.5 1.6-3.5 3.5V12H8v2.5h2.5V22H14v-7.5h2.3L17 12h-3V9c0-.6.4-1 1-1z"
            />
          </svg>
        </a>
        <a
          class="footer__social-btn"
          href="https://www.instagram.com/solarlex/"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Instagram"
        >
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
            <path
              fill="currentColor"
              d="M12 7.2A4.8 4.8 0 1 0 16.8 12 4.8 4.8 0 0 0 12 7.2zm0 7.9A3.1 3.1 0 1 1 15.1 12 3.1 3.1 0 0 1 12 15.1z"
            />
            <path
              fill="currentColor"
              d="M17.5 2h-11A4.5 4.5 0 0 0 2 6.5v11A4.5 4.5 0 0 0 6.5 22h11a4.5 4.5 0 0 0 4.5-4.5v-11A4.5 4.5 0 0 0 17.5 2zm2.8 15.5a2.8 2.8 0 0 1-2.8 2.8h-11a2.8 2.8 0 0 1-2.8-2.8v-11a2.8 2.8 0 0 1 2.8-2.8h11a2.8 2.8 0 0 1 2.8 2.8z"
            />
            <circle fill="currentColor" cx="17.7" cy="6.3" r="1.15" />
          </svg>
        </a>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.footer {
  border-top: 1px solid var(--color-line);
  padding-block: calc(var(--space) * 8);
  background: #fff;
}

.footer__inner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 20px 32px;
}

.footer__legal,
.footer__contact {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 20px;
}

.footer__legal a,
.footer__contact a {
  color: var(--color-ink-muted);
  font-size: 14px;
  letter-spacing: -0.01em;
  transition: color 0.2s ease;
}

.footer__legal a:hover,
.footer__contact a:hover {
  color: var(--color-ink);
}

.footer__social {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
}

.footer__social-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: 1px solid var(--color-line);
  border-radius: 8px;
  color: var(--color-ink);
  background: transparent;
  transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.footer__social-btn:hover {
  border-color: #cfcfcf;
  background: #fafafa;
}

.footer__social-btn:focus-visible {
  outline: 2px solid var(--color-ink);
  outline-offset: 2px;
}

@media (max-width: 699px) {
  .footer__inner {
    flex-direction: column;
    align-items: flex-start;
  }

  .footer__social {
    margin-left: 0;
  }
}
</style>
