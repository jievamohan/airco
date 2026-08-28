<template>
  <div class="dash">
    <header class="dash__bar">
      <NuxtLink to="/dashboard" class="dash__brand">KlimaatX CRM</NuxtLink>
      <nav class="dash__nav">
        <NuxtLink v-for="item in nav" :key="item.to" :to="item.to" :class="{ 'is-active': isActive(item.to) }">
          {{ item.label }}
        </NuxtLink>
      </nav>
      <button type="button" class="btn btn--ghost btn--small" @click="logout">Uitloggen</button>
    </header>

    <main class="dash__main">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
useHead({
  title: 'KlimaatX CRM',
  meta: [{ name: 'robots', content: 'noindex, nofollow' }],
})

const route = useRoute()
const api = useApi()

const nav = [
  { to: '/dashboard', label: 'Overzicht' },
  { to: '/dashboard/leads', label: 'Leads' },
  { to: '/dashboard/catalogus', label: 'Catalogus' },
  { to: '/dashboard/opvolging', label: 'Opvolging' },
  { to: '/dashboard/instellingen', label: 'Instellingen' },
]

function isActive(to: string) {
  return to === '/dashboard' ? route.path === '/dashboard' : route.path.startsWith(to)
}

async function logout() {
  try {
    await api.post('/admin/logout')
  } catch {
    /* de token is lokaal sowieso weg */
  }
  writeToken(null)
  await navigateTo('/dashboard/login')
}
</script>
