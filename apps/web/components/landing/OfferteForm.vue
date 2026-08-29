<template>
  <section id="offerte" class="offerte">
    <div class="offerte__inner container">
      <div class="offerte__intro">
        <h2 class="offerte__title">Ontvang vrijblijvend advies.</h2>
        <p class="offerte__lede">We nemen contact op om uw situatie te bespreken.</p>
      </div>

      <div v-if="success" class="offerte__success" data-testid="offerte-success" role="status">
        <p>Bedankt, uw aanvraag is bij ons binnen.</p>
        <p class="offerte__success-note">
          We bellen u kort om de laatste details door te nemen en sturen daarna direct een offerte.
        </p>
        <button type="button" class="btn-ghost" @click="reset">Nog een aanvraag doen</button>
      </div>

      <form
        v-else
        class="offerte__form"
        data-testid="offerte-form"
        novalidate
        @submit.prevent="onSubmit"
      >
        <div class="offerte__grid">
          <label class="field">
            <span>Naam</span>
            <input
              v-model.trim="form.name"
              name="name"
              autocomplete="name"
              :aria-invalid="!!errors.name"
              :aria-describedby="errors.name ? 'err-name' : undefined"
            />
            <span v-if="errors.name" id="err-name" class="field__error">{{ errors.name }}</span>
          </label>

          <label class="field">
            <span>Adres</span>
            <input
              v-model.trim="form.address"
              name="address"
              autocomplete="street-address"
              :aria-invalid="!!errors.address"
              :aria-describedby="errors.address ? 'err-address' : undefined"
            />
            <span v-if="errors.address" id="err-address" class="field__error">{{ errors.address }}</span>
          </label>

          <label class="field">
            <span>Postcode</span>
            <input
              v-model.trim="form.postcode"
              name="postcode"
              autocomplete="postal-code"
              :aria-invalid="!!errors.postcode"
              :aria-describedby="errors.postcode ? 'err-postcode' : undefined"
            />
            <span v-if="errors.postcode" id="err-postcode" class="field__error">{{ errors.postcode }}</span>
          </label>

          <label class="field">
            <span>Plaats</span>
            <input
              v-model.trim="form.city"
              name="city"
              autocomplete="address-level2"
              :aria-invalid="!!errors.city"
              :aria-describedby="errors.city ? 'err-city' : undefined"
            />
            <span v-if="errors.city" id="err-city" class="field__error">{{ errors.city }}</span>
          </label>

          <label class="field">
            <span>E-mailadres</span>
            <input
              v-model.trim="form.email"
              name="email"
              type="email"
              autocomplete="email"
              :aria-invalid="!!errors.email"
              :aria-describedby="errors.email ? 'err-email' : undefined"
            />
            <span v-if="errors.email" id="err-email" class="field__error">{{ errors.email }}</span>
          </label>

          <label class="field">
            <span>Telefoonnummer</span>
            <input
              v-model.trim="form.phone"
              name="phone"
              type="tel"
              autocomplete="tel"
              :aria-invalid="!!errors.phone"
              :aria-describedby="errors.phone ? 'err-phone' : undefined"
            />
            <span v-if="errors.phone" id="err-phone" class="field__error">{{ errors.phone }}</span>
          </label>

          <div class="field">
            <span id="space-label">Ruimtemaat</span>
            <div
              class="field__measure"
              :class="{ 'field__measure--invalid': !!errors.space_size }"
            >
              <input
                v-model.trim="form.space_size"
                name="space_size"
                inputmode="numeric"
                aria-labelledby="space-label"
                :aria-invalid="!!errors.space_size"
                :aria-describedby="errors.space_size ? 'err-space' : undefined"
              />
              <div class="field__units" role="group" aria-label="Eenheid">
                <button
                  type="button"
                  class="field__unit"
                  :class="{ 'is-active': form.space_unit === 'm2' }"
                  :aria-pressed="form.space_unit === 'm2'"
                  @click="setUnit('m2')"
                >
                  m²
                </button>
                <button
                  type="button"
                  class="field__unit"
                  :class="{ 'is-active': form.space_unit === 'm3' }"
                  :aria-pressed="form.space_unit === 'm3'"
                  @click="setUnit('m3')"
                >
                  m³
                </button>
              </div>
            </div>
            <input type="hidden" name="space_unit" :value="form.space_unit" />
            <span v-if="errors.space_size" id="err-space" class="field__error">{{ errors.space_size }}</span>
          </div>

          <div class="field">
            <span id="rooms-label">Aantal ruimtes</span>
            <div class="field__rooms" role="group" aria-labelledby="rooms-label">
              <button
                v-for="option in roomOptions"
                :key="option.value"
                type="button"
                class="field__room"
                :class="{ 'is-active': form.rooms_count === option.value }"
                :aria-pressed="form.rooms_count === option.value"
                @click="setRooms(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
            <input type="hidden" name="rooms_count" :value="form.rooms_count ?? ''" />
          </div>

          <label class="field field--full">
            <span>Opmerkingen</span>
            <textarea
              v-model.trim="form.notes"
              name="notes"
              rows="4"
              :aria-invalid="!!errors.notes"
            />
          </label>
        </div>

        <p v-if="submitError" class="offerte__error" role="alert">{{ submitError }}</p>

        <button
          type="submit"
          class="btn-primary offerte__submit"
          data-testid="offerte-submit"
          :disabled="submitting"
        >
          {{ submitting ? 'Bezig met versturen…' : 'Versturen' }}
        </button>
      </form>
    </div>
  </section>
