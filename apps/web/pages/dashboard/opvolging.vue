<template>
  <div>
    <div class="dash__head">
      <div>
        <h1 class="dash__title">Opvolging</h1>
        <p class="dash__lede">Wat er gebeurt als een lead de telefoon niet opneemt.</p>
      </div>
    </div>

    <p v-if="flash" class="notice notice--ok" role="status">{{ flash }}</p>
    <p v-if="error" class="notice notice--bad" role="alert">{{ error }}</p>

    <section v-for="sequence in sequences" :key="sequence.key" class="panel">
      <h2 class="panel__title">{{ sequence.name }}</h2>
      <p class="panel__note">{{ sequence.description }}</p>

      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th class="num">#</th>
              <th>Kanaal</th>
              <th>Stap</th>
              <th class="num">Wachttijd</th>
              <th class="num">Actief</th>
              <th class="num"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="step in sequence.steps" :key="step.id">
              <td class="num">{{ step.position }}</td>
              <td>{{ step.channel === 'call' ? 'Bellen' : 'Mailen' }}</td>
              <td><input v-model.trim="step.label" class="cell cell--wide" /></td>
              <td class="num">
                <input v-model.number="step.delay_minutes" type="number" min="0" step="5" class="cell cell--narrow" />
                <span class="small muted"> min</span>
                <span class="small muted" style="display: block">{{ humanDelay(step.delay_minutes) }}</span>
              </td>
              <td class="num"><input v-model="step.active" type="checkbox" /></td>
              <td class="num">
                <button type="button" class="btn btn--ghost btn--small" :disabled="busy === step.id" @click="save(step)">
                  Opslaan
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="small muted" style="margin-top: 12px">
        De wachttijd geldt vanaf het moment dat de vorige stap is uitgevoerd. Belstappen worden altijd naar het
        eerstvolgende belvenster geschoven; die vensters staan onder Instellingen.
      </p>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'dashboard', middleware: 'dashboard-auth' })

type Step = {
  id: number
  position: number
  channel: string
  action: string
  delay_minutes: number
  label: string
  active: boolean
}

type Sequence = { key: string; name: string; description: string | null; active: boolean; steps: Step[] }

const api = useApi()
const sequences = ref<Sequence[]>([])
const busy = ref<number | null>(null)
const flash = ref('')
const error = ref('')

function humanDelay(minutes: number) {
  if (minutes < 60) return `${minutes} minuten`
  if (minutes < 60 * 24) return `${(minutes / 60).toFixed(1).replace('.0', '')} uur`
  return `${(minutes / 1440).toFixed(1).replace('.0', '')} dagen`
}

async function load() {
  error.value = ''
  try {
    sequences.value = (await api.get<{ sequences: Sequence[] }>('/admin/sequences')).sequences
  } catch (e) {
    error.value = (e as ApiError).message
  }
}

async function save(step: Step) {
  busy.value = step.id
  flash.value = ''
  error.value = ''

  try {
    await api.patch(`/admin/sequences/steps/${step.id}`, {
      delay_minutes: step.delay_minutes,
      label: step.label,
      active: step.active,
    })
    flash.value = `Stap ${step.position} is bijgewerkt.`
  } catch (e) {
    error.value = (e as ApiError).message
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>

<style scoped>
.cell {
  font: inherit;
  padding: 5px 8px;
  border: 1px solid var(--dash-line);
  border-radius: 6px;
}

.cell--wide { width: 100%; min-width: 220px; }
.cell--narrow { width: 80px; text-align: right; }
</style>
