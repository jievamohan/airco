<template>
  <div>
    <div class="dash__head">
      <div>
        <h1 class="dash__title">Overzicht</h1>
        <p class="dash__lede">Hoe de agent het doet, van binnengekomen aanvraag tot ingeplande afspraak.</p>
      </div>
      <div class="filters filters--dates" style="margin: 0">
        <label class="small muted">
          Van
          <input v-model="from" type="date" @change="load" />
        </label>
        <label class="small muted">
          Tot
          <input v-model="to" type="date" @change="load" />
        </label>
      </div>
    </div>

    <p v-if="error" class="notice notice--bad" role="alert">{{ error }}</p>

    <section v-if="status" class="panel status" :class="{ 'status--alarm': !allesDraait }">
      <div class="status__head">
        <h2 class="panel__title">Systeemstatus</h2>
        <span class="small muted">bijgewerkt {{ statusLeeftijd }}</span>
      </div>

      <div class="status__row">
        <div class="status__item">
          <span class="badge" :class="badgeClass(status.worker.state)">{{ stateLabels[status.worker.state] }}</span>
          <div>
            <p class="status__label">Wachtrij-worker</p>
            <p class="status__sub">{{ heartbeatUitleg(status.worker, 'worker') }}</p>
          </div>
        </div>

        <div class="status__item">
          <span class="badge" :class="badgeClass(status.scheduler.state)">{{ stateLabels[status.scheduler.state] }}</span>
          <div>
            <p class="status__label">Planner</p>
            <p class="status__sub">{{ heartbeatUitleg(status.scheduler, 'scheduler') }}</p>
          </div>
        </div>

        <div class="status__item">
          <span class="badge" :class="status.queue.failed > 0 ? 'badge--lost' : ''">{{ status.queue.pending }}</span>
          <div>
            <p class="status__label">In de wachtrij</p>
            <p class="status__sub">
              <template v-if="status.queue.failed > 0">{{ status.queue.failed }} mislukt</template>
              <template v-else>niets mislukt</template>
            </p>
          </div>
        </div>
      </div>

      <p v-if="!allesDraait" class="status__hint">
        Zolang dit niet groen staat, wordt er niet gebeld en gaat er geen mail uit. Start de
        wachtrij-worker en de planner op de server (<code>make deploy-worker</code>).
      </p>
    </section>

    <div v-if="data">
      <div class="kpis">
        <div class="kpi">
          <p class="kpi__label">Aanvragen</p>
          <p class="kpi__value">{{ data.totals.leads }}</p>
          <p class="kpi__sub">in de gekozen periode</p>
        </div>
        <div class="kpi">
          <p class="kpi__label">Bereikt aan de telefoon</p>
          <p class="kpi__value">{{ fmt.number(data.totals.answer_rate, 1) }}%</p>
          <p class="kpi__sub">{{ data.totals.calls_answered }} van {{ data.totals.calls }} gesprekken</p>
        </div>
        <div class="kpi">
          <p class="kpi__label">Prijsindicaties</p>
          <p class="kpi__value">{{ data.totals.indications_sent }}</p>
          <p class="kpi__sub">verstuurd na het gesprek</p>
        </div>
        <div class="kpi">
          <p class="kpi__label">Offertes verstuurd</p>
          <p class="kpi__value">{{ data.totals.quotes_sent }}</p>
          <p class="kpi__sub">na de opname · {{ data.totals.quotes_accepted }} geaccepteerd</p>
        </div>
        <div class="kpi">
          <p class="kpi__label">Offerteacceptatie</p>
          <p class="kpi__value">{{ fmt.number(data.totals.quote_acceptance_rate, 1) }}%</p>
          <p class="kpi__sub">van de verstuurde offertes</p>
        </div>
        <div class="kpi">
          <p class="kpi__label">Afspraken</p>
          <p class="kpi__value">{{ data.totals.appointments }}</p>
          <p class="kpi__sub">ingepland</p>
        </div>
        <div class="kpi">
          <p class="kpi__label">Geboekte omzet</p>
          <p class="kpi__value">{{ fmt.euro(data.totals.booked_value_cents) }}</p>
          <p class="kpi__sub">incl. btw, geaccepteerde offertes</p>
        </div>
      </div>

      <div class="grid grid--2">
        <section class="panel">
          <h2 class="panel__title">Funnel</h2>
          <p class="panel__note">
            Elke balk telt de leads die deze stap hebben bereikt, ook als ze inmiddels verder zijn.
          </p>

          <div class="funnel">
            <div v-for="step in data.funnel" :key="step.status" class="funnel__row">
              <div class="funnel__meta">
                <span class="funnel__label">{{ step.label }}</span>
                <span class="funnel__numbers">
                  {{ step.count }}
                  <template v-if="step.step_conversion !== null"> · {{ fmt.number(step.step_conversion, 0) }}% door</template>
                  <template v-if="step.dropped > 0"> · {{ step.dropped }} afgevallen</template>
                </span>
              </div>
              <div class="funnel__track">
                <div class="funnel__fill" :style="{ width: `${step.share_of_total}%` }" />
              </div>
            </div>
          </div>
        </section>

        <div>
          <section class="panel">
            <h2 class="panel__title">Uitkomst van gesprekken</h2>
            <p class="panel__note">Waar de telefoontjes op uitdraaien.</p>
            <table v-if="Object.keys(data.call_outcomes).length" class="data">
              <tbody>
                <tr v-for="(count, outcome) in data.call_outcomes" :key="outcome">
                  <td>{{ outcomeLabels[outcome] ?? outcome }}</td>
                  <td class="num">{{ count }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty">Nog geen afgeronde gesprekken.</p>
          </section>

          <section class="panel">
            <h2 class="panel__title">Bron van de aanvraag</h2>
            <table v-if="Object.keys(data.by_source).length" class="data">
              <tbody>
                <tr v-for="(count, source) in data.by_source" :key="source">
                  <td>{{ sourceLabels[source] ?? source }}</td>
                  <td class="num">{{ count }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty">Nog geen aanvragen in deze periode.</p>
          </section>

          <section v-if="Object.keys(data.lost_reasons).length" class="panel">
            <h2 class="panel__title">Waarom leads afhaken</h2>
            <table class="data">
              <tbody>
                <tr v-for="(count, reason) in data.lost_reasons" :key="reason">
                  <td>{{ reason }}</td>
                  <td class="num">{{ count }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>
      </div>
    </div>

    <p v-else-if="!error" class="empty">Cijfers worden opgehaald…</p>
  </div>
</template>

<script setup lang="ts">
import type { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'dashboard', middleware: 'dashboard-auth' })

type Analytics = {
  totals: {
    leads: number
    calls: number
    calls_answered: number
    answer_rate: number
    indications_sent: number
    quotes_sent: number
    quotes_accepted: number
    quote_acceptance_rate: number
    appointments: number
    booked_value_cents: number
  }
  funnel: Array<{
    status: string
    label: string
    count: number
    share_of_total: number
    step_conversion: number | null
    dropped: number
  }>
  by_source: Record<string, number>
  call_outcomes: Record<string, number>
  lost_reasons: Record<string, number>
}

type Heartbeat = {
  last_seen_at: string | null
  seconds_ago: number | null
  fresh: boolean
  state: 'online' | 'offline' | 'unknown'
}

type SystemStatus = {
  scheduler: Heartbeat
  worker: Heartbeat
  queue: { pending: number; failed: number }
  fresh_within_seconds: number
  checked_at: string
}

const api = useApi()
const fmt = useDashboardFormat()

const data = ref<Analytics | null>(null)
const status = ref<SystemStatus | null>(null)
const statusOpgehaaldOp = ref<Date | null>(null)
const nu = ref(Date.now())
const error = ref('')
const from = ref('')
const to = ref('')

const outcomeLabels: Record<string, string> = {
  answered: 'Opgenomen',
  no_answer: 'Niet opgenomen',
  voicemail: 'Voicemail',
  busy: 'In gesprek',
  failed: 'Mislukt',
  declined: 'Afgewezen',
  appointment_booked: 'Afspraak gemaakt',
  callback_requested: 'Terugbelverzoek',
  do_not_contact: 'Wil niet gebeld worden',
}

const sourceLabels: Record<string, string> = {
  web_form: 'Formulier op de website (versie 1)',
  web_form_v2: 'Formulier op de website (versie 2)',
  mailbox: 'Mailbox',
  api: 'Externe koppeling',
  manual: 'Handmatig ingevoerd',
}

const stateLabels: Record<string, string> = {
  online: 'Draait',
  offline: 'Ligt stil',
  unknown: 'Onbekend',
}

function badgeClass(state: string) {
  return { online: 'badge--won', offline: 'badge--lost', unknown: 'badge--calling' }[state] ?? ''
}

const allesDraait = computed(
  () => status.value?.worker.state === 'online' && status.value?.scheduler.state === 'online',
)

/** "3 minuten geleden" leest hier prettiger dan een tijdstip. */
function geleden(seconden: number) {
  if (seconden < 60) return `${seconden} seconden geleden`
  const minuten = Math.round(seconden / 60)
  if (minuten < 60) return `${minuten} ${minuten === 1 ? 'minuut' : 'minuten'} geleden`
  const uren = Math.round(minuten / 60)
  return `${uren} ${uren === 1 ? 'uur' : 'uur'} geleden`
}

const statusLeeftijd = computed(() => {
  if (!statusOpgehaaldOp.value) return 'zojuist'
  const seconden = Math.max(0, Math.round((nu.value - statusOpgehaaldOp.value.getTime()) / 1000))
  return seconden < 10 ? 'zojuist' : geleden(seconden)
})

function heartbeatUitleg(hartslag: Heartbeat, proces: 'worker' | 'scheduler') {
  if (hartslag.state === 'unknown') {
    return 'Niet te zeggen zolang de planner stilligt.'
  }

  if (hartslag.seconds_ago === null) {
    return proces === 'worker'
      ? 'Heeft zich nog nooit gemeld.'
      : 'Heeft zich nog nooit gemeld; draait de cron wel?'
  }

  return `Laatste levensteken ${geleden(hartslag.seconds_ago)}.`
}

async function loadStatus() {
  try {
    status.value = await api.get<SystemStatus>('/admin/system-status')
    statusOpgehaaldOp.value = new Date()
  } catch {
    // Een mislukte statuscheck mag het overzicht niet in de weg zitten.
  }
}

async function load() {
  error.value = ''
  const params = new URLSearchParams()
  if (from.value) params.set('from', from.value)
  if (to.value) params.set('to', to.value)

  try {
    data.value = await api.get<Analytics>(`/admin/analytics${params.toString() ? `?${params}` : ''}`)
  } catch (e) {
    error.value = (e as ApiError).message
  }
}

onMounted(() => {
  load()
  loadStatus()

  // Tijdens een demo wil je het omslaan zien zonder te verversen.
  const statusTimer = setInterval(loadStatus, 20000)
  const klok = setInterval(() => (nu.value = Date.now()), 1000)

  onUnmounted(() => {
    clearInterval(statusTimer)
    clearInterval(klok)
  })
})
</script>
