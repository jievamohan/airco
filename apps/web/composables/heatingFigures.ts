/**
 * Exact figures + copy from sources/apple_style_scroll_section_design_brief.md
 */
export const HEATING_TRACK_VH = 350
export const HEATING_TRACK_VH_MOBILE = 0 /* linear stack — no sticky track */

export const HEATING_BAR_RATIO = 2.43

export const HEATING_FIGURES = {
  aircoPrice: '€0,07',
  cvPrice: '€0,17',
  savePercent: '59%',
  savePercentValue: 59,
  perKwh: 'per kWh warmte',
  aircoLabel: 'Airco',
  cvLabel: 'CV-ketel',
} as const

export const HEATING_COPY = {
  s1Title: 'Dezelfde warmte.',
  s1Sub: 'Twee manieren om haar te maken.',
  s2Line: 'Dezelfde warmte. Andere prijs.',
  s3Prefix: 'tot',
  s3Save: 'goedkoper',
  s3Same: 'Voor dezelfde hoeveelheid warmte.',
  basedOn: 'Gebaseerd op:',
  disclaimer:
    'Dit is een rekenvoorbeeld. De werkelijke besparing hangt af van woning, installatie, buitentemperatuur en gebruik.',
} as const

export const HEATING_ASSUMPTIONS = [
  '€0,28 per kWh stroom',
  '€1,40 per m³ gas',
  'COP 4',
  'ketelrendement 80,7%',
] as const

/** One-line assumptions for Scene 3 footnotes. */
export const HEATING_ASSUMPTIONS_LINE = `${HEATING_COPY.basedOn} ${HEATING_ASSUMPTIONS.join(' · ')}`

export type HeatingScrollPhase =
  | 'same-heat'
  | 'price'
  | 'conclusion'
  | 'reduced'
