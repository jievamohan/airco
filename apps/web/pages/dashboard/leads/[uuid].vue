<template>
  <div v-if="lead">
    <div class="dash__head">
      <div>
        <h1 class="dash__title">{{ lead.name }}</h1>
        <p class="dash__lede">
          <span class="badge" :class="`badge--${lead.status}`">{{ lead.status_label }}</span>
          <span class="muted"> · {{ lead.city ?? 'plaats onbekend' }} · binnengekomen {{ fmt.dateTime(lead.created_at) }}</span>
        </p>
      </div>
      <NuxtLink to="/dashboard/leads" class="btn btn--ghost btn--small">Terug naar de lijst</NuxtLink>
    </div>

    <p v-if="flash" class="notice notice--ok" role="status">{{ flash }}</p>
    <p v-if="error" class="notice notice--bad" role="alert">{{ error }}</p>

    <section class="panel">
      <h2 class="panel__title">Stappen opnieuw aftrappen</h2>
      <p class="panel__note">
        Elke actie maakt een nieuwe poging aan; bestaande gesprekken en offertes blijven bewaard.
      </p>
      <div class="actions">
        <button
          v-for="action in actions"
          :key="action.value"
          type="button"
          class="btn btn--ghost btn--small"
          :disabled="busy"
          @click="trigger(action.value)"
        >
          {{ action.label }}
        </button>
      </div>
    </section>

    <div class="grid grid--sidebar" style="margin-top: 20px">
      <div>
        <section class="panel">
          <h2 class="panel__title">Gegevens</h2>
          <p class="panel__note">Aanpassingen werken direct door in de volgende offerteberekening.</p>

          <form class="form-grid" @submit.prevent="save">
            <label><span>Naam</span><input v-model.trim="form.name" required /></label>
            <label><span>E-mailadres</span><input v-model.trim="form.email" type="email" /></label>
            <label><span>Telefoon</span><input v-model.trim="form.phone" /></label>
            <label><span>Adres</span><input v-model.trim="form.address" /></label>
            <label><span>Postcode</span><input v-model.trim="form.postcode" placeholder="1234 AB" /></label>
            <label><span>Plaats</span><input v-model.trim="form.city" /></label>

            <label><span>Ruimtemaat</span><input v-model="form.space_size" type="number" step="0.1" min="1" /></label>
            <label>
              <span>Eenheid</span>
              <select v-model="form.space_unit">
                <option value="m2">m²</option>
                <option value="m3">m³</option>
              </select>
            </label>
            <label><span>Aantal ruimtes</span><input v-model="form.rooms_count" type="number" min="1" max="20" /></label>
            <label>
              <span>Isolatie</span>
              <select v-model="form.insulation">
                <option :value="null">Onbekend</option>
                <option value="good">Goed</option>
                <option value="average">Gemiddeld</option>
                <option value="poor">Matig</option>
              </select>
            </label>
            <label><span>Bouwjaar</span><input v-model="form.building_year" type="number" min="1800" max="2100" /></label>
            <label><span>Verdieping binnenunit</span><input v-model="form.floor_level" type="number" min="0" max="20" /></label>
            <label><span>Leidinglengte (m)</span><input v-model="form.pipe_length_m" type="number" min="1" max="100" /></label>
            <label><span>Plek buitenunit</span><input v-model.trim="form.outdoor_unit_placement" /></label>
            <label><span>Wandtype</span><input v-model.trim="form.wall_type" /></label>
            <label><span>Gewenste startdatum</span><input v-model="form.desired_start" type="date" /></label>
            <label>
              <span>Kwaliteitsklasse</span>
              <select v-model="form.tier">
                <option :value="null">Standaard</option>
                <option value="budget">Voordelig</option>
                <option value="mid">Middenklasse</option>
                <option value="premium">Premium</option>
              </select>
            </label>

            <label><span>Condenspomp nodig</span><input v-model="form.needs_condensate_pump" type="checkbox" /></label>
            <label><span>Extra elektragroep nodig</span><input v-model="form.needs_extra_group" type="checkbox" /></label>
            <label><span>Niet benaderen</span><input v-model="form.do_not_contact" type="checkbox" /></label>

            <label class="full"><span>Opmerkingen</span><textarea v-model.trim="form.notes" rows="4" /></label>

            <div class="full actions">
              <button type="submit" class="btn" :disabled="busy">Opslaan</button>
              <span class="small muted" style="align-self: center">
                Advies nu: {{ lead.recommended_system === 'multi_split' ? 'multisplit' : 'single split' }},
                {{ lead.estimated_kw ? `${fmt.number(lead.estimated_kw, 1)} kW` : 'nog niet berekend' }}
              </span>
            </div>
          </form>
        </section>

        <section class="panel">
          <h2 class="panel__title">Gesprekken</h2>
          <p v-if="!lead.calls.length" class="empty">Er is nog niet gebeld.</p>
          <div v-for="call in lead.calls" v-else :key="call.id" style="border-bottom: 1px solid var(--dash-line); padding: 12px 0">
            <p style="margin: 0">
              <strong>{{ call.purpose_label }}</strong> — poging {{ call.attempt_no }}
              <span class="badge" style="margin-left: 8px">{{ call.outcome_label ?? call.status }}</span>
            </p>
            <p class="small muted" style="margin: 4px 0 0">
              Gepland {{ fmt.dateTime(call.scheduled_for) }}
              <template v-if="call.started_at"> · gestart {{ fmt.dateTime(call.started_at) }}</template>
              <template v-if="call.duration_seconds"> · {{ call.duration_seconds }} seconden</template>
            </p>
            <p v-if="call.summary" style="margin: 6px 0 0">{{ call.summary }}</p>
            <details v-if="call.transcript" style="margin-top: 6px">
              <summary class="small muted" style="cursor: pointer">Transcript tonen</summary>
              <pre class="transcript">{{ call.transcript }}</pre>
            </details>
          </div>
        </section>

        <section class="panel">
          <h2 class="panel__title">Offertes</h2>
          <p v-if="!lead.quotes.length" class="empty">Er is nog geen offerte opgesteld.</p>
          <div v-for="quote in lead.quotes" v-else :key="quote.id" style="border-bottom: 1px solid var(--dash-line); padding: 12px 0">
            <p style="margin: 0">
              <strong>{{ quote.number }}</strong>
              <span class="badge" style="margin-left: 8px">{{ quoteStatus[quote.status] ?? quote.status }}</span>
              <span style="float: right">{{ fmt.euro(quote.total_cents) }} incl. btw</span>
            </p>
            <p class="small muted" style="margin: 4px 0 0">
              Montage ± {{ fmt.duration(quote.onsite_minutes) }} · geldig tot {{ fmt.date(quote.valid_until) }}
              <template v-if="quote.sent_at"> · verstuurd {{ fmt.dateTime(quote.sent_at) }}</template>
            </p>
            <p class="small" style="margin: 4px 0 0" :class="quote.margin_warning ? 'is-bad' : 'muted'">
              Kostprijs {{ fmt.euro(quote.cost_cents) }} · marge {{ fmt.number(quote.margin_pct, 1) }}%
              <template v-if="quote.margin_warning"> — onder de ingestelde drempel</template>
            </p>
            <details style="margin-top: 6px">
              <summary class="small muted" style="cursor: pointer">Regels tonen</summary>
              <table class="data" style="margin-top: 8px">
                <tbody>
                  <tr v-for="(item, index) in quote.items" :key="index">
                    <td>{{ item.description }}</td>
                    <td class="num">{{ fmt.number(item.quantity, 2) }} {{ item.unit }}</td>
                    <td class="num">{{ fmt.euro(item.line_total_cents) }}</td>
                  </tr>
                </tbody>
              </table>
            </details>
          </div>
        </section>
      </div>

      <div>
        <section class="panel">
          <h2 class="panel__title">Afspraken</h2>
          <p v-if="!lead.appointments.length" class="empty">Nog geen afspraak.</p>
          <div v-for="appointment in lead.appointments" v-else :key="appointment.id" style="padding: 8px 0">
            <p style="margin: 0">
              <strong>{{ fmt.dateTime(appointment.starts_at, appointment.timezone) }}</strong>
            </p>
            <p class="small muted" style="margin: 3px 0 0">
              tot {{ fmt.dateTime(appointment.ends_at, appointment.timezone) }} · {{ providerLabel(appointment.provider) }}
            </p>
            <p v-if="appointment.sync_error" class="small muted" style="margin: 3px 0 0">{{ appointment.sync_error }}</p>
          </div>
        </section>

        <section class="panel">
          <h2 class="panel__title">Verstuurde mail</h2>
          <p v-if="!lead.emails.length" class="empty">Nog geen mail verstuurd.</p>
          <table v-else class="data">
            <tbody>
              <tr v-for="email in lead.emails" :key="email.id">
                <td>
                  {{ email.subject }}
                  <span class="small muted" style="display: block">{{ fmt.dateTime(email.sent_at) }}</span>
                </td>
                <td class="num"><span class="badge">{{ emailStatus[email.status] ?? email.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="panel">
          <h2 class="panel__title">Tijdlijn</h2>
          <div class="timeline">
            <div v-for="event in lead.events" :key="event.id" class="timeline__item">
              <span class="timeline__dot" />
              <div>
                <p class="timeline__title">{{ event.title }}</p>
                <p class="timeline__meta">{{ fmt.dateTime(event.occurred_at) }} · {{ actorLabel(event.actor) }}</p>
                <p v-if="event.description" class="timeline__desc small">{{ event.description }}</p>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>

  <p v-else-if="error" class="notice notice--bad">{{ error }}</p>
  <p v-else class="empty">Lead wordt opgehaald…</p>
</template>

<script setup lang="ts">
import type { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'dashboard', middleware: 'dashboard-auth' })

type LeadDetail = Record<string, any>

const route = useRoute()
const api = useApi()
const fmt = useDashboardFormat()

const lead = ref<LeadDetail | null>(null)
const form = reactive<Record<string, any>>({})
const busy = ref(false)
const flash = ref('')
const error = ref('')

const actions = [
  { value: 'enrich', label: 'Opnieuw doorrekenen' },
  { value: 'call_qualification', label: 'Kwalificatiegesprek inplannen' },
  { value: 'send_quote', label: 'Offerte opnieuw versturen' },
  { value: 'call_conversion', label: 'Conversiegesprek inplannen' },
  { value: 'book_appointment', label: 'Afspraak inplannen' },
  { value: 'start_chase', label: 'Opvolging starten' },
  { value: 'stop_chase', label: 'Opvolging stoppen' },
  { value: 'mark_won', label: 'Markeer als gewonnen' },
  { value: 'mark_lost', label: 'Markeer als verloren' },
  { value: 'reopen', label: 'Lead heropenen' },
]

const quoteStatus: Record<string, string> = {
  draft: 'Concept',
  sent: 'Verstuurd',
  viewed: 'Bekeken',
  accepted: 'Geaccepteerd',
  declined: 'Afgewezen',
  expired: 'Verlopen',
}

const emailStatus: Record<string, string> = {
  queued: 'In de wachtrij',
  sent: 'Verstuurd',
  failed: 'Mislukt',
  skipped: 'Overgeslagen',
}

function actorLabel(actor: string) {
  return { system: 'systeem', voice_agent: 'voice agent', user: 'medewerker', lead: 'klant' }[actor] ?? actor
}

function providerLabel(provider: string) {
  return { google: 'Google-agenda', apple: 'Apple-agenda', none: 'alleen in het CRM' }[provider] ?? provider
}

const editable = [
  'name', 'email', 'phone', 'address', 'postcode', 'city', 'space_size', 'space_unit',
  'rooms_count', 'insulation', 'building_year', 'floor_level', 'wall_type',
  'outdoor_unit_placement', 'pipe_length_m', 'needs_condensate_pump', 'needs_extra_group',
  'desired_start', 'notes', 'tier', 'do_not_contact',
]

function fillForm(data: LeadDetail) {
  for (const key of editable) form[key] = data[key] ?? (typeof data[key] === 'boolean' ? false : null)
  form.needs_condensate_pump = Boolean(data.needs_condensate_pump)
  form.needs_extra_group = Boolean(data.needs_extra_group)
  form.do_not_contact = Boolean(data.do_not_contact)
}

async function load() {
  error.value = ''
  try {
    const result = await api.get<{ data: LeadDetail }>(`/admin/leads/${route.params.uuid}`)
    lead.value = result.data
    fillForm(result.data)
  } catch (e) {
    error.value = (e as ApiError).message
  }
}

async function save() {
  busy.value = true
  flash.value = ''
  error.value = ''

  const payload: Record<string, any> = {}
  for (const key of editable) {
    const value = form[key]
    payload[key] = value === '' ? null : value
  }

  try {
    const result = await api.patch<{ data: LeadDetail }>(`/admin/leads/${route.params.uuid}`, payload)
    lead.value = result.data
    fillForm(result.data)
    flash.value = 'De gegevens zijn opgeslagen.'
  } catch (e) {
    const err = e as ApiError
    error.value = err.errors ? Object.values(err.errors).flat().join(' ') : err.message
  } finally {
    busy.value = false
  }
}

async function trigger(action: string) {
  busy.value = true
  flash.value = ''
  error.value = ''

  try {
    const result = await api.post<{ message: string }>(`/admin/leads/${route.params.uuid}/actions`, { action })
    flash.value = result.message
    await load()
  } catch (e) {
    error.value = (e as ApiError).message
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>
