# Runbook — lead-to-appointment agent

Van binnengekomen aanvraag tot ingeplande installatie, zonder handmatige stap.
Dit document beschrijft wat er draait, wat er ingesteld moet worden en wat je
doet als er iets misgaat.

---

## 1. Wat er gebeurt

```
mailbox / websiteformulier
        │
        ▼  LeadIntake            ontdubbelt op e-mail + telefoon binnen 30 dagen
   ProcessNewLeadJob             sizing, systeemadvies, notificatie naar de ondernemer
        │
        ▼  agent:tick            zet de belafspraak door zodra het belvenster open is
  kwalificatiegesprek            ElevenLabs voice agent, dynamische variabelen per lead
        │
        ▼  post-call webhook     transcript, samenvatting en verzamelde velden
     SendQuoteJob                offerte opstellen, pdf renderen, mailen
        │
        ▼  +60 minuten
   conversiegesprek              offerte doornemen, bezwaren, datum prikken
        │
        ▼  BookAppointmentJob    Google of Apple agenda + ICS naar de klant
   afspraak ingepland            ondernemer krijgt bericht
```

Neemt de lead niet op, dan start de cadans uit `sequences`: bel- en mailstappen
met oplopende wachttijden. Reageert de lead, dan stopt de cadans; is de rij op,
dan komt de lead op `unreachable` en krijgt de ondernemer bericht.

Elke stap schrijft een regel in `lead_events`; dat is de tijdlijn in het dashboard.

---

## 2. Processen die moeten draaien

| Proces | Commando | Frequentie |
|--------|----------|------------|
| Scheduler | `php artisan schedule:work` (of cron op `schedule:run`, elke minuut) | continu |
| Queue-worker | `php artisan queue:work --tries=3 --timeout=120` | continu |

De scheduler roept zelf aan:

* `leads:poll-mailbox` — elke vijf minuten
* `agent:tick` — elke minuut
* verlopen offertes opruimen — dagelijks 03:15

Cron-regel op een DirectAdmin-VPS:

```
* * * * * cd /pad/naar/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

De queue-worker draait als systemd-unit of onder supervisor; zonder worker
blijven verrijking, offertes en afspraken in de wachtrij staan.

---

## 3. Instellen

Alles staat in `.env` (zie `.env.example`) en is daarna aanpasbaar via
**Dashboard → Instellingen**. De databasewaarde wint van de `.env`-waarde.

### Mailbox

`MAILBOX_ENABLED=true` plus host, poort, gebruikersnaam en wachtwoord. Er wordt
gelezen uit `MAILBOX_FOLDER`; verwerkte berichten worden als gelezen gemarkeerd.
Gebruik een aparte mailbox of een map waar alleen leadmail binnenkomt.

De parser leest "Label: waarde"-regels en herkent onder meer naam, e-mail,
telefoon, adres, postcode, plaats, ruimtemaat en aantal ruimtes — in platte tekst
en in HTML-tabellen. Nieuwe labels toevoegen kan in
`app/Services/Mailbox/LeadEmailParser.php` (`LABELS`).

### Voice agent (ElevenLabs)

Het volledige gespreksscript, de agent-instellingen en de veldafspraken staan in
**[voice-agent-prompt.md](./voice-agent-prompt.md)** — plak die prompt en die
dataverzameling over in het ElevenLabs-dashboard. In het kort:

1. Maak in het ElevenLabs-dashboard een Conversational AI-agent aan in het
   Nederlands en koppel er een uitgaand telefoonnummer aan.
2. Zet in de agentprompt de dynamische variabelen die wij meesturen:
   `{{klant_voornaam}}`, `{{klant_adres}}`, `{{ruimte_omschrijving}}`,
   `{{geadviseerd_systeem}}`, `{{geadviseerd_vermogen}}`, `{{gespreksdoel}}`,
   `{{ontbrekende_gegevens}}`, en bij het conversiegesprek `{{offerte_nummer}}`,
   `{{offerte_bedrag}}`, `{{montageduur}}` en `{{korting_bij_direct_akkoord}}`.
   De volledige lijst staat in `app/Services/Voice/CallVariables.php`.
3. Configureer data collection zodat de agent deze velden terugstuurt:
   `outcome` (een waarde uit `App\Enums\CallOutcome`), `appointment_agreed`,
   `appointment_start`, `rooms_count`, `space_size`, `space_unit`,
   `building_year`, `insulation`, `floor_level`, `wall_type`,
   `outdoor_unit_placement`, `pipe_length_m`, `needs_condensate_pump`,
   `needs_extra_group`, `desired_start`, `notes`.
4. Zet de post-call webhook op `POST https://<api-host>/api/webhooks/elevenlabs/post-call`
   en vul hetzelfde secret in bij `ELEVENLABS_WEBHOOK_SECRET`.

De handtekening wordt constant-time gecontroleerd en verzoeken ouder dan
`ELEVENLABS_WEBHOOK_TOLERANCE` seconden worden geweigerd.

### Agenda

`CALENDAR_PROVIDER=google`, `apple` of `none`.