</template>

<script setup lang="ts">
type SpaceUnit = 'm2' | 'm3'

type LeadForm = {
  name: string
  address: string
  postcode: string
  city: string
  email: string
  phone: string
  space_size: string
  space_unit: SpaceUnit
  rooms_count: number | null
  notes: string
}

type LeadErrors = Partial<Record<keyof LeadForm, string>>

const empty = (): LeadForm => ({
  name: '',
  address: '',
  postcode: '',
  city: '',
  email: '',
  phone: '',
  space_size: '',
  space_unit: 'm2',
  rooms_count: null,
  notes: '',
})

/**
 * Het aantal ruimtes bepaalt of de sizing op één split-unit of op een
 * multi-split uitkomt, en het belscript noemt het in het gesprek. "4+" gaat
 * als 4 mee: het exacte aantal komt bij het huisbezoek.
 */
const roomOptions: { value: number; label: string }[] = [
  { value: 1, label: '1' },
  { value: 2, label: '2' },
  { value: 3, label: '3' },
  { value: 4, label: '4+' },
]

const form = reactive<LeadForm>(empty())
const errors = reactive<LeadErrors>({})
const success = ref(false)
const submitting = ref(false)
const submitError = ref('')

const emailOk = (v: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)
const postcodeOk = (v: string) => /^\d{4}\s?[A-Za-z]{2}$/.test(v)
const phoneOk = (v: string) => /^[0-9+\s()-]{8,20}$/.test(v)
const unitLabel = (unit: SpaceUnit) => (unit === 'm2' ? 'm²' : 'm³')

function setUnit(unit: SpaceUnit) {
  form.space_unit = unit
  if (errors.space_size) delete errors.space_size
}

function setRooms(count: number) {
  // Nogmaals klikken zet hem weer uit: het veld is optioneel.
  form.rooms_count = form.rooms_count === count ? null : count
}

function clearErrors() {
  ;(Object.keys(errors) as (keyof LeadForm)[]).forEach((k) => {
    delete errors[k]
  })
}

function validate(): boolean {
  clearErrors()
  if (!form.name) errors.name = 'Vul uw naam in.'
  if (!form.address) errors.address = 'Vul uw adres in.'
  if (!form.postcode) errors.postcode = 'Vul uw postcode in.'
  else if (!postcodeOk(form.postcode)) errors.postcode = 'Gebruik formaat 1234 AB.'
  if (!form.city) errors.city = 'Vul uw plaats in.'
  if (!form.email) errors.email = 'Vul uw e-mailadres in.'
  else if (!emailOk(form.email)) errors.email = 'Ongeldig e-mailadres.'
  if (!form.phone) errors.phone = 'Vul uw telefoonnummer in.'
  else if (!phoneOk(form.phone)) errors.phone = 'Ongeldig telefoonnummer.'
  if (form.space_size) {
    const n = Number(form.space_size.replace(',', '.'))
    if (!Number.isFinite(n) || n < 1 || n > 10000) {
      errors.space_size = `Voer een geldig aantal ${unitLabel(form.space_unit)} in.`
    }
  }
  return Object.keys(errors).length === 0
}

/**
 * Verstuurt de aanvraag naar POST /api/leads. Daar begint de agent-workflow:
 * verrijken, bellen, offerte mailen en nabellen.
 */
