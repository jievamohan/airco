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
      <div class="verloop__head">
        <h2 class="panel__title">Verloop</h2>
        <span class="small muted">{{ verloopSamenvatting }}</span>
      </div>

      <ol class="pijplijn">
        <li v-for="stap in stappen" :key="stap.key" :class="['pijplijn__stap', `pijplijn__stap--${stap.state}`]">
          <span class="pijplijn__balk" />
          <span class="pijplijn__naam">{{ stap.kort ?? stap.label }}</span>
          <span class="pijplijn__meta">{{ stap.meta }}</span>
        </li>
      </ol>

      <!--
        Alleen de stap die aandacht vraagt krijgt ruimte. Alles tegelijk tonen
        is precies wat de rij van elf knoppen deed.
      -->
      <div v-if="aandachtStap" :class="['aandacht', `aandacht--${aandachtStap.state}`]">
        <div class="aandacht__tekst">
          <p class="aandacht__titel">{{ aandachtStap.kop }}</p>
          <p class="aandacht__uitleg small">{{ aandachtStap.uitleg }}</p>
        </div>
        <div class="actions">
          <button
            v-for="actie in aandachtStap.acties"
            :key="actie.value"
            type="button"
            :class="['btn', 'btn--small', actie.primair ? '' : 'btn--ghost']"
            :disabled="busy"
            @click="trigger(actie.value)"
          >
            {{ actie.label }}
          </button>
        </div>
      </div>

      <div class="verloop__rest">
        <span class="small muted">Overslaan naar:</span>
        <button
          v-for="action in overslaanActies"
          :key="action.value"
          type="button"
          class="btn btn--ghost btn--small"
          :disabled="busy"
          @click="trigger(action.value)"
        >
          {{ action.label }}
        </button>
        <span class="verloop__vul" />
        <button
          v-for="action in leadActies"
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
          <div class="lijst__kop">
            <h2 class="panel__title">Verstuurde mail</h2>
            <span v-if="mislukteMail" class="small" style="color: var(--dash-bad)">{{ mislukteMail }}</span>
          </div>

          <p v-if="!lead.emails.length" class="empty">Nog geen mail verstuurd.</p>

          <div v-for="groep in mailPerDag" v-else :key="groep.dag" class="daggroep">
            <div class="daggroep__kop">
              <span>{{ groep.dag }}</span>
              <span class="daggroep__lijn" />
            </div>

            <div v-for="regel in groep.regels" :key="regel.id" class="mailregel">
              <span :class="['stip', `stip--${regel.staat}`]" />
              <div class="mailregel__tekst">
                <p class="mailregel__onderwerp">{{ regel.subject }}</p>
                <p :class="['mailregel__meta', regel.staat === 'bad' ? 'is-bad' : '']">{{ regel.meta }}</p>
              </div>
              <span class="tijd">{{ regel.tijd }}</span>
            </div>
          </div>

          <button
            v-if="meerMail"
            type="button"
            class="btn btn--ghost btn--small lijst__meer"
            @click="alleMail = true"
          >
            Toon alle {{ mailRegels.length }}
          </button>
        </section>

        <section class="panel">
          <div class="lijst__kop">
            <h2 class="panel__title">Tijdlijn</h2>
            <span class="small muted">{{ lead.events.length }} gebeurtenissen</span>
          </div>

          <div v-for="groep in tijdlijnPerDag" :key="groep.dag" class="daggroep">
            <div class="daggroep__kop">
              <span>{{ groep.dag }}</span>
              <span class="daggroep__lijn" />
            </div>

            <div class="timeline">
              <div
                v-for="event in groep.regels"
                :key="event.id"
                class="timeline__item"
                :class="{ 'timeline__item--nieuw': nieuweGebeurtenissen.has(event.id) }"
              >
                <span :class="['timeline__dot', `timeline__dot--${event.kleur}`]" />
                <div>
                  <div class="timeline__regel">
                    <p class="timeline__title">{{ event.title }}</p>
                    <span class="tijd">{{ fmt.time(event.occurred_at) }}</span>
                  </div>
                  <p class="timeline__meta">{{ event.meta }}</p>
                  <p
                    v-if="event.description"
                    :class="['timeline__desc', 'small', event.kleur === 'bad' ? 'timeline__desc--bad' : '']"
                  >
                    {{ event.description }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <button
            v-if="meerTijdlijn"
            type="button"
            class="btn btn--ghost btn--small lijst__meer"
            @click="alleTijdlijn = true"
          >
            Toon alle {{ tijdlijnRegels.length }}
          </button>
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

// De stappen die de agent doorloopt, in de volgorde waarin ze gebeuren.
// Alles wat over de héle lead gaat (gewonnen, verloren, heropenen) hoort hier
// níét bij: dat zijn geen stappen en stonden eerder wel als gelijkwaardige
// knop tussen de rest.
const overslaanActies = [
  { value: 'enrich', label: 'Opnieuw doorrekenen' },
  { value: 'send_quote', label: 'Offerte versturen' },
  { value: 'book_appointment', label: 'Afspraak inplannen' },
]

const leadActies = [
  { value: 'mark_won', label: 'Gewonnen' },
  { value: 'mark_lost', label: 'Verloren' },
  { value: 'reopen', label: 'Heropenen' },
]

type StapStaat = 'done' | 'failed' | 'busy' | 'pending'

type Stap = {
  key: string
  /** Volledige naam, voor de tekst onder de balk. */
  label: string
  /** Korte naam voor de balk zelf: "Kwalificatiegesprek" is één woord zonder
   *  breekpunt en werd op een telefoon afgekapt tot onleesbaar. */
  kort?: string
  state: StapStaat
  meta: string
  kop?: string
  uitleg?: string
  acties?: { value: string; label: string; primair?: boolean }[]
}

function gesprekken(purpose: string) {
  return ((lead.value?.calls ?? []) as any[]).filter((c) => c.purpose === purpose)
}

/** De laatste beschrijving bij een gebeurtenis van dit type, als die er is. */
function laatsteReden(...types: string[]): string {
  const gebeurtenis = ((lead.value?.events ?? []) as any[]).find(
    (e) => types.includes(e.type) && e.description,
  )

  return gebeurtenis?.description ?? ''
}

function gesprekStap(key: string, label: string, kort: string, purpose: string, acties: Stap['acties']): Stap {
  const lijst = gesprekken(purpose)
  const gelukt = lijst.find((c) => c.status === 'completed' && c.outcome !== 'failed')
  const wachtend = lijst.find((c) => c.status === 'queued')
  const mislukt = lijst.filter((c) => c.status === 'failed' || c.outcome === 'failed')

  if (gelukt) {
    return { key, label, kort, state: 'done', meta: gelukt.outcome_label ?? 'Gesproken' }
  }

  if (mislukt.length > 0 && !wachtend) {
    return {
      key,
      label,
      kort,
      state: 'failed',
      meta: `${mislukt.length}× mislukt`,
      kop: `${label} is ${mislukt.length === 1 ? 'mislukt' : `${mislukt.length} keer mislukt`}`,
      uitleg: laatsteReden('call_failed') || 'Er is geen reden vastgelegd.',
      acties,
    }
  }

  if (wachtend) {
    return {
      key,
      label,
      kort,
      state: 'busy',
      meta: wachtend.scheduled_for ? fmt.dateTime(wachtend.scheduled_for) : 'Staat klaar',
      kop: `${label} staat klaar`,
      uitleg: wachtend.scheduled_for
        ? `Gaat automatisch de deur uit om ${fmt.dateTime(wachtend.scheduled_for)}.`
        : 'Gaat bij de eerstvolgende tik de deur uit.',
      acties,
    }
  }

  return { key, label, kort, state: 'pending', meta: '—' }
}

const stappen = computed<Stap[]>(() => {
  const l = lead.value
  if (!l) return []

  const offertes = (l.quotes ?? []) as any[]
  const verstuurd = offertes.find((q) => q.sent_at)
  const afspraken = ((l.appointments ?? []) as any[]).filter((a) => a.status !== 'cancelled')

  return [
    {
      key: 'aanvraag',
      label: 'Aanvraag',
      state: 'done',
      meta: l.created_at ? fmt.dateTime(l.created_at) : '',
    },
    l.estimated_kw
      ? { key: 'sizing', label: 'Doorgerekend', state: 'done', meta: `${l.estimated_kw} kW` }
      : {
          key: 'sizing',
          label: 'Doorgerekend',
          state: 'pending',
          meta: '—',
          kop: 'Nog niet doorgerekend',
          uitleg: 'Zonder ruimtemaat kan het advies niet bepaald worden.',
          acties: [{ value: 'enrich', label: 'Nu doorrekenen', primair: true }],
        },
    gesprekStap('kwalificatie', 'Kwalificatiegesprek', 'Kwalificatie', 'qualification', [
      { value: 'call_qualification_now', label: 'Nu bellen', primair: true },
      { value: 'call_qualification', label: 'Opnieuw inplannen' },
      { value: 'stop_chase', label: 'Opvolging stoppen' },
    ]),
    verstuurd
      ? {
          key: 'offerte',
          label: 'Offerte',
          state: 'done',
          meta: verstuurd.number ?? fmt.dateTime(verstuurd.sent_at),
        }
      : { key: 'offerte', label: 'Offerte', state: 'pending', meta: '—' },
    gesprekStap('conversie', 'Conversiegesprek', 'Conversie', 'conversion', [
      { value: 'call_conversion', label: 'Opnieuw inplannen', primair: true },
      { value: 'stop_chase', label: 'Opvolging stoppen' },
    ]),
    afspraken.length > 0
      ? { key: 'afspraak', label: 'Afspraak', state: 'done', meta: fmt.dateTime(afspraken[0].starts_at) }
      : { key: 'afspraak', label: 'Afspraak', state: 'pending', meta: '—' },
  ]
})

/**
 * De eerste stap die iets van je wil: mislukt gaat vóór bezig, want daar
 * gebeurt niets meer vanzelf.
 */
const aandachtStap = computed<Stap | null>(
  () => stappen.value.find((s) => s.state === 'failed') ?? stappen.value.find((s) => s.state === 'busy') ?? null,
)

const verloopSamenvatting = computed(() => {
  const totaal = stappen.value.length
  const klaar = stappen.value.filter((s) => s.state === 'done').length

  if (stappen.value.some((s) => s.state === 'failed')) return `Stap ${klaar + 1} van ${totaal} · vastgelopen`
  if (klaar === totaal) return 'Alle stappen doorlopen'

  return `Stap ${klaar + 1} van ${totaal}`
})

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

// ------------------------------------------------ mail en tijdlijn

/**
 * Beide panelen liepen eindeloos door en zagen er identiek uit, terwijl ze een
 * andere vraag beantwoorden: de mail zegt of de klant bereikt is, de tijdlijn
 * wat er gebeurd is. Ze krijgen nu allebei daggroepen — dan volstaat een
 * klokje per regel — en ze worden afgekapt tot het recente deel.
 */
const MAIL_ZICHTBAAR = 6
const TIJDLIJN_ZICHTBAAR = 12

const alleMail = ref(false)
const alleTijdlijn = ref(false)

type Dag<T> = { dag: string; regels: T[] }

/**
 * Groepeert op dag, nieuwste eerst. Zelf sorteren en niet vertrouwen op de
 * volgorde uit de API: die sorteert op id, en dat loopt uiteen zodra een regel
 * met terugwerkende kracht wordt vastgelegd — dan verschijnt "Vandaag" twee
 * keer met "Gisteren" ertussen.
 */
function perDag<T>(regels: T[], moment: (r: T) => string | null | undefined): Dag<T>[] {
  const groepen: Dag<T>[] = []
  const gesorteerd = [...regels].sort(
    (a, b) => new Date(moment(b) ?? 0).getTime() - new Date(moment(a) ?? 0).getTime(),
  )

  for (const regel of gesorteerd) {
    const dag = fmt.dayLabel(moment(regel))
    const laatste = groepen[groepen.length - 1]

    if (laatste && laatste.dag === dag) laatste.regels.push(regel)
    else groepen.push({ dag, regels: [regel] })
  }

  return groepen
}

const emailStaat: Record<string, string> = {
  sent: 'ok',
  failed: 'bad',
  queued: 'wacht',
  skipped: 'uit',
}

/**
 * Opeenvolgende pogingen met hetzelfde onderwerp én dezelfde afloop worden één
 * regel met een telling. Zes keer "Offerte verstuurd — mislukt" onder elkaar
 * zegt niet meer dan één keer, en verdringt wel de rest van het paneel.
 */
const mailRegels = computed(() => {
  const mails = ((lead.value?.emails ?? []) as any[])
  const regels: any[] = []

  for (const mail of mails) {
    const vorige = regels[regels.length - 1]

    if (vorige && vorige.subject === mail.subject && vorige.status === mail.status) {
      vorige.aantal += 1
      vorige.eerste = mail
      continue
    }

    regels.push({ ...mail, aantal: 1, eerste: mail })
  }

  return regels.map((r) => {
    const staat = emailStaat[r.status] ?? 'uit'
    const moment = r.sent_at ?? r.attempted_at
    const label = emailStatus[r.status] ?? r.status

    return {
      id: r.id,
      subject: r.subject,
      staat,
      moment,
      tijd: fmt.time(moment),
      meta:
        r.aantal > 1
          ? `${r.aantal} pogingen ${label.toLowerCase()} · eerste ${fmt.time(r.eerste.sent_at ?? r.eerste.attempted_at)}`
          : r.status === 'sent'
            ? `naar ${lead.value?.email ?? 'de klant'}`
            : label,
    }
  })
})

const meerMail = computed(() => !alleMail.value && mailRegels.value.length > MAIL_ZICHTBAAR)

const mailPerDag = computed(() =>
  perDag(alleMail.value ? mailRegels.value : mailRegels.value.slice(0, MAIL_ZICHTBAAR), (r) => r.moment),
)

/**
 * Kleur zegt wie er aan het werk was, zodat je in een lange lijst het handwerk
 * van het automatische kunt scheiden. Een mislukking gaat daar overheen — dat
 * wil je als eerste zien.
 */
function gebeurtenisKleur(event: any): string {
  if (/mislukt|geweigerd|niet overgenomen|onbereikbaar/i.test(event.title ?? '')) return 'bad'

  return { user: 'mens', voice_agent: 'agent', lead: 'klant' }[event.actor as string] ?? 'systeem'
}

/**
 * Ook hier opeenvolgende herhalingen samenvouwen. Veertien keer "Offerte
 * gemaild" onder elkaar duwt precies de regels uit beeld waar je naar zoekt —
 * de mislukking en wat een mens deed. De beschrijving van de nieuwste blijft
 * staan, want die is meestal de enige die je nog wilt lezen.
 */
const tijdlijnRegels = computed(() => {
  const regels: any[] = []

  for (const event of ((lead.value?.events ?? []) as any[])) {
    const vorige = regels[regels.length - 1]

    if (vorige && vorige.title === event.title && vorige.actor === event.actor) {
      vorige.aantal += 1
      continue
    }

    regels.push({ ...event, aantal: 1, kleur: gebeurtenisKleur(event) })
  }

  return regels.map((r) => ({
    ...r,
    meta: r.aantal > 1 ? `${actorLabel(r.actor)} · ${r.aantal} keer` : actorLabel(r.actor),
  }))
})

const meerTijdlijn = computed(() => !alleTijdlijn.value && tijdlijnRegels.value.length > TIJDLIJN_ZICHTBAAR)

const tijdlijnPerDag = computed(() =>
  perDag(
    alleTijdlijn.value ? tijdlijnRegels.value : tijdlijnRegels.value.slice(0, TIJDLIJN_ZICHTBAAR),
    (e) => e.occurred_at,
  ),
)

const mislukteMail = computed(() => {
  const mails = (lead.value?.emails ?? []) as any[]
  const mislukt = mails.filter((m) => m.status === 'failed').length

  return mislukt === 0 ? '' : `${mislukt} van ${mails.length} mislukt`
})

// ------------------------------------------------------- meekijken

/**
 * De tijdlijn loopt vol terwijl je ernaar kijkt: de agent belt, de webhook komt
 * terug, de opvolging schuift op. Dat elke minuut zelf moeten verversen is
 * precies op het moment dat je wél wilt meekijken het vervelendst.
 */
const VERVERS_INTERVAL_MS = 10_000

let ververser: ReturnType<typeof setInterval> | null = null
const nieuweGebeurtenissen = ref<Set<number>>(new Set())
let bekendeGebeurtenissen: Set<number> | null = null

function markeerNieuw(data: LeadDetail) {
  // LeadDetail is losjes getypeerd, dus de ids expliciet als getal nemen.
  const huidige = new Set<number>(((data.events ?? []) as { id: number }[]).map((e) => Number(e.id)))

  // De eerste keer is alles "nieuw"; dan markeren we niets.
  if (bekendeGebeurtenissen === null) {
    bekendeGebeurtenissen = huidige
    return
  }

  const verschil: number[] = [...huidige].filter((id) => !bekendeGebeurtenissen!.has(id))
  bekendeGebeurtenissen = huidige

  if (verschil.length === 0) return

  nieuweGebeurtenissen.value = new Set<number>([...nieuweGebeurtenissen.value, ...verschil])
}

/**
 * Ververst stil op de achtergrond. Twee dingen bewust anders dan `load()`:
 * het formulier wordt niet overschreven zolang je iets hebt gewijzigd — anders
 * verdwijnt je invoer onder je handen — en een mislukte poging laat de pagina
 * met rust in plaats van er een foutmelding overheen te zetten. Bij de volgende
 * ronde is het meestal weer over.
 */
async function ververs() {
  if (busy.value) return

  try {
    const result = await api.get<{ data: LeadDetail }>(`/admin/leads/${route.params.uuid}`)

    // Vóór het vervangen kijken, niet erna: `dirty` vergelijkt het formulier met
    // `lead`, en zodra dat de nieuwe serverstand is lijkt een onaangeraakt veld
    // óók gewijzigd. Dan zou het formulier na de eerste serverwijziging voorgoed
    // achterlopen.
    const warenErWijzigingen = dirty.value

    markeerNieuw(result.data)
    lead.value = result.data
    if (!warenErWijzigingen) fillForm(result.data)
  } catch {
    /* stil: een korte hapering hoort de pagina niet te verstoren */
  }
}

function startVerversen() {
  if (ververser !== null) return
  ververser = setInterval(ververs, VERVERS_INTERVAL_MS)
}

function stopVerversen() {
  if (ververser === null) return
  clearInterval(ververser)
  ververser = null
}

// Een tabblad op de achtergrond hoeft niet elke tien seconden de API te
// bevragen; bij terugkomst halen we meteen op wat er gemist is.
function opZichtbaarheid() {
  if (document.hidden) {
    stopVerversen()
    return
  }

  void ververs()
  startVerversen()
}

onMounted(async () => {
  await load()
  markeerNieuw(lead.value ?? ({ events: [] } as unknown as LeadDetail))

  // Opent de pagina in een achtergrondtabblad, dan begint het verversen pas
  // zodra iemand er daadwerkelijk naar kijkt.
  if (!document.hidden) startVerversen()

  document.addEventListener('visibilitychange', opZichtbaarheid)
})

onBeforeUnmount(() => {
  stopVerversen()
  document.removeEventListener('visibilitychange', opZichtbaarheid)
})
</script>

<style scoped>
/* --------------------------------------------------------- mail en tijdlijn
   Beide panelen stapelden gelijkwaardige regels tot ver voorbij de vouw. Nu
   draagt de dag de context, staat de tijd rechts in een kolom die je kunt
   scannen, en zegt kleur wie er aan het werk was.
   -------------------------------------------------------------------------- */

.lijst__kop {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.lijst__meer { margin-top: 16px; width: 100%; }

.daggroep + .daggroep { margin-top: 14px; }

.daggroep__kop {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  font-size: 12px;
  font-weight: 600;
  color: var(--dash-muted);
}

.daggroep__lijn {
  flex: 1;
  height: 1px;
  background: #f0f0f0;
}

/* Rechts uitgelijnd en met vaste cijferbreedte, zodat de klokjes onder elkaar
   één kolom vormen in plaats van te dansen. */
.tijd {
  font-size: 12px;
  color: var(--dash-muted);
  font-variant-numeric: tabular-nums;
  flex-shrink: 0;
}

.mailregel {
  display: grid;
  grid-template-columns: 7px 1fr auto;
  gap: 10px;
  align-items: baseline;
  padding: 5px 0;
}

.mailregel__tekst { min-width: 0; }

.mailregel__onderwerp {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mailregel__meta {
  margin: 1px 0 0;
  font-size: 12px;
  color: var(--dash-muted);
}

.mailregel__meta.is-bad { color: var(--dash-bad); }

.stip {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  align-self: center;
  background: #d4d4d4;
}

.stip--ok { background: var(--dash-ok); }
.stip--bad { background: var(--dash-bad); }
.stip--wacht { background: var(--dash-warn); }

.timeline__regel {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
}

.timeline__dot--mens { background: var(--dash-ink); }
.timeline__dot--agent { background: #2c6ba8; }
.timeline__dot--klant { background: #2c6ba8; }
.timeline__dot--systeem { background: #d4d4d4; }
.timeline__dot--bad { background: var(--dash-bad); }

/* De reden van een mislukking is het enige in deze lijst dat je echt moet
   lezen; die krijgt daarom een eigen vlak in plaats van nog een grijze regel. */
.timeline__desc--bad {
  margin-top: 6px;
  padding: 8px 10px;
  border: 1px solid #eecccc;
  border-radius: 6px;
  background: #fdf5f5;
  color: #4a4a4a;
}

/* ------------------------------------------------------------------ verloop
   De pijplijn vat samen waar de lead staat; alleen de stap die aandacht
   vraagt krijgt ruimte. De vorige opzet — elf gelijkwaardige knoppen — vertelde
   niet wat er al gebeurd was, wat er misging, of wat er logisch volgde.
   -------------------------------------------------------------------------- */

.verloop__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.pijplijn {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pijplijn__stap {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.pijplijn__balk {
  height: 4px;
  border-radius: 999px;
  background: #ededed;
}

.pijplijn__naam {
  font-size: 12.5px;
  color: var(--dash-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pijplijn__meta {
  font-size: 11.5px;
  color: #9a9a9a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pijplijn__stap--done .pijplijn__balk { background: var(--dash-ok); }
.pijplijn__stap--done .pijplijn__naam { color: var(--dash-ink); font-weight: 500; }
.pijplijn__stap--done .pijplijn__meta { color: var(--dash-muted); }

.pijplijn__stap--busy .pijplijn__balk { background: var(--dash-warn); }
.pijplijn__stap--busy .pijplijn__naam { color: var(--dash-warn); font-weight: 600; }
.pijplijn__stap--busy .pijplijn__meta { color: var(--dash-warn); }

.pijplijn__stap--failed .pijplijn__balk { background: var(--dash-bad); }
.pijplijn__stap--failed .pijplijn__naam { color: var(--dash-bad); font-weight: 600; }
.pijplijn__stap--failed .pijplijn__meta { color: var(--dash-bad); }

.aandacht {
  margin-top: 18px;
  padding: 16px;
  border: 1px solid var(--dash-line);
  border-radius: 8px;
  background: #fff;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px 16px;
}

.aandacht--failed { border-color: #eecccc; background: #fdf5f5; }
.aandacht--busy { border-color: #eadfc0; background: #fdfaf3; }

.aandacht__tekst { min-width: 0; flex: 1 1 320px; }
.aandacht__titel { margin: 0; font-weight: 600; }
.aandacht__uitleg { margin: 4px 0 0; color: #4a4a4a; }

.verloop__rest {
  margin-top: 18px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.verloop__vul { flex: 1; }

@media (max-width: 720px) {
  /* Zes kolommen naast elkaar worden op een telefoon zes onleesbare kokers. */
  .pijplijn { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 8px; }

  /* Laten afbreken in plaats van afkappen: op drie kolommen viel juist
     "Kwalificatiegesprek" weg achter een beletselteken, en dat is doorgaans
     de stap die je wilt lezen. */
  .pijplijn__naam,
  .pijplijn__meta {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    overflow-wrap: anywhere;
  }

  .verloop__vul { display: none; }
}

/* Een regel die er tijdens het kijken bij komt, mag even opvallen — anders
   verandert de tijdlijn onder je handen zonder dat je het merkt. */
.timeline__item--nieuw {
  animation: tijdlijn-nieuw 620ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes tijdlijn-nieuw {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .timeline__item--nieuw {
    animation: tijdlijn-nieuw-rustig 240ms linear both;
  }

  @keyframes tijdlijn-nieuw-rustig {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }
}

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
