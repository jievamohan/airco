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

    <!--
      Los van het instellingenformulier: dit praat met ElevenLabs en hoort niet
      mee te liften op "Instellingen opslaan".
    -->
    <section class="panel" style="margin-top: 24px">
      <h2 class="panel__title">Agent bij ElevenLabs</h2>
      <p class="panel__note">
        Zet de gespreksprompt en alle achttien dataverzamelingsvelden bij ElevenLabs
        neer, uit het runbook. Vul hierboven eerst de API-sleutel en het voice_id in
        en sla op. Bestaat de agent al, dan wordt hij bijgewerkt in plaats van dubbel
        aangemaakt.
      </p>

      <p v-if="agentFlash" class="notice notice--ok" role="status">{{ agentFlash }}</p>
      <p v-if="agentError" class="notice notice--bad" role="alert">{{ agentError }}</p>

      <div class="actions" style="margin-top: 20px">
        <button type="button" class="btn" :disabled="agentBusy" @click="syncAgent">
          {{ agentBusy ? 'Bezig bij ElevenLabs…' : 'Agent aanmaken of bijwerken' }}
        </button>
        <span class="small muted" style="align-self: center">
          Dit koppelt geen telefoonnummer en zet de webhook niet aan.
        </span>
      </div>
    </section>

    <!--
      Een eigen formulier, niet een sectie van het bovenstaande: dit gaat naar
      een ander endpoint en mag niet meeliften op "Instellingen opslaan".
    -->
    <form class="panel" style="margin-top: 24px" @submit.prevent="changePassword">
      <h2 class="panel__title">Wachtwoord wijzigen</h2>
      <p class="panel__note">
        Uw huidige wachtwoord is nodig. Na het wijzigen worden andere apparaten
        uitgelogd; dit apparaat blijft ingelogd.
      </p>

      <p v-if="passwordFlash" class="notice notice--ok" role="status">{{ passwordFlash }}</p>
      <p v-if="passwordError" class="notice notice--bad" role="alert">{{ passwordError }}</p>

      <div class="form-grid">
        <label>
          <span>Huidig wachtwoord</span>
          <input v-model="passwordForm.current_password" type="password" autocomplete="current-password" />
          <em v-if="passwordErrors.current_password" class="small notice--bad">{{ passwordErrors.current_password }}</em>
        </label>

        <label>
          <span>Nieuw wachtwoord</span>
          <input v-model="passwordForm.password" type="password" autocomplete="new-password" />
          <em v-if="passwordErrors.password" class="small notice--bad">{{ passwordErrors.password }}</em>
          <em v-else class="small muted">Minimaal 12 tekens.</em>
        </label>

        <label>
          <span>Nieuw wachtwoord herhalen</span>
          <input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" />
        </label>
      </div>

      <div class="actions" style="margin-top: 20px">
        <button type="submit" class="btn" :disabled="passwordBusy">
          {{ passwordBusy ? 'Bezig…' : 'Wachtwoord wijzigen' }}
        </button>
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

// -------------------------------------------------------------- voice agent

const agentBusy = ref(false)
const agentFlash = ref('')
const agentError = ref('')

async function syncAgent() {
  agentBusy.value = true
  agentFlash.value = ''
  agentError.value = ''

  try {
    const result = await api.post<{ agent_id: string; bijgewerkt: boolean; velden: number }>(
      '/admin/voice/agent-sync',
    )

    agentFlash.value = result.bijgewerkt
      ? `Agent bijgewerkt (${result.velden} velden). Id: ${result.agent_id}`
      : `Agent aangemaakt (${result.velden} velden). Id: ${result.agent_id}`

    // Het id is server-side vastgelegd; opnieuw laden zodat het veld hierboven
    // klopt met wat er nu staat.
    await load()
  } catch (e) {
    agentError.value = (e as ApiError).message
  } finally {
    agentBusy.value = false
  }
}

// ---------------------------------------------------------------- wachtwoord

type PasswordForm = {
  current_password: string
  password: string
  password_confirmation: string
}

const leegWachtwoordFormulier = (): PasswordForm => ({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const passwordForm = reactive<PasswordForm>(leegWachtwoordFormulier())
const passwordErrors = reactive<Partial<Record<keyof PasswordForm, string>>>({})
const passwordBusy = ref(false)
const passwordFlash = ref('')
const passwordError = ref('')

function clearPasswordErrors() {
  ;(Object.keys(passwordErrors) as (keyof PasswordForm)[]).forEach((k) => {
    delete passwordErrors[k]
  })
}

async function changePassword() {
  passwordBusy.value = true
  passwordFlash.value = ''
  passwordError.value = ''
  clearPasswordErrors()

  // De server is de baas over wat mag; dit voorkomt alleen een ronde over het
  // netwerk voor iets wat je hier al ziet.
  if (passwordForm.password !== passwordForm.password_confirmation) {
    passwordErrors.password = 'De twee nieuwe wachtwoorden zijn niet gelijk.'
    passwordBusy.value = false
    return
  }

  try {
    await api.post('/admin/password', { ...passwordForm })
    Object.assign(passwordForm, leegWachtwoordFormulier())
    passwordFlash.value = 'Uw wachtwoord is gewijzigd. Andere apparaten zijn uitgelogd.'
  } catch (e) {
    const err = e as ApiError

    if (err.errors) {
      for (const [veld, meldingen] of Object.entries(err.errors)) {
        if (veld in passwordForm) passwordErrors[veld as keyof PasswordForm] = meldingen[0]
      }
      passwordError.value = 'Controleer de gemarkeerde velden.'
    } else {
      passwordError.value = err.message
    }
  } finally {
    passwordBusy.value = false
  }
}

onMounted(load)
</script>