async function onSubmit() {
  if (!validate()) return

  submitting.value = true
  submitError.value = ''

  const payload: Record<string, unknown> = {
    name: form.name,
    email: form.email,
    phone: form.phone,
    address: form.address,
    postcode: form.postcode,
    city: form.city,
    notes: form.notes || null,
  }

  if (form.space_size) {
    payload.space_size = Number(form.space_size.replace(',', '.'))
    payload.space_unit = form.space_unit
  }

  if (form.rooms_count !== null) payload.rooms_count = form.rooms_count

  try {
    const response = await fetch(`${useRuntimeConfig().public.apiBase}/leads`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    })

    if (!response.ok) {
      const body = await response.json().catch(() => ({}))

      if (response.status === 422 && body.errors) {
        for (const [field, messages] of Object.entries(body.errors as Record<string, string[]>)) {
          if (field in form) errors[field as keyof LeadForm] = messages[0]
        }
        submitError.value = 'Controleer de gemarkeerde velden.'
        return
      }

      submitError.value = 'Het versturen lukte niet. Probeer het zo nog eens of bel ons.'
      return
    }

    success.value = true
  } catch {
    submitError.value = 'We konden geen verbinding maken. Probeer het zo nog eens of bel ons.'
  } finally {
    submitting.value = false
  }
}

function reset() {
  Object.assign(form, empty())
  clearErrors()
  success.value = false
  submitError.value = ''
}
</script>

<style scoped>
.offerte {
  padding-block: calc(var(--space) * 16) calc(var(--space) * 18);
}

.offerte__inner {
  display: grid;
  gap: calc(var(--space) * 8);
}

.offerte__title {
  margin: 0 0 12px;
  font-size: clamp(32px, 4vw, 48px);
  font-weight: 600;
  letter-spacing: -0.03em;
}

.offerte__lede {
  margin: 0;
  color: var(--color-ink-muted);
  font-size: 16px;
}

.offerte__grid {
  display: grid;
  gap: 28px;
}

.field {
  display: grid;
  gap: 8px;
}

.field span {
  font-size: 13px;
  color: var(--color-ink-muted);
}

.field input,
.field textarea {
  width: 100%;
  border: none;
  border-bottom: 1px solid var(--color-line);
  border-radius: 0;
  padding: 10px 0;
  background: transparent;
  color: var(--color-ink);
  outline: none;
  resize: vertical;
}

.field input:focus,
.field textarea:focus {
  border-bottom-color: var(--color-ink);
}

.field input[aria-invalid='true'],
.field textarea[aria-invalid='true'] {
  border-bottom-color: #c45c5c;
}

.field__measure {
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid var(--color-line);
}

.field__measure:focus-within {
  border-bottom-color: var(--color-ink);
}

.field__measure--invalid {
  border-bottom-color: #c45c5c;
}

.field__measure input {
  flex: 1;
  min-width: 0;
  border-bottom: none;
  padding-right: 0;
}

.field__measure input:focus {
  border-bottom: none;
}

.field__units {
  display: flex;
  flex-shrink: 0;
  gap: 2px;
  padding-bottom: 2px;
}

.field__unit {
  appearance: none;
  border: none;
  background: transparent;
  padding: 8px 6px;
  margin: 0;
  font: inherit;
  font-size: 13px;
  letter-spacing: 0.02em;
  color: var(--color-ink-muted);
  cursor: pointer;
  line-height: 1;
}

.field__unit.is-active {
  color: var(--color-ink);
  font-weight: 600;
}

.field__unit:focus-visible {
  outline: 2px solid var(--color-ink);
  outline-offset: 2px;
}

.field__rooms {
  display: flex;
  gap: 2px;
  border-bottom: 1px solid var(--color-line);
  padding-bottom: 2px;
}

.field__room {
  appearance: none;
  border: none;
  background: transparent;
  padding: 8px 12px;
  margin: 0;
  font: inherit;
  font-size: 13px;
  letter-spacing: 0.02em;
  color: var(--color-ink-muted);
  cursor: pointer;
  line-height: 1;
}

.field__room.is-active {
  color: var(--color-ink);
  font-weight: 600;
}

.field__room:focus-visible {
  outline: 2px solid var(--color-ink);
  outline-offset: 2px;
}

.field__error {
  color: #c45c5c;
  font-size: 12px;
}

.offerte__submit {
  margin-top: 12px;
  min-width: 180px;
}

.offerte__submit:disabled {
  opacity: 0.55;
  cursor: progress;
}

.offerte__error {
  margin: 12px 0 0;
  color: #c45c5c;
  font-size: 14px;
}

.offerte__success p {
  margin: 0 0 12px;
  font-size: 22px;
  letter-spacing: -0.02em;
}

.offerte__success-note {
  color: var(--color-ink-muted) !important;
  font-size: 14px !important;
}

@media (min-width: 800px) {
  .offerte__inner {
    grid-template-columns: 0.85fr 1.15fr;
    align-items: start;
  }

  .offerte__grid {
    grid-template-columns: 1fr 1fr;
  }

  .field--full {
    grid-column: 1 / -1;
  }
}
</style>
