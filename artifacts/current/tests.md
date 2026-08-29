# Tests — PASS

## API (`apps/api`)

```
vendor/bin/phpunit --no-coverage
OK (105 tests, 481 assertions)
```

| Suite | Wat het bewijst |
|-------|-----------------|
| `Unit/SizingCalculatorTest` | m² → m³-omrekening, isolatiefactor uit bouwjaar of opgave, ophogen naar standaardklasse, multisplitadvies bij meerdere ruimtes, terugval zonder ruimtemaat |
| `Unit/PhoneNumberTest` | 10 nummervarianten naar E.164, inclusief de gevallen die `null` horen op te leveren |
| `Unit/LeadEmailParserTest` | eigen formulier (platte tekst), leadportaal (HTML-tabel), terugval op de afzender, en het weigeren van een bericht zonder contactgegevens |
| `Feature/QuoteBuilderTest` | offerte binnen de marktrange, btw over het subtotaal, meerlengte verhoogt prijs én tijd, multisplit krijgt buitenunit plus n binnenunits, montageduur houdt rekening met de ploeggrootte, minimale opdrachtwaarde, versienummering, kortingsregel |
| `Feature/LeadWorkflowTest` | verrijken en inplannen, uitstel buiten het belvenster, lead zonder telefoonnummer gaat direct de cadans in, beantwoord gesprek kwalificeert en werkt gegevens bij, niet opnemen start de cadans, conversiegesprek staat op T+60 min, maximum aantal pogingen, `do_not_contact`, ontdubbeling, volledige tijdlijn |
| `Feature/ElevenLabsWebhookTest` | geen/verkeerde/verlopen handtekening geven 401, beantwoord gesprek zet transcript weg en start de offerte, kort gesprek telt als niet opgenomen, akkoord zet een afspraak in de wachtrij, dubbele webhook wordt niet twee keer verwerkt, onbekend gesprek geeft 404 |
| `Feature/SequenceRunnerTest` | de zes cadansstappen lopen af en eindigen op `unreachable`, een stap wacht tot zijn tijd, de cadans stopt zodra de lead reageert, dubbele start maakt geen tweede cadans, lead zonder mailadres slaat mailstappen over zonder te breken |
| `Feature/AppointmentSchedulerTest` | voorgestelde momenten liggen op werkdagen na de voorbereidingstijd, afspraak wordt vastgelegd en bevestigd, een tijd uit het gesprek wordt als Nederlandse kloktijd gelezen, het ICS-bestand is geldig (inclusief de 75-tekengrens per regel) |
| `Feature/EntryPriceTest` | kostprijs en marge worden vastgelegd, arbeid telt tegen het kostentarief mee, de vanaf-prijs werkt als ondergrens, het totaal landt exact op de advertentieprijs (geen afrondingscent), het instappakket topt een instapklus af en markeert de marge, klussen die er niet onder vallen blijven ongemoeid, de vanaf-prijs-check keurt € 899 af en € 1.449 goed, de adviesprijs haalt precies de drempel, de waarschuwing komt in de tijdlijn én in de mail naar de ondernemer, en de voice agent krijgt de vanaf-prijs mee |
| `Feature/VoiceAgentPromptTest` | bewaakt het contract met het gespreksscript bij ElevenLabs: elke `{{variabele}}` in de prompt wordt ook echt meegestuurd, geen enkele variabele komt leeg binnen, de openingszin meldt de digitale assistent én de opname, elk gedocumenteerd veld wordt daadwerkelijk op de lead overgenomen, en elke genoemde uitkomst bestaat als `CallOutcome` |
| `Feature/AdminApiTest` | formulierintake met normalisatie, weigeren van onbekende velden, afscherming van het dashboard (ook zonder JSON-header), inloggen, sessieduur met en zonder "onthoud mij", een verlopen token dat geen toegang meer geeft, inloggen op een tweede apparaat dat de eerste sessie intact laat, uitloggen dat alleen het eigen apparaat raakt, opruimen van verlopen tokens, filteren en het níét lekken van contactgegevens in de lijst, bewerken, alle her-aftrap-acties, funnelberekening, catalogus die doorwerkt in de offerte, geheimen die niet teruggestuurd worden, cadansbeheer |

## Web (`apps/web`)

```
pnpm run typecheck   → schoon
pnpm run build       → 14 routes gegenereerd
```

## Handmatige doorloop tegen een draaiende API

Uitgevoerd in proefmodus met de fake voice-client:

