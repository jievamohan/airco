<template>
  <div>
    <div class="dash__head">
      <div>
        <h1 class="dash__title">Catalogus</h1>
        <p class="dash__lede">Inkoopprijzen, marges en normtijden waarmee de offertes worden gerekend.</p>
      </div>
    </div>

    <p v-if="herkomst" class="notice" :class="herkomst.voorlopig === 0 ? 'notice--ok' : ''">
      <strong>{{ herkomst.echt }}</strong> van de {{ herkomst.echt + herkomst.voorlopig }} actieve regels rekenen met een
      echte inkoopprijs<span v-if="herkomst.prijslijsten.length"> uit {{ herkomst.prijslijsten.join(' en ') }}</span>.
      <template v-if="herkomst.voorlopig">
        De overige {{ herkomst.voorlopig }} zijn nog afgeleid uit marktonderzoek — herkenbaar aan het label
        <span class="badge badge--warn">Voorlopig</span>; onderbouwing in <code>docs/research/pricing-baseline.md</code>.
      </template>
      <template v-if="herkomst.vervallen">
        {{ herkomst.vervallen }} vervangen regels staan op vervallen en tellen niet meer mee.
      </template>
    </p>

    <section v-if="check" class="panel">
      <h2 class="panel__title">Vanaf-prijs</h2>
      <p class="panel__note">
        Klopt de prijs uit jullie advertentie nog met de huidige inkoopprijzen en normtijden?
        Deze berekening loopt mee zodra je hieronder iets aanpast.
      </p>

      <p class="notice" :class="check.achievable ? 'notice--ok' : 'notice--bad'" role="status">
        {{ check.message }}
      </p>

      <dl class="facts">
        <dt>Geadverteerde vanaf-prijs</dt>
        <dd>{{ fmt.euro(check.entry_price_cents) }} incl. btw</dd>
        <dt>Goedkoopst mogelijke klus</dt>
        <dd>{{ fmt.euro(check.cheapest_total_cents) }} incl. btw</dd>
        <dt>Kostprijs van die klus</dt>
        <dd>{{ fmt.euro(check.cheapest_cost_cents) }} excl. btw</dd>
        <dt>Break-even</dt>
        <dd>{{ fmt.euro(check.break_even_total_cents) }} incl. btw</dd>
        <dt>Resultaat op de vanaf-prijs</dt>
        <dd :class="check.result_at_entry_price_cents < 0 ? 'is-bad' : 'is-ok'">
          {{ fmt.euro(check.result_at_entry_price_cents) }}
          ({{ fmt.number(check.margin_at_entry_price_pct, 1) }}% marge)
        </dd>
        <dt>Advies bij {{ fmt.number(check.minimum_margin_pct, 0) }}% marge</dt>
        <dd>{{ fmt.euro(check.advised_entry_price_cents) }} incl. btw</dd>
        <dt>Instappakket</dt>
        <dd class="facts__block">
          {{ check.entry_package_enabled ? 'Aan' : 'Uit' }} —
          <span class="muted">
            {{ check.entry_package_enabled
              ? 'een eenvoudige instapklus wordt afgetopt op de vanaf-prijs'
              : 'de vanaf-prijs geldt als ondergrens, niet als actieprijs inclusief montage' }}
          </span>
        </dd>
      </dl>

      <p class="small muted" style="margin-top: 10px">
        De vanaf-prijs, het instappakket en de margedrempel stel je in onder
        <NuxtLink to="/dashboard/instellingen">Instellingen → Prijsstelling</NuxtLink>.
      </p>
    </section>

    <p v-if="flash" class="notice notice--ok" role="status">{{ flash }}</p>
    <p v-if="error" class="notice notice--bad" role="alert">{{ error }}</p>

    <section v-if="pricing" class="panel">
      <h2 class="panel__title">Algemene calculatie</h2>
      <dl class="facts">
        <dt>Btw-tarief</dt><dd>{{ fmt.number(pricing.vat_rate, 0) }}%</dd>
        <dt>Uurtarief arbeid</dt><dd>{{ fmt.euro(pricing.labour_sell_rate_cents) }} per monteursuur, excl. btw</dd>
        <dt>Ploeggrootte</dt><dd>{{ pricing.crew_size }} monteurs</dd>
        <dt>Kostprijs arbeid</dt><dd>{{ fmt.euro(pricing.labour_cost_rate_cents) }} per monteursuur, excl. btw</dd>
        <dt>Margedrempel</dt><dd>{{ fmt.number(pricing.minimum_margin_pct, 0) }}%</dd>
        <dt>Standaardklasse</dt><dd>{{ tierLabels[pricing.default_tier] ?? pricing.default_tier }}</dd>
      </dl>
      <p class="small muted" style="margin-top: 10px">
        Deze waarden pas je aan onder <NuxtLink to="/dashboard/instellingen">Instellingen → Prijsstelling</NuxtLink>.
      </p>
    </section>

    <div class="filters">
      <select v-model="kind" @change="load">
        <option value="">Alle soorten</option>
        <option value="equipment_set">Single split sets</option>
        <option value="equipment_outdoor">Multisplit buitenunits</option>
        <option value="equipment_indoor">Binnenunits</option>
        <option value="equipment_heatpump">Warmtepompen</option>
        <option value="equipment_accessory">Toebehoren</option>
        <option value="material">Materiaal</option>
        <option value="surcharge">Toeslagen</option>
      </select>
      <select v-model="tier" @change="load">
        <option value="">Alle klassen</option>
        <option value="budget">Voordelig</option>
        <option value="mid">Middenklasse</option>
        <option value="premium">Premium</option>
      </select>
      <select v-model="priceSource" @change="load">
        <option value="">Alle herkomst</option>
        <option value="pricelist">Uit prijslijst</option>
        <option value="dashboard">Eigen invoer</option>
        <option value="provisional">Nog voorlopig</option>
      </select>
      <select v-model="active" @change="load">
        <option value="1">Actief</option>
        <option value="0">Vervallen</option>
      </select>
    </div>

    <section class="panel panel--bare">
      <!--
        Een kaart per artikel: eerst wat het is, dan de drie getallen die je
        zelf invult, en daaronder wat daar uitkomt. De verkoopprijs is het
        antwoord op de vraag die je stelt met inkoop en marge, dus die staat
        eronder en niet ertussen.
      -->
      <div v-if="compact" class="cards">
        <div v-for="item in items" :key="item.id" class="card">
          <span class="card__head">
            <span class="card__title">{{ item.name }}</span>
            <span v-if="item.tier" class="badge">{{ tierLabels[item.tier] }}</span>
            <span class="badge" :class="item.price_is_real ? 'badge--ok' : 'badge--warn'">{{ item.price_source_label }}</span>
          </span>
          <span class="card__sub">{{ item.sku }} · per {{ item.unit }}<template v-if="item.brand"> · {{ item.brand }}</template></span>

          <div class="card__fields">
            <label class="field">
              <span class="field__label">Inkoop €</span>
              <input v-model.number="item.cost_euro" type="number" step="0.01" min="0" class="cell" />
            </label>
            <label class="field">
              <span class="field__label">Marge %</span>
              <input v-model.number="item.margin_pct" type="number" step="0.1" min="0" class="cell" />
            </label>
            <label class="field">
              <span class="field__label">Normtijd</span>
              <input v-model.number="item.labour_minutes" type="number" step="5" min="0" class="cell" />
            </label>
          </div>

          <span class="card__figures">
            <span class="card__amount">{{ fmt.euro(Math.round(item.cost_euro * 100 * (1 + item.margin_pct / 100))) }}</span>
            <span class="card__aside">verkoop excl. btw</span>
          </span>

          <div class="card__actions">
            <label class="toggle">
              <input v-model="item.active" type="checkbox" />
              <span>Actief</span>
            </label>
            <button type="button" class="btn btn--ghost btn--small" :disabled="busy === item.id" @click="save(item)">
              Opslaan
            </button>
          </div>
        </div>
      </div>

      <div v-else class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Artikel</th>
              <th>Herkomst</th>
              <th>Klasse</th>
              <th class="num">Inkoop excl. btw</th>
              <th class="num">Marge %</th>
              <th class="num">Verkoop excl. btw</th>
              <th class="num">Normtijd (min)</th>
              <th class="num">Actief</th>
              <th class="num"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <strong>{{ item.name }}</strong>
                <span class="small muted" style="display: block">
                  {{ item.sku }} · per {{ item.unit }}<template v-if="item.brand"> · {{ item.brand }}</template>
                </span>
              </td>
              <td>
                <span class="badge" :class="item.price_is_real ? 'badge--ok' : 'badge--warn'">{{ item.price_source_label }}</span>
                <span v-if="item.price_list_ref" class="small muted" style="display: block">{{ item.price_list_ref }}</span>
              </td>
              <td class="muted">{{ item.tier ? tierLabels[item.tier] : '—' }}</td>
              <td class="num"><input v-model.number="item.cost_euro" type="number" step="0.01" min="0" class="cell" /></td>
              <td class="num"><input v-model.number="item.margin_pct" type="number" step="0.1" min="0" class="cell cell--narrow" /></td>
              <td class="num">{{ fmt.euro(Math.round(item.cost_euro * 100 * (1 + item.margin_pct / 100))) }}</td>
              <td class="num"><input v-model.number="item.labour_minutes" type="number" step="5" min="0" class="cell cell--narrow" /></td>
              <td class="num"><input v-model="item.active" type="checkbox" /></td>
              <td class="num">
                <button type="button" class="btn btn--ghost btn--small" :disabled="busy === item.id" @click="save(item)">
                  Opslaan
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="!items.length && !error" class="empty">Geen artikelen gevonden.</p>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'dashboard', middleware: 'dashboard-auth' })

