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
        <option value="web_form">Website — versie 1</option>
        <option value="web_form_v2">Website — versie 2</option>
        <option value="mailbox">Mailbox</option>
        <option value="api">Externe koppeling</option>
        <option value="manual">Handmatig</option>
      </select>
      <button type="button" class="btn btn--ghost" @click="load(1)">Zoeken</button>
      <button v-if="search || status || source" type="button" class="btn btn--ghost" @click="reset">Wissen</button>
    </div>

    <p v-if="error" class="notice notice--bad" role="alert">{{ error }}</p>

    <section class="panel panel--bare">
      <!--
        Op een telefoon een kaart per lead: naam en status eerst, dan het
        bedrag, dan wanneer er iets moet gebeuren. De tabel hieronder blijft
        voor een breed scherm, waar je juist kolommen wilt vergelijken.
      -->
      <div v-if="compact" class="cards">
        <button
          v-for="lead in rows"
          :key="lead.uuid"
          type="button"
          class="card lead"
          :class="`lead--${lead.status}`"
          @click="open(lead.uuid)"
        >
          <span class="lead__stripe" aria-hidden="true" />

          <span class="lead__body">
            <span class="lead__top">
              <span class="card__title">{{ lead.name }}</span>
              <span class="lead__amount" :class="{ 'lead__amount--none': !lead.quote_total_cents }">
                {{ lead.quote_total_cents ? fmt.euroRound(lead.quote_total_cents) : 'nog geen bedrag' }}
                <span v-if="lead.quote_total_cents && lead.quote_binding === false" class="lead__amount-soort">indicatie</span>
              </span>
            </span>

            <span class="lead__meta">
              <span class="lead__status">{{ lead.status_label }}</span> ·
              {{ lead.city ?? 'plaats onbekend' }} ·
              {{ lead.estimated_kw ? `${fmt.number(lead.estimated_kw, 1)} kW` : 'nog niet berekend' }}
            </span>

            <span class="lead__foot">
              <span v-if="lead.next_action_at" class="chip" :class="{ 'chip--due': isOverdue(lead.next_action_at) }">
                <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6">
                  <circle cx="8" cy="8" r="6.4" />
                  <path d="M8 4.6V8l2.4 1.6" stroke-linecap="round" />
                </svg>
                {{ fmt.relative(lead.next_action_at) }}
              </span>
              <span v-else class="chip">geen actie gepland</span>

              <span class="lead__tail">
                {{ lead.call_attempts ? `${lead.call_attempts}× gebeld` : 'nog niet gebeld' }}
              </span>
            </span>
          </span>
        </button>
      </div>

      <div v-else class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Naam</th>
              <th>Plaats</th>
              <th>Status</th>
              <th>Bron</th>
              <th class="num">kW</th>
              <th class="num">Bedrag</th>
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
              <td class="num">
                {{ fmt.euro(lead.quote_total_cents) }}
                <span v-if="lead.quote_total_cents && lead.quote_binding === false" class="muted small">indicatie</span>
              </td>
              <td class="num">{{ lead.call_attempts }}</td>
              <td class="muted">{{ lead.next_action_at ? fmt.relative(lead.next_action_at) : '—' }}</td>
              <td class="muted">{{ fmt.dateTime(lead.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="!rows.length && !error" class="empty">Geen leads gevonden met deze filters.</p>

      <div v-if="meta && meta.last_page > 1" class="pager">
        <button type="button" class="btn btn--ghost btn--small" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
          Vorige
        </button>
        <span class="small muted pager__count">Pagina {{ meta.current_page }} van {{ meta.last_page }} · {{ meta.total }} leads</span>
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
  quote_binding: boolean | null
  call_attempts: number
  next_action_at: string | null
  created_at: string | null
}

type Meta = { current_page: number; last_page: number; total: number }

const api = useApi()
const fmt = useDashboardFormat()
const compact = useIsCompact()

const rows = ref<LeadRow[]>([])
const meta = ref<Meta | null>(null)
const statuses = ref<Array<{ value: string; label: string }>>([])
const error = ref('')

const search = ref('')
const status = ref('')
const source = ref('')

const sourceLabels: Record<string, string> = {
  web_form: 'Website v1',
  web_form_v2: 'Website v2',
  mailbox: 'Mailbox',
  api: 'Koppeling',
  manual: 'Handmatig',
}

/** Een actie die in het verleden ligt vraagt om aandacht en kleurt rood. */
function isOverdue(iso: string) {
  return new Date(iso).getTime() < Date.now()
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

.pager {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-top: 16px;
}

@media (max-width: 720px) {
  /* De teller op een eigen regel, de knoppen daaronder even breed. */
  .pager { display: grid; grid-template-columns: 1fr 1fr; }
  .pager__count { grid-column: 1 / -1; order: -1; text-align: center; }
}
</style>
