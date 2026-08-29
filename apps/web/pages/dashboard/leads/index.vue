<template>
  <div>
    <div class="dash__head">
      <div>
        <h1 class="dash__title">Leads</h1>
        <p class="dash__lede">Alle aanvragen met hun plek in de funnel.</p>
      </div>
    </div>

    <div class="filters">
      <input v-model.trim="search" type="search" placeholder="Zoek op naam, mail, telefoon of plaats" @keyup.enter="load(1)" />
      <select v-model="status" @change="load(1)">
        <option value="">Alle statussen</option>
        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <select v-model="source" @change="load(1)">
        <option value="">Alle bronnen</option>
        <option value="web_form">Formulier op de website</option>
        <option value="mailbox">Mailbox</option>
        <option value="api">Externe koppeling</option>
        <option value="manual">Handmatig</option>
      </select>
      <button type="button" class="btn btn--ghost" @click="load(1)">Zoeken</button>
      <button v-if="search || status || source" type="button" class="btn btn--ghost" @click="reset">Wissen</button>
    </div>

    <p v-if="error" class="notice notice--bad" role="alert">{{ error }}</p>

    <section class="panel">
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Naam</th>
              <th>Plaats</th>
              <th>Status</th>
              <th>Bron</th>
              <th class="num">kW</th>
              <th class="num">Offerte</th>
              <th class="num">Belpogingen</th>
              <th>Volgende actie</th>
              <th>Binnengekomen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="lead in rows" :key="lead.uuid" class="row" @click="open(lead.uuid)">
              <td><strong>{{ lead.name }}</strong></td>
              <td>{{ lead.city ?? '—' }}</td>
              <td><span class="badge" :class="`badge--${lead.status}`">{{ lead.status_label }}</span></td>
              <td class="muted">{{ sourceLabels[lead.source] ?? lead.source }}</td>
              <td class="num">{{ lead.estimated_kw ? fmt.number(lead.estimated_kw, 1) : '—' }}</td>
              <td class="num">{{ fmt.euro(lead.quote_total_cents) }}</td>
              <td class="num">{{ lead.call_attempts }}</td>
              <td class="muted">{{ lead.next_action_at ? fmt.relative(lead.next_action_at) : '—' }}</td>
              <td class="muted">{{ fmt.dateTime(lead.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="!rows.length && !error" class="empty">Geen leads gevonden met deze filters.</p>

      <div v-if="meta && meta.last_page > 1" class="actions" style="margin-top: 16px">
        <button type="button" class="btn btn--ghost btn--small" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
          Vorige
        </button>
        <span class="small muted" style="align-self: center">Pagina {{ meta.current_page }} van {{ meta.last_page }} · {{ meta.total }} leads</span>
        <button type="button" class="btn btn--ghost btn--small" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">
          Volgende
        </button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'dashboard', middleware: 'dashboard-auth' })

type LeadRow = {
  uuid: string
  name: string
  city: string | null
  status: string
  status_label: string
  source: string
  estimated_kw: number | null
  quote_total_cents: number | null
  call_attempts: number
  next_action_at: string | null
  created_at: string | null
}

type Meta = { current_page: number; last_page: number; total: number }

const api = useApi()
const fmt = useDashboardFormat()

const rows = ref<LeadRow[]>([])
const meta = ref<Meta | null>(null)
const statuses = ref<Array<{ value: string; label: string }>>([])
const error = ref('')

const search = ref('')
const status = ref('')
const source = ref('')

const sourceLabels: Record<string, string> = {
  web_form: 'Website',
  mailbox: 'Mailbox',
  api: 'Koppeling',
  manual: 'Handmatig',
}

function open(uuid: string) {
  navigateTo(`/dashboard/leads/${uuid}`)
}

function reset() {
  search.value = ''
  status.value = ''
  source.value = ''
  load(1)
}

async function load(page = 1) {
  error.value = ''
  const params = new URLSearchParams({ page: String(page) })
  if (search.value) params.set('search', search.value)
  if (status.value) params.set('status', status.value)
  if (source.value) params.set('source', source.value)

  try {
    const result = await api.get<{ data: LeadRow[]; meta: Meta }>(`/admin/leads?${params}`)
    rows.value = result.data
    meta.value = result.meta
  } catch (e) {
    error.value = (e as ApiError).message
  }
}

onMounted(async () => {
  try {
    statuses.value = (await api.get<{ statuses: Array<{ value: string; label: string }> }>('/admin/leads/statuses')).statuses
  } catch {
    /* filters blijven dan leeg; de lijst werkt gewoon */
  }
  await load(1)
})
</script>

<style scoped>
.row { cursor: pointer; }
</style>