type Item = {
  id: number
  sku: string
  kind: string
  name: string
  tier: string | null
  unit: string
  cost_cents: number
  cost_euro: number
  margin_pct: number
  labour_minutes: number
  active: boolean
  brand: string | null
  price_source: string
  price_source_label: string
  price_is_real: boolean
  price_list_ref: string | null
}

type Herkomst = {
  echt: number
  voorlopig: number
  vervallen: number
  prijslijsten: string[]
}

type Pricing = {
  vat_rate: number
  labour_sell_rate_cents: number
  labour_cost_rate_cents: number
  crew_size: number
  entry_price_cents: number
  entry_package_enabled: boolean
  minimum_margin_pct: number
  default_tier: string
}

type EntryPriceCheck = {
  entry_price_cents: number
  entry_package_enabled: boolean
  cheapest_total_cents: number
  cheapest_cost_cents: number
  margin_at_entry_price_pct: number
  result_at_entry_price_cents: number
  achievable: boolean
  minimum_margin_pct: number
  break_even_total_cents: number
  advised_entry_price_cents: number
  message: string
}

const api = useApi()
const fmt = useDashboardFormat()
const compact = useIsCompact()

const items = ref<Item[]>([])
const pricing = ref<Pricing | null>(null)
const check = ref<EntryPriceCheck | null>(null)
const herkomst = ref<Herkomst | null>(null)
const kind = ref('')
const tier = ref('')
const priceSource = ref('')
const active = ref('1')
const busy = ref<number | null>(null)
const flash = ref('')
const error = ref('')

