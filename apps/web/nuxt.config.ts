export default defineNuxtConfig({
  compatibilityDate: '2025-01-15',
  devtools: { enabled: false },
  css: ['~/assets/css/main.css'],
  nitro: {
    preset: 'static',
  },
  app: {
    head: {
      title: 'KlimaatX — Perfect klimaat. Elk seizoen.',
      htmlAttrs: { lang: 'nl' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        {
          name: 'description',
          content:
            'Koelen in de zomer. Verwarmen in de winter. Comfortabel, duurzaam en voordeliger dan aardgas.',
        },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap',
        },
      ],
    },
  },
})
