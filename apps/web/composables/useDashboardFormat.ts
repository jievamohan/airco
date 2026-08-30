/**
 * Weergavehulpjes: bedragen komen als centen uit de API en tijden als ISO-8601.
 */
export function useDashboardFormat() {
  const euro = (cents: number | null | undefined) =>
    cents === null || cents === undefined
      ? '—'
      : new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(cents / 100)

  /**
   * Hetzelfde bedrag op hele euro's. In een lijst die je scant dragen de
   * centen niets bij; op de detailpagina staat het bedrag voluit.
   */
  const euroRound = (cents: number | null | undefined) =>
    cents === null || cents === undefined
      ? '—'
      : new Intl.NumberFormat('nl-NL', {
          style: 'currency',
          currency: 'EUR',
          minimumFractionDigits: 0,
          maximumFractionDigits: 0,
        }).format(cents / 100)

  const number = (value: number | null | undefined, decimals = 1) =>
    value === null || value === undefined
      ? '—'
      : new Intl.NumberFormat('nl-NL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value)

  /**
   * Tijden komen als UTC uit de API. Zonder expliciete zone rendert de browser
   * ze in de tijdzone van de kijker; voor een afspraak is dat verkeerd, want
   * die staat in de tijdzone van de klus. Geef die dan mee.
   */
  const dateTime = (iso: string | null | undefined, timeZone?: string) =>
    !iso
      ? '—'
      : new Intl.DateTimeFormat('nl-NL', {
          day: 'numeric',
          month: 'short',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          ...(timeZone ? { timeZone } : {}),
        }).format(new Date(iso))

  /** Alleen het klokje, voor lijsten waar de dag al boven de groep staat. */
  const time = (iso: string | null | undefined) =>
    !iso
      ? '—'
      : new Intl.DateTimeFormat('nl-NL', { hour: '2-digit', minute: '2-digit' }).format(new Date(iso))

  /**
   * "Vandaag" en "Gisteren" lezen sneller dan een datum, en verderop terug in
   * de tijd zegt de datum zelf weer meer dan een aantal dagen geleden.
   */
  const dayLabel = (iso: string | null | undefined) => {
    if (!iso) return 'Onbekend'

    const dag = new Date(iso)
    const vandaag = new Date()
    const opDag = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()
    const verschil = Math.round((opDag(vandaag) - opDag(dag)) / 86_400_000)

    if (verschil === 0) return 'Vandaag'
    if (verschil === 1) return 'Gisteren'

    return new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'short' }).format(dag)
  }

  const date = (iso: string | null | undefined) =>
    !iso ? '—' : new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso))

  const relative = (iso: string | null | undefined) => {
    if (!iso) return '—'
    const diff = new Date(iso).getTime() - Date.now()
    const minutes = Math.round(diff / 60000)
    const formatter = new Intl.RelativeTimeFormat('nl-NL', { numeric: 'auto' })

    if (Math.abs(minutes) < 60) return formatter.format(minutes, 'minute')
    if (Math.abs(minutes) < 60 * 24) return formatter.format(Math.round(minutes / 60), 'hour')
    return formatter.format(Math.round(minutes / (60 * 24)), 'day')
  }

  const duration = (minutes: number | null | undefined) =>
    minutes === null || minutes === undefined ? '—' : `${number(minutes / 60, 1)} uur`

  return { euro, euroRound, number, dateTime, time, dayLabel, date, relative, duration }
}
