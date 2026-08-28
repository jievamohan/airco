# PR — Autonome lead-to-appointment agent met CRM-dashboard

## Wat dit toevoegt

Een aanvraag die binnenkomt — via het formulier op de site of via de mailbox —
wordt voortaan zelfstandig afgehandeld: verrijken, bellen met een ElevenLabs
voice agent, direct een offerte mailen, na een uur nabellen om te converteren, en
bij akkoord meteen een afspraak in de agenda zetten. Neemt de lead niet op, dan
loopt er een opvolgcadans van bel- en mailmomenten. De ondernemer krijgt bij
elke mijlpaal bericht.

Daarnaast een CRM-dashboard om de status te volgen, de funnel te analyseren,
gegevens aan te passen en elke stap opnieuw af te trappen.

## Structuur

* `apps/api` — nieuwe Laravel 12-applicatie: CRM, workflow-engine, integraties
* `apps/web/pages/dashboard` — het CRM-dashboard (client-side, afgeschermd)
* `apps/web/components/landing/OfferteForm.vue` — verstuurt nu echt naar de API
* `docs/research/pricing-baseline.md` — de prijsbasis met bronnen en methode
* `docs/runbooks/agent-workflow.md` — wat er draait, hoe je het instelt, wat je doet als het misgaat

## Werking in het kort

```
mailbox / formulier → verrijken → kwalificatiegesprek → offerte mailen
                                                            │
                                         +60 min → conversiegesprek → afspraak
                                                            │
                              geen gehoor → cadans (bel + mail) → onbereikbaar
```

Elke stap schrijft een regel in de tijdlijn en is vanuit het dashboard opnieuw af
te trappen.

## Prijzen en montagetijden

We hadden nog geen eigen inkoop- of calculatiegegevens. De catalogus is daarom
gevuld met cijfers die zijn afgeleid uit openbaar marktonderzoek; methode en
bronnen staan in `docs/research/pricing-baseline.md`. Een 3,5 kW single split
komt uit op ± € 2.400 incl. btw, binnen de waargenomen marktrange van
€ 1.900–2.800. **Deze cijfers zijn voorlopig** en horen vervangen te worden via
Dashboard → Catalogus; de seeder overschrijft aangepaste regels nooit.

De geadverteerde vanaf-prijs van € 899 is als instelbare ondergrens opgenomen,
met een optioneel instappakket dat een eenvoudige klus daadwerkelijk op die
prijs aftopt (standaard uit, omdat het € 159 per klus kost). Elke offerte legt
kostprijs en marge vast en wordt gemarkeerd zodra die onder de drempel zakt;
Dashboard → Catalogus → Vanaf-prijs rekent continu door of de advertentie nog
klopt.

## Poorten

* PHPUnit: 94 tests, 367 assertions — groen
* Volledige doorloop van het dashboard in een headless browser: 31/31 checks
* PHPStan level 6 met larastan: schoon, zonder baseline of ignores
* Pint: schoon
* `nuxt typecheck` en `nuxt build`: schoon
* `composer audit` en `pnpm audit --prod`: schoon (`nanoid` is via een override
  naar een gepatchte versie getild)

## Wat er nog nodig is voordat dit live kan

1. Echte inkoopprijzen, marges en normtijden in de catalogus, plus de keuze wat
   de advertentieprijs van € 899 dekt: het apparaat of apparaat plus montage.
2. Koppelingen invullen: mailbox, ElevenLabs (agent, nummer, webhook-secret) en
   een agenda (Google of Apple).
3. De API uitrollen op de VPS, met een queue-worker en een cron-regel voor
   `schedule:run` — zie de runbook.
4. De repository-variabele `PUBLIC_API_BASE` in GitHub zetten, anders wijst de
   gegenereerde site naar localhost.
5. In de ElevenLabs-prompt melden dat het een AI-assistent is en dat er wordt
   opgenomen, en een bewaartermijn voor transcripten afspreken.

Tot dat rond is, draait de agent veilig met `AGENT_DRY_RUN=true`: de volledige
workflow loopt, maar er wordt niet gebeld, gemaild of geboekt.