* **Google** — OAuth-client met scope `https://www.googleapis.com/auth/calendar.events`,
  daarna eenmalig een refresh-token ophalen en in `GOOGLE_REFRESH_TOKEN` zetten.
  `GOOGLE_CALENDAR_ID` is meestal `primary`.
* **Apple** — Apple ID plus een app-specifiek wachtwoord (appleid.apple.com →
  Beveiliging). `APPLE_CALDAV_CALENDAR_PATH` is het pad van de agenda binnen het
  CalDAV-account, bijvoorbeeld `1234567890/calendars/work`.
* **none** — de afspraak staat alleen in het CRM; de klant krijgt wel een
  ICS-bijlage.

### Belvensters

Staan in `config/agent.php` onder `calling_windows`, per ISO-weekdag
(1 = maandag). Buiten het venster geplande gesprekken schuiven automatisch naar
de eerstvolgende opening; zondag staat standaard uit.

---

## 4. Proefmodus

`AGENT_DRY_RUN=true` laat de volledige workflow lopen — statussen, offertes,
timeline, cadans — maar belt niet, mailt niet en schrijft niet in een agenda.
Mails krijgen status `skipped`. Ideaal om de flow te bekijken voordat de
koppelingen live gaan.

---

## 5. Prijzen en montagetijden

De catalogus is geseed met voorlopige, uit marktonderzoek afgeleide cijfers; de
onderbouwing en bronnen staan in `docs/research/pricing-baseline.md`. Vervang ze
via **Dashboard → Catalogus**. De seeder is idempotent: hij vult alleen aan wat
ontbreekt en overschrijft nooit een aangepaste regel.

Algemene calculatieparameters (btw, uurtarief, ploeggrootte, standaardklasse)
staan onder **Instellingen → Prijsstelling**.

### Vanaf-prijs

De geadverteerde "vanaf"-prijs staat als bedrag **inclusief btw** onder
Instellingen, in dezelfde termen als de advertentie. Hij werkt als ondergrens:
geen enkele offerte komt eronder.

Wil je een eenvoudige instapklus daadwerkelijk voor die prijs aanbieden — ook als
dat onder de kostprijs uitkomt — zet dan **Instappakket** aan. Dat topt een
offerte af op de vanaf-prijs, maar alleen als de klus er echt onder valt:

* enkele binnenunit, single split
* niet meer dan het ingestelde maximale vermogen (standaard 2,5 kW)
* leidinglengte binnen de standaard 5 meter
* begane grond of eerste verdieping
* geen condenspomp en geen extra elektragroep

Valt de klus daarbuiten, dan rekent de engine gewoon door. Elke offerte krijgt
een kostprijs en een brutomarge; zakt die onder de **margedrempel**, dan wordt de
offerte gemarkeerd, komt er een regel in de tijdlijn en staat de waarschuwing in
de notificatiemail naar de ondernemer.

Onder **Catalogus → Vanaf-prijs** rekent het dashboard continu door of de
geadverteerde prijs nog haalbaar is met de huidige inkoopprijzen en normtijden,
en wat de prijs zou moeten zijn om de margedrempel te halen.

---

## 6. Als er iets misgaat

| Symptoom | Waar te kijken |
|----------|----------------|
| Leads komen niet binnen | `php artisan leads:poll-mailbox` handmatig draaien; let op de melding. Staat `MAILBOX_ENABLED` aan? |
| Er wordt niet gebeld | Draait de queue-worker en de scheduler? Staat het moment binnen een belvenster? Is `ELEVENLABS_ENABLED` aan en `AGENT_DRY_RUN` uit? |
| Webhook geeft 401 | Secret in ElevenLabs en in `ELEVENLABS_WEBHOOK_SECRET` moeten gelijk zijn; controleer ook de klok van de server. |
| Webhook geeft 404 | Het `conversation_id` hoort bij geen enkel gesprek in de database — meestal een testaanroep vanuit het ElevenLabs-dashboard. |
| Offerte niet verstuurd | `email_messages` bij de lead: status `failed` wijst op de mailserver, `skipped` op proefmodus. |
| Afspraak niet in de agenda | Het veld `sync_error` op de afspraak zegt waarom; de afspraak zelf is wel vastgelegd. |
| "Failed to fetch" bij het inloggen op het dashboard | De browser komt niet bij de API. De melding noemt het API-adres en de origin. Controleer welke origin de API teruggeeft: `curl -i http://localhost:8010/api/admin/leads \| grep -i access-control`. Staat daar een ander domein, dan leest de container een verkeerde `.env`; verwijder `apps/api/.env` en start de api-container opnieuw, dan wordt hij opnieuw uit `.env.docker` opgebouwd. |
| Lead blijft hangen | Dashboard → lead → "Stappen opnieuw aftrappen". Elke actie maakt een nieuwe poging; niets wordt overschreven. |
| Offerte gemarkeerd als "onder de margedrempel" | De verkoopprijs zit te dicht op de kostprijs. Kijk op Catalogus → Vanaf-prijs of de advertentieprijs nog klopt, of verhoog de inkoop-/margegegevens. |

Logs: `apps/api/storage/logs/`. Er worden bewust geen transcripten of
persoonsgegevens naar de logstream geschreven; die staan alleen in de database.