1. `POST /api/leads` → 202, lead aangemaakt, telefoonnummer genormaliseerd naar `+31612345678`, postcode naar `3811 AB`
2. Queue verwerkt → status `enriched`, advies single split 5,0 kW op basis van 88,4 m³ × 40 W/m³
3. Kwalificatiegesprek gestart → status `calling`
4. Ondertekende post-call webhook → 200, transcript en samenvatting opgeslagen, `pipe_length_m` bijgewerkt naar 8
5. Offerte opgesteld en gemaild → `OFF-2026-0001-1`, € 2.049,75 excl. / € 2.480,20 incl. btw, 4,0 uur montage, 10 regels
6. Conversiegesprek op T+60 min ingepland, doorgezet, akkoord via webhook
7. Afspraak vastgelegd op 15-09-2026 08:00 Nederlandse tijd, offerte op `accepted`, lead op `appointment_scheduled`
8. Dashboard-API gecontroleerd: leadlijst, funnel (1 lead door alle stappen), catalogus met 51 artikelen
9. Aparte lead die niet opneemt: cadans doorlopen tot het einde → 3 mails, 3 belpogingen ingepland, indicatie-offerte verstuurd, status `unreachable`, ondernemer geïnformeerd
10. Vanaf-prijs: `GET /api/admin/catalog` meldt dat € 899 onder de kostprijs ligt (€ 159,02 verlies per klus) en adviseert € 1.284,03; na het instappakket via `PATCH /api/admin/settings` aan te zetten landt een instapklus op precies € 899,00 met een kortingsregel van € 463,22, marge −21,4%, en verschijnt `margin_warning` in de tijdlijn

## Wat tijdens het bouwen is gevonden en gerepareerd

| Bevinding | Herkomst | Fix |
|-----------|----------|-----|
| Montagetijd van de kernboring werd dubbel geteld | testverwachting week af | normtijd verplaatst naar de eigen catalogusregel; `docs/research/pricing-baseline.md` bijgewerkt |
| Afspraaktijden werden zonder tijdzone opgeslagen, waardoor 08:00 als 10:00 terugkwam | test op een voorkeursmoment | opslaan in UTC met aparte `timezone`-kolom |
| Een tijd uit het gesprek werd als UTC gelezen in plaats van als Nederlandse kloktijd | handmatige doorloop | `AppointmentScheduler::parseLocal()`, met test |
| `SettingsRepository` negeerde de config zodra een fallback werd meegegeven, waardoor élke `.env`-instelling genegeerd werd | handmatige doorloop (proefmodus deed niets) | volgorde is nu database → config → fallback; testomgeving vastgepind in `phpunit.xml` |
| Een verzoek zonder `Accept: application/json` kreeg een 500 in plaats van een 401 | handmatige doorloop | `redirectGuestsTo(null)`, met regressietest |
| Platgeslagen HTML-tabellen leverden waarden met een afsluitende dubbele punt | parsertest | waarde wordt nu ontdaan van trailing `:` |
| Een offerte die op de advertentieprijs landde kwam door btw-afronding op € 899,01 uit | test op de vanaf-prijs | het totaal wordt vastgepind op het geadverteerde bedrag en de btw volgt uit het verschil |
| **Het dashboard had helemaal geen navigatiebalk**: `app.vue` miste `<NuxtLayout>`, waardoor `definePageMeta({ layout })` stil genegeerd werd | doorloop in de browser | `<NuxtLayout>` toegevoegd; landingspagina ongewijzigd |
| Afspraaktijden werden in de tijdzone van de kijker getoond in plaats van die van de klus (08:00 verscheen als 06:00) | doorloop in de browser | `timezone` toegevoegd aan de API-payload en meegegeven aan de datumopmaak, met regressietest |
| Een herziene offerte trok een al geboekte klant terug de funnel in en plande een nieuw conversiegesprek | doorloop in de browser | status blijft staan bij `appointment_scheduled` en `won`; er wordt geen gesprek meer ingepland, met test |

## Doorloop in een headless browser

De volledige dashboardapplicatie is met een headless Chromium doorlopen tegen een
draaiende API met demodata (`DemoSeeder`): **31 van de 31 checks geslaagd**.

Gecontroleerd: afscherming van `/dashboard`, foutmelding bij een verkeerd
wachtwoord, inloggen, de navigatiebalk, de KPI-tegels en alle acht funnelstappen,
de leadlijst met status- en zoekfilter, het leaddetail met tijdlijn (26
gebeurtenissen), beide transcripten, offerte met kostprijs en marge, de afspraak
in de juiste tijdzone, verstuurde mail, clientvalidatie op een ongeldig bouwjaar,
gegevens opslaan en zien blijven staan, twee "opnieuw aftrappen"-acties, de
vanaf-prijs-check, een catalogusprijs aanpassen, de cadans met zes stappen en het
aanpassen daarvan, alle zes instellingsgroepen met gemaskeerde geheimen,
instellingen opslaan, uitloggen, opnieuw afgeschermd zijn, en de landingspagina
die intact blijft.

Geen console- of API-fouten, op de 422 na die de test zelf uitlokt met een
verkeerd wachtwoord.

## Niet gedekt

De browserdoorloop is een handmatig script, nog geen Playwright-suite in CI; die
service is in dit project nog steeds uitgesteld (zie
`docs/runbooks/commands.md`).
