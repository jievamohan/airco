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
      <NuxtLink to="/dashboard/leads" class="btn btn--ghost btn--small back">Terug naar de lijst</NuxtLink>
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
        <form @submit.prevent="save">
          <fieldset class="panel fieldgroup">
            <legend class="fieldgroup__legend">Contact</legend>
            <div class="fieldgroup__fields">
              <label class="field full">
                <span class="field__label">Naam</span>
                <input v-model.trim="form.name" required />
              </label>

              <label class="field full">
                <span class="field__label">Telefoon</span>
                <span class="actionfield">
                  <input v-model.trim="form.phone" type="tel" inputmode="tel" autocomplete="off" />
                  <a v-if="form.phone" class="actionfield__action" :href="`tel:${form.phone}`">
                    <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path
                        d="M3.2 4.1c0-.6.5-1.1 1.1-1.1h1.4c.5 0 .9.3 1 .8l.5 1.9c.1.4 0 .8-.4 1l-.9.6c.8 1.6 2.1 2.9 3.7 3.7l.6-.9c.2-.3.6-.5 1-.4l1.9.5c.5.1.8.5.8 1v1.4c0 .6-.5 1.1-1.1 1.1C7.4 13.7 3.2 9.5 3.2 4.1z"
                        stroke-linejoin="round"
                      />
                    </svg>
                    Bellen
                  </a>
                </span>
              </label>

              <label class="field full">
                <span class="field__label">E-mailadres</span>
                <span class="actionfield">
                  <input v-model.trim="form.email" type="email" inputmode="email" autocomplete="off" />
                  <a v-if="form.email" class="actionfield__action" :href="`mailto:${form.email}`">
                    <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5">
                      <rect x="2.2" y="3.6" width="11.6" height="8.8" rx="1.4" />
                      <path d="M2.6 4.4L8 8.6l5.4-4.2" stroke-linejoin="round" />
                    </svg>
                    Mailen
                  </a>
                </span>
              </label>
            </div>
          </fieldset>

          <fieldset class="panel fieldgroup">
            <legend class="fieldgroup__legend">Adres</legend>
            <div class="fieldgroup__fields">
              <label class="field full">
                <span class="field__label">Straat en huisnummer</span>
                <input v-model.trim="form.address" autocomplete="off" />
              </label>
              <label class="field">
                <span class="field__label">Postcode</span>
                <input v-model.trim="form.postcode" placeholder="1234 AB" autocomplete="off" />
              </label>
              <label class="field">
                <span class="field__label">Plaats</span>
                <input v-model.trim="form.city" autocomplete="off" />
              </label>
            </div>
          </fieldset>

          <fieldset class="panel fieldgroup">
            <legend class="fieldgroup__legend">De klus</legend>
            <p class="fieldgroup__note">Deze velden bepalen het advies hieronder.</p>
            <div class="fieldgroup__fields">
              <!-- Ruimtemaat en eenheid waren twee velden voor één vraag. -->
              <label class="field full">
                <span class="field__label">Te koelen ruimte</span>
                <span class="unitfield">
                  <input v-model="form.space_size" type="number" step="0.1" min="1" inputmode="decimal" />
                  <span class="seg">
                    <label v-for="unit in spaceUnits" :key="unit.value" class="seg__option">
                      <input v-model="form.space_unit" type="radio" name="space_unit" :value="unit.value" />
                      <span>{{ unit.label }}</span>
                    </label>
                  </span>
                </span>
              </label>

              <!-- In de praktijk 1 tot 4: dat vraagt geen toetsenbord. -->
              <div class="field">
                <span class="field__label" id="rooms-label">Aantal ruimtes</span>
                <span class="stepper">
                  <button type="button" :disabled="roomsCount <= 1" aria-label="Eén ruimte minder" @click="stepRooms(-1)">
                    −
                  </button>
                  <input v-model.number="form.rooms_count" type="number" min="1" max="20" inputmode="numeric" aria-labelledby="rooms-label" />
                  <button type="button" :disabled="roomsCount >= 20" aria-label="Eén ruimte meer" @click="stepRooms(1)">
                    +
                  </button>
                </span>
              </div>

              <div class="field full">
                <span class="field__label" id="insulation-label">Isolatie</span>
                <span class="seg" role="radiogroup" aria-labelledby="insulation-label">
                  <label v-for="option in insulations" :key="String(option.value)" class="seg__option">
                    <input v-model="form.insulation" type="radio" name="insulation" :value="option.value" />
                    <span>{{ option.label }}</span>
                  </label>
                </span>
              </div>

              <label class="field">
                <span class="field__label">Bouwjaar</span>
                <input v-model="form.building_year" type="number" min="1800" max="2100" inputmode="numeric" />
              </label>
              <label class="field">
                <span class="field__label">Verdieping binnenunit</span>
                <input v-model="form.floor_level" type="number" min="0" max="20" inputmode="numeric" />
              </label>
              <label class="field">
                <span class="field__label">Leidinglengte (m)</span>
                <input v-model="form.pipe_length_m" type="number" min="1" max="100" inputmode="decimal" />
              </label>
              <label class="field">
                <span class="field__label">Wandtype</span>
                <input v-model.trim="form.wall_type" />
              </label>
              <label class="field full">
                <span class="field__label">Plek buitenunit</span>
                <input v-model.trim="form.outdoor_unit_placement" />
              </label>
            </div>
          </fieldset>

          <!-- De uitkomst van de velden hierboven, dus hij staat er direct onder. -->
          <div class="advice">
            <span class="advice__mark" aria-hidden="true">
              <svg width="17" height="17" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M8 1.8l4.6 2.6v5.2L8 12.2 3.4 9.6V4.4z" stroke-linejoin="round" />
                <path d="M8 6.6v3.1M6.4 7.6h3.2" stroke-linecap="round" />
              </svg>
            </span>
            <span class="advice__text">
              <span class="advice__value">
                {{ lead.recommended_system === 'multi_split' ? 'Multisplit' : 'Single split' }}
                <template v-if="lead.estimated_kw"> · {{ fmt.number(lead.estimated_kw, 1) }} kW</template>
              </span>
              <span class="advice__note">
                {{ adviceStale ? 'Wordt opnieuw berekend zodra je opslaat' : 'Berekend uit de velden hierboven' }}
              </span>
            </span>
          </div>

          <fieldset class="panel fieldgroup">
            <legend class="fieldgroup__legend">Wat de offerte verandert</legend>
            <div class="fieldgroup__fields">
              <div class="field full">
                <span class="field__label" id="tier-label">Kwaliteitsklasse</span>
                <span class="seg" role="radiogroup" aria-labelledby="tier-label">
                  <label v-for="option in tiers" :key="String(option.value)" class="seg__option">
                    <input v-model="form.tier" type="radio" name="tier" :value="option.value" />
                    <span>{{ option.label }}</span>
                  </label>
                </span>
              </div>

              <label class="switchrow full">
                <input v-model="form.needs_condensate_pump" type="checkbox" class="switchrow__input" />
                <span class="switchrow__text">
                  <span class="switchrow__label">Condenspomp nodig</span>
                  <span class="switchrow__note">Telt als toeslag mee in de offerte</span>
                </span>
                <span class="switch" aria-hidden="true" />
              </label>

              <label class="switchrow full">
                <input v-model="form.needs_extra_group" type="checkbox" class="switchrow__input" />
                <span class="switchrow__text">
                  <span class="switchrow__label">Extra elektragroep nodig</span>
                  <span class="switchrow__note">Telt als toeslag mee in de offerte</span>
                </span>
                <span class="switch" aria-hidden="true" />
              </label>
            </div>
          </fieldset>

          <fieldset class="panel fieldgroup">
            <legend class="fieldgroup__legend">Planning</legend>
            <div class="fieldgroup__fields">
              <label class="field full">
                <span class="field__label">Gewenste startdatum</span>
                <input v-model="form.desired_start" type="date" />
              </label>
            </div>
          </fieldset>

          <fieldset class="panel fieldgroup">
            <legend class="fieldgroup__legend">Opmerkingen</legend>
            <div class="fieldgroup__fields">
              <label class="field full">
                <span class="field__label">Wat je over deze lead moet weten</span>
                <textarea v-model.trim="form.notes" rows="4" />
              </label>
            </div>
          </fieldset>

          <!-- Geen eigenschap van de klus, maar een rem op het hele proces. -->
          <label class="switchrow switchrow--danger" style="margin-top: 16px">
            <input v-model="form.do_not_contact" type="checkbox" class="switchrow__input" />
            <span class="switchrow__lead">
              <svg class="switchrow__icon" width="17" height="17" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M8 2.6l6 10.8H2z" stroke-linejoin="round" />
                <path d="M8 6.6v3M8 11.6v.1" stroke-linecap="round" />
              </svg>
              <span class="switchrow__text">
                <span class="switchrow__label">Niet benaderen</span>
                <span class="switchrow__note">Zet alle bel- en mailstappen stop</span>
              </span>
            </span>
            <span class="switch" aria-hidden="true" />
          </label>

          <div class="savebar">
            <span class="savebar__state">{{ dirty ? 'Niet-opgeslagen wijzigingen' : 'Alles is opgeslagen' }}</span>
            <button type="submit" class="btn" :disabled="busy || !dirty">Opslaan</button>
          </div>
        </form>

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
            <p class="quote__head">
              <span>
                <strong>{{ quote.number }}</strong>
                <span class="badge" style="margin-left: 8px">{{ quoteStatus[quote.status] ?? quote.status }}</span>
              </span>
              <span class="quote__total">{{ fmt.euro(quote.total_cents) }} incl. btw</span>
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
              <table class="data quote__items" style="margin-top: 8px">
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
  // Aparte actie, niet een vinkje bij de vorige: buiten het belvenster bellen
  // hoort een bewuste keuze te zijn en geen instelling die aan blijft staan.
  { value: 'call_qualification_now', label: 'Nu bellen (buiten belvenster)' },
  { value: 'send_quote', label: 'Offerte opnieuw versturen' },
  { value: 'call_conversion', label: 'Conversiegesprek inplannen' },
  { value: 'book_appointment', label: 'Afspraak inplannen' },
  { value: 'start_chase', label: 'Opvolging starten' },
  { value: 'stop_chase', label: 'Opvolging stoppen' },
  { value: 'mark_won', label: 'Markeer als gewonnen' },
  { value: 'mark_lost', label: 'Markeer als verloren' },
  { value: 'reopen', label: 'Lead heropenen' },
]

