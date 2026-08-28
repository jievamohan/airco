<template>
  <div class="app">
    <!--
      NuxtLayout is nodig zodat `definePageMeta({ layout: … })` daadwerkelijk
      iets doet. Zonder deze wrapper wordt de dashboardlayout stil genegeerd en
      mist het CRM zijn navigatiebalk. De landingspagina's declareren geen
      layout en vallen door naar de slot-inhoud.
    -->
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>
