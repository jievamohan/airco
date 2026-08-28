<template>
  <div class="dash login">
    <form class="login__box panel" @submit.prevent="submit">
      <h1 class="panel__title">Inloggen op het CRM</h1>
      <p class="panel__note">Alleen voor medewerkers van KlimaatX.</p>

      <p v-if="error" class="notice notice--bad" role="alert">{{ error }}</p>

      <div class="form-grid">
        <label class="full">
          <span>E-mailadres</span>
          <input v-model.trim="email" type="email" autocomplete="username" required />
        </label>
        <label class="full">
          <span>Wachtwoord</span>
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>
      </div>

      <button type="submit" class="btn login__submit" :disabled="busy">
        {{ busy ? 'Bezig…' : 'Inloggen' }}
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import type { ApiError } from '~/composables/useApi'

definePageMeta({ layout: false })

useHead({
  title: 'Inloggen — KlimaatX CRM',
  meta: [{ name: 'robots', content: 'noindex, nofollow' }],
})

const api = useApi()
const email = ref('')
const password = ref('')
const busy = ref(false)
const error = ref('')

async function submit() {
  busy.value = true
  error.value = ''

  try {
    const result = await api.post<{ token: string }>('/admin/login', { email: email.value, password: password.value }, false)
    writeToken(result.token)
    await navigateTo('/dashboard')
  } catch (e) {
    const err = e as ApiError
    error.value = err.errors?.email?.[0] ?? err.message
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.login {
  display: grid;
  place-items: center;
  padding: 8vh 20px;
}

.login__box {
  width: min(420px, 100%);
  display: grid;
  gap: 14px;
}

.login__submit {
  justify-self: start;
  margin-top: 4px;
}
</style>