const spaceUnits = [
  { value: 'm2', label: 'm²' },
  { value: 'm3', label: 'm³' },
]

const insulations = [
  { value: null, label: 'Onbekend' },
  { value: 'good', label: 'Goed' },
  { value: 'average', label: 'Gemiddeld' },
  { value: 'poor', label: 'Matig' },
]

// "Middenklasse" past niet op vier knoppen naast elkaar op een telefoon.
const tiers = [
  { value: null, label: 'Standaard' },
  { value: 'budget', label: 'Voordelig' },
  { value: 'mid', label: 'Midden' },
  { value: 'premium', label: 'Premium' },
]

const roomsCount = computed(() => Number(form.rooms_count) || 1)

function stepRooms(delta: number) {
  form.rooms_count = Math.min(20, Math.max(1, roomsCount.value + delta))
}

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

/**
 * Gelijk genoeg? De API stuurt getallen soms als "42.00" en booleans als
 * `true`, terwijl een invoerveld strings teruggeeft; letterlijk vergelijken
 * zou het formulier altijd gewijzigd noemen.
 */
function same(a: unknown, b: unknown) {
  if (typeof a === 'boolean' || typeof b === 'boolean') return Boolean(a) === Boolean(b)

  const left = String(a ?? '')
  const right = String(b ?? '')

  if (left !== '' && right !== '' && !Number.isNaN(Number(left)) && !Number.isNaN(Number(right))) {
    return Number(left) === Number(right)
  }

  return left === right
}

const dirty = computed(() => Boolean(lead.value) && editable.some((key) => !same(form[key], lead.value![key])))

// De velden waar de server het advies uit rekent. Verandert er één, dan klopt
// het getoonde advies niet meer tot er is opgeslagen.
const adviceFields = ['space_size', 'space_unit', 'rooms_count', 'insulation', 'building_year', 'floor_level']

const adviceStale = computed(
  () => Boolean(lead.value) && adviceFields.some((key) => !same(form[key], lead.value![key])),
)

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

<style scoped>
.quote__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 4px 12px;
  margin: 0;
}

.quote__total { white-space: nowrap; }

@media (max-width: 720px) {
  /* Een knop naast een lange leadnaam wordt anders een smalle koker. */
  .back {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
  }

  .quote__items td:first-child { min-width: 0; }
}
</style>
