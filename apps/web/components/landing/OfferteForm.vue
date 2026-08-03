<template>
  <section id="offerte" class="offerte">
    <div class="offerte__inner container">
      <div class="offerte__intro">
        <h2 class="offerte__title">Ontvang vrijblijvend advies.</h2>
        <p class="offerte__lede">We nemen contact op om uw situatie te bespreken.</p>
      </div>

      <div v-if="success" class="offerte__success" data-testid="offerte-success" role="status">
        <p>Bedankt. Uw aanvraag is klaar om te versturen zodra we live gaan.</p>
        <p class="offerte__success-note">Er is nog niets opgeslagen of verzonden.</p>
        <button type="button" class="btn-ghost" @click="reset">Opnieuw</button>
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

          <label class="field">
            <span>Aantal vierkante meters</span>
            <input
              v-model.trim="form.square_meters"
              name="square_meters"
              inputmode="numeric"
              :aria-invalid="!!errors.square_meters"
              :aria-describedby="errors.square_meters ? 'err-m2' : undefined"
            />
            <span v-if="errors.square_meters" id="err-m2" class="field__error">{{ errors.square_meters }}</span>
          </label>

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

        <button type="submit" class="btn-primary offerte__submit" data-testid="offerte-submit">
          Versturen
        </button>
      </form>
    </div>
  </section>
</template>

<script setup lang="ts">
type LeadForm = {
  name: string
  address: string
  postcode: string
  city: string
  email: string
  phone: string
  square_meters: string
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
  square_meters: '',
  notes: '',
})

const form = reactive<LeadForm>(empty())
const errors = reactive<LeadErrors>({})
const success = ref(false)

const emailOk = (v: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)
const postcodeOk = (v: string) => /^\d{4}\s?[A-Za-z]{2}$/.test(v)
const phoneOk = (v: string) => /^[0-9+\s()-]{8,20}$/.test(v)

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
  if (form.square_meters) {
    const n = Number(form.square_meters)
    if (!Number.isFinite(n) || n < 1 || n > 10000) {
      errors.square_meters = 'Voer een geldig aantal m² in.'
    }
  }
  return Object.keys(errors).length === 0
}

/**
 * Mirrors future thin POST /api/leads → { ok: true }.
 * v1: local mock only — no network, no PII logging.
 */
function mockSubmit(): { ok: true } {
  return { ok: true }
}

function onSubmit() {
  if (!validate()) return
  const res = mockSubmit()
  if (res.ok) success.value = true
}

function reset() {
  Object.assign(form, empty())
  clearErrors()
  success.value = false
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

.field__error {
  color: #c45c5c;
  font-size: 12px;
}

.offerte__submit {
  margin-top: 12px;
  min-width: 180px;
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