const tierLabels: Record<string, string> = { budget: 'Voordelig', mid: 'Middenklasse', premium: 'Premium' }

async function load() {
  error.value = ''
  const params = new URLSearchParams()
  if (kind.value) params.set('kind', kind.value)
  if (tier.value) params.set('tier', tier.value)
  if (priceSource.value) params.set('price_source', priceSource.value)
  params.set('active', active.value)

  try {
    const result = await api.get<{
      items: Item[]
      herkomst: Herkomst
      pricing: Pricing
      entry_price_check: EntryPriceCheck
    }>(`/admin/catalog${params.toString() ? `?${params}` : ''}`)
    items.value = result.items.map((item) => ({ ...item, cost_euro: item.cost_cents / 100 }))
    herkomst.value = result.herkomst
    pricing.value = result.pricing
    check.value = result.entry_price_check
  } catch (e) {
    error.value = (e as ApiError).message
  }
}

async function save(item: Item) {
  busy.value = item.id
  flash.value = ''
  error.value = ''

  try {
    await api.patch(`/admin/catalog/${item.id}`, {
      cost_cents: Math.round(item.cost_euro * 100),
      margin_pct: item.margin_pct,
      labour_minutes: item.labour_minutes,
      active: item.active,
    })
    flash.value = `${item.name} is bijgewerkt.`
    await load()
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
  width: 110px;
  text-align: right;
  font: inherit;
  padding: 5px 8px;
  border: 1px solid var(--dash-line);
  border-radius: 6px;
}

.cell--narrow { width: 80px; }

@media (max-width: 720px) {
  /* Onder de 16px zoomt Safari op iOS in zodra het veld focus krijgt. */
  .cell { font-size: 16px; }
}

.field .cell { width: 100%; }
</style>
