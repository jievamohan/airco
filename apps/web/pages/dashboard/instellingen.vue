<template>
  <div>
    <div class="dash__head">
      <div>
        <h1 class="dash__title">Instellingen</h1>
        <p class="dash__lede">Koppelingen, prijsstelling en de manier waarop de agent werkt.</p>
      </div>
    </div>

    <p v-if="flash" class="notice notice--ok" role="status">{{ flash }}</p>
    <p v-if="error" class="notice notice--bad" role="alert">{{ error }}</p>

    <form @submit.prevent="save">
      <section v-for="(settings, group) in groups" :key="group" class="panel">
        <h2 class="panel__title">{{ groupLabels[group] ?? group }}</h2>
        <p v-if="groupNotes[group]" class="panel__note">{{ groupNotes[group] }}</p>

        <div class="form-grid">
          <label v-for="setting in settings" :key="setting.key" :class="{ full: setting.type === 'bool' }">
            <span>
              {{ setting.label }}
              <em v-if="setting.is_secret" class="muted">— wordt niet getoond</em>
            </span>

            <input
              v-if="setting.type === 'bool'"
              v-model="values[setting.key]"
              type="checkbox"
            />
            <input
              v-else-if="setting.type === 'int' || setting.type === 'float'"
              v-model="values[setting.key]"
              type="number"
              :step="setting.type === 'float' ? '0.1' : '1'"
              :placeholder="placeholder(setting)"
            />
            <input
              v-else
              v-model="values[setting.key]"
              :type="setting.is_secret ? 'password' : 'text'"
              autocomplete="off"
              :placeholder="placeholder(setting)"
            />

            <em v-if="setting.description" class="small muted">{{ setting.description }}</em>
          </label>
        </div>
      </section>

      <div class="actions" style="margin-top: 20px">
        <button type="submit" class="btn" :disabled="busy">{{ busy ? 'Bezig…' : 'Instellingen opslaan' }}</button>
        <span class="small muted" style="align-self: center">
          Leeg laten betekent: de standaardwaarde uit de serverconfiguratie gebruiken.
        </span>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import type { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'dashboard', middleware: 'dashboard-auth' })

type Setting = {
  key: string
  label: string
  description: string | null
  type: string
  is_secret: boolean
  is_set: boolean
  value: unknown
  effective: unknown
}

const api = useApi()
const groups = ref<Record<string, Setting[]>>({})
const values = reactive<Record<string, any>>({})
const busy = ref(false)
const flash = ref('')
const error = ref('')

const groupLabels: Record<string, string> = {
  bedrijf: 'Bedrijfsgegevens',
  werking: 'Werking van de agent',
  prijsstelling: 'Prijsstelling',
  mailbox: 'Mailbox-intake',
  voice: 'Voice agent (ElevenLabs)',
  agenda: 'Agenda',
}

const groupNotes: Record<string, string> = {
  werking: 'In proefmodus loopt de hele workflow door, maar wordt er niet echt gebeld, gemaild of geboekt.',
  prijsstelling: 'Deze waarden gelden voor elke nieuwe offerte. Artikelprijzen zelf staan onder Catalogus.',
  voice: 'De stem, taal en gespreksprompt beheer je in het ElevenLabs-dashboard; hier leggen we alleen de koppeling.',
  agenda: 'Kies google, apple of none. Bij none staat de afspraak alleen in het CRM en gaat er een ICS-bijlage mee.',
}

function placeholder(setting: Setting) {
  if (setting.is_secret) return setting.is_set ? '••••••••' : 'nog niet ingesteld'
  if (setting.value === null && setting.effective !== null && setting.effective !== undefined) {
    return `standaard: ${String(setting.effective)}`
  }
  return ''
}

async function load() {
  error.value = ''
  try {
    const result = await api.get<{ groups: Record<string, Setting[]> }>('/admin/settings')
    groups.value = result.groups

    for (const settings of Object.values(result.groups)) {
      for (const setting of settings) {
        values[setting.key] = setting.type === 'bool' ? Boolean(setting.value) : (setting.value ?? '')
      }
    }
  } catch (e) {
    error.value = (e as ApiError).message
  }
}

async function save() {
  busy.value = true
  flash.value = ''
  error.value = ''

  // Geheimen die de gebruiker niet heeft aangeraakt, sturen we niet mee:
  // anders zou een lege invoer een bestaande sleutel wissen.
  const payload: Record<string, any> = {}

  for (const settings of Object.values(groups.value)) {
    for (const setting of settings) {
      const value = values[setting.key]
      if (setting.is_secret && (value === '' || value === null)) continue
      payload[setting.key] = value === '' ? null : value
    }
  }

  try {
    await api.patch('/admin/settings', { values: payload })
    flash.value = 'De instellingen zijn opgeslagen.'
    await load()
  } catch (e) {
    const err = e as ApiError
    error.value = err.errors ? Object.values(err.errors).flat().join(' ') : err.message
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>
