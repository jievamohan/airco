# De voice agent inrichten bij ElevenLabs

Dit document bevat het gespreksscript en de veldafspraken die de agent nodig
heeft. De prompt en de dataverzameling worden bij ElevenLabs beheerd, niet in
deze repository — maar ze moeten wel exact aansluiten op wat onze code
meestuurt en terugverwacht. Wijkt een veldnaam af, dan komt de informatie niet
in het CRM terecht.

**Eén agent bedient alle vier de gesprekstypen.** Welk gesprek het is, staat in
`{{gesprekstype}}`; de openingszin krijgt hij kant-en-klaar mee in
`{{gespreksopening}}`.

---

## 0. Klikpad

De volgorde waarin je het doet, met de schermnamen erbij. De secties hierna
bevatten de inhoud die je op elke plek nodig hebt.

**Vooraf:** een ElevenLabs-account met Agents, en een Twilio-account. Dat
laatste is geen voorkeur maar een eis: onze code roept
`/v1/convai/twilio/outbound-call` aan, dus het nummer moet via Twilio bij
ElevenLabs binnenkomen. Een SIP-trunknummer werkt niet.

### 1 — Twilio: nummer klaarzetten

Twee smaken, en voor ons voldoet de tweede:

| | Inkomend | Uitgaand |
|---|---|---|
| Nummer gekocht bij Twilio | ja | ja |
| Geverifieerde caller ID | nee | ja |

Wij bellen alleen uit. Zet je je bestaande bedrijfsnummer als geverifieerde
caller ID, dan belt de agent daar vandaan en komt een klant die terugbelt
gewoon bij jullie uit — vaak beter dan een los nummer waar niemand opneemt.

Je hebt straks een SID en een token nodig. Maak liever een API key aan (de SID
begint dan met `SK`) dan je account-brede gegevens te gebruiken.

### 2 — ElevenLabs: agent aanmaken

Dit kan met de hand, maar doe het niet: de prompt is zesduizend tekens en §4
telt achttien velden waarvan de namen exact moeten kloppen. Eén typefout in een
identifier en die informatie komt nooit in het CRM aan, zonder foutmelding.

Kies eerst een Nederlandse stem in de stemmenbibliotheek en noteer het
`voice_id`.

**Vanuit het dashboard** (geen shell nodig): vul onder **Instellingen → Voice
agent** de API-sleutel en het voice_id in, sla op, en klik op **Agent aanmaken
of bijwerken**. Het agent-id wordt daarna zelf ingevuld. Bestaat de agent al,
dan wordt hij bijgewerkt in plaats van dubbel aangemaakt.

**Of vanaf de server**, met dezelfde code:

```bash
cd apps/api
ELEVENLABS_API_KEY=<jouw-sleutel> php artisan voice:agent-sync --voice=<voice_id>
```

Het commando leest de prompt uit §2 en de velden uit §4 van dit document en
zet ze bij ElevenLabs neer. Het antwoordt met een `agent_…`-id; dat heb je in
stap 7 nodig.

Wil je eerst zien wat er verstuurd wordt, zonder iets aan te maken:

```bash
php artisan voice:agent-sync --voice=<voice_id> --dry-run
```

De sleutel staat bewust in de omgeving van dat ene commando en niet in `.env`:
hij is alleen nodig om de agent neer te zetten, en hoort daarna nergens meer
te staan behalve in het dashboard.

Draai je het later opnieuw na een wijziging in dit document, geef dan het
bestaande id mee — anders komt er een tweede agent naast te staan:

```bash
ELEVENLABS_API_KEY=… php artisan voice:agent-sync --voice=<voice_id> --agent=<agent_id>
```

Wat het commando **niet** doet: het nummer koppelen, de webhook aanzetten en de
sleutels invullen. Dat blijft stap 3, 5 en 7.

### 3 — ElevenLabs: nummer importeren

Tabblad **Phone Numbers** → nummer toevoegen, met vier velden: Label, Phone
Number, Twilio SID, Twilio Token. ElevenLabs bepaalt zelf of het nummer in- en
uitgaand kan of alleen uitgaand.

Open daarna het geïmporteerde nummer: het id begint met `phnum_`. **Dat** is
wat wij nodig hebben, niet het telefoonnummer.

### 4 — ElevenLabs: dataverzameling

Heeft het commando uit stap 2 al gedaan. Controleren kan in tabblad
**Analysis** → **Data collection**: daar horen de achttien velden uit §4 te
staan.

Vul je ze liever met de hand, dan is het per veld *Add item* met type,
identifier en beschrijving. De identifiers moeten exact overeenkomen; §7 legt
uit waarom.

### 5 — ElevenLabs: webhook

Deze staat **werkruimtebreed**, niet bij de agent: instellingenpagina van
ElevenAgents → post-call webhooks. Zie §5 voor de URL en het type. Kopieer het
secret dat er bij het aanmaken verschijnt — je ziet het maar één keer.

### 6 — ElevenLabs: API-sleutel

Profielmenu → **API Keys** → nieuwe sleutel. Beperk hem desnoods in scope.

### 7 — Ons dashboard

Vul de vijf velden in en zet proefmodus uit; dat staat uitgeschreven in §6.

### Welke code hoort waar

| Begint met | Wat het is | Waar je hem invult |
|---|---|---|
| `agent_…` | de agent uit stap 2 | ElevenLabs agent-id |
| `phnum_…` | het nummer uit stap 3 | Uitgaand telefoonnummer-id |
| `sk_…` | Twilio API key-SID | alleen bij ElevenLabs, stap 3 |

> Schermnamen zijn nagelopen in de documentatie van ElevenLabs. Wijkt de
> interface af, zoek dan op de begrippen uit de tabellen hierboven —
> *Phone Numbers*, *Data collection*, *post-call webhook* — want die zitten in
> de API en veranderen niet met een herontwerp mee.

---

## 1. Agent-instellingen

| Instelling | Waarde |
|-----------|--------|
| Taal | Nederlands |
| Stem | Een Nederlandse stem; kies een rustige, niet-uitbundige stem. Beluister hem eerst op een cijfer als "drieëntwintig honderd" — niet elke stem spreekt bedragen netjes uit |
| First message | `{{gespreksopening}}` |
| Max call duration | 8 minuten |
| Interruptions | Aan. Mensen praten door de agent heen; zonder dit klinkt hij bot |
| TTS-model | `eleven_flash_v2_5`. Geen voorkeur maar een eis: ElevenLabs weigert een niet-Engelse agent met *"Non-english Agents must use turbo or flash v2_5"*. Van de twee toegestane modellen heeft flash de laagste vertraging, en aan de telefoon hoor je elke wachttijd |

Vul daarna het systeemprompt uit §2 en de dataverzameling uit §4 in.

### Uitgaand telefoonnummer

Zonder nummer kan de agent alleen opgebeld worden, niet zelf bellen — en dit
systeem belt uit. Koppel bij ElevenLabs onder **Phone Numbers** een nummer
(eigen Twilio-account of een nummer van ElevenLabs zelf).

Wat wij nodig hebben is niet het nummer maar het **id** ervan: onze code stuurt
`agent_phone_number_id` mee bij elke oproep. Je vindt het in de URL of in de
detailweergave van dat nummer, en het begint met `phnum_`.

Gebruik een nummer dat de klant kan terugbellen. Belt hij terug op een nummer
dat nergens uitkomt, dan is dat een lead die je zelf hebt weggegooid.

---

## 2. Systeemprompt

Plak dit als geheel in het promptveld van de agent.

```text
# Rol

Je bent de telefonische assistent van {{bedrijfsnaam}}, een installateur van
airconditioning in Nederland. Je belt mensen die zelf een offerte hebben
aangevraagd. Je bent geen verkoper die iets aansmeert: de klant heeft om dit
contact gevraagd en jij maakt het af.

# Wie je bent

Je bent een digitale assistent. Vraagt iemand of je een computer of een robot
bent, dan bevestig je dat meteen en zonder omhaal: "Klopt, ik ben een digitale
assistent van {{bedrijfsnaam}}." Doe nooit alsof je een mens bent. Vraagt
iemand om een medewerker van vlees en bloed, dan zeg je dat je dat laat
terugbellen en rond je het gesprek af.

# Toon

- Spreek Nederlands, met u.
- Korte zinnen. Dit wordt uitgesproken, niet gelezen.
- Eén vraag tegelijk. Wacht op antwoord.
- Geen vakjargon. Zeg "de buitenunit" en niet "de condensor".
- Geen overdreven enthousiasme, geen uitroepen, geen "geweldig!".
- Bedragen spreek je uit als hele euro's: "vierentwintighonderd euro", niet
  "2400,20". Cijfers achter de komma laat je weg.

# Wat je van deze klant weet

Naam: {{klant_naam}} (spreek aan met {{klant_voornaam}} alleen als de klant
zichzelf zo introduceert; gebruik anders de achternaam met "meneer" of
"mevrouw", of vermijd de naam)
Adres: {{klant_adres}}
E-mailadres: {{klant_email}}
Ruimte: {{ruimte_omschrijving}}, {{aantal_ruimtes}} ruimte(s)
Advies tot nu toe: {{geadviseerd_systeem}} van {{geadviseerd_vermogen}}
Gewenste startdatum: {{gewenste_startdatum}}
Opmerking bij de aanvraag: {{opmerkingen_klant}}
Dit is belpoging {{belpoging}}.

Gegevens die nog ontbreken: {{ontbrekende_gegevens}}

# Dit gesprek

Type: {{gesprekstype}}
Doel: {{gespreksdoel}}

Volg hieronder het blok dat bij {{gesprekstype}} hoort.

## Als gesprekstype = qualification

1. Bevestig kort waar de aanvraag over ging, zodat de klant weet dat je de
   juiste persoon bent.
2. Vraag de ontbrekende gegevens uit. Sla over wat je al weet. Vraag alleen
   wat je nodig hebt, in deze volgorde, en stop zodra je het hebt:
   - Om hoeveel ruimtes gaat het, en hoe groot is de ruimte ongeveer?
   - Op welke verdieping komt de binnenunit?
   - Waar kan de buitenunit komen: aan de gevel, op het platte dak, of op de
     grond in de tuin?
   - Hoeveel meter zit er ongeveer tussen de binnen- en de buitenunit? Een
     schatting is genoeg.
   - Uit welk bouwjaar is de woning ongeveer, of hoe goed is hij geïsoleerd?
   - Wanneer zou u de installatie willen?
3. Vraag of er een plek is waar het condenswater weg kan, bijvoorbeeld een
   afvoer of een buitenmuur. Weet de klant het niet, ga er dan niet op door;
   dat bekijkt de monteur.
4. Bevestig het e-mailadres letterlijk: "Ik stuur de offerte naar
   {{klant_email}}, klopt dat?"
5. Zeg dat de offerte binnen enkele minuten in de mail staat, en dat je over
   ongeveer een uur nog even belt om hem door te nemen. Vraag of dat schikt.
6. Sluit vriendelijk af.

Noem in dit gesprek geen totaalprijs. Vraagt de klant er toch naar, zeg dan:
"Onze prijzen beginnen bij {{vanaf_prijs}}. {{vanaf_prijs_dekking}} Wat het
voor uw situatie wordt, staat zo meteen in de offerte."

## Als gesprekstype = conversion

De klant heeft offerte {{offerte_nummer}} ontvangen: {{offerte_bedrag}}
inclusief btw, montage ongeveer {{montageduur}}, geldig tot
{{offerte_geldig_tot}}.

1. Vraag of de klant de offerte heeft kunnen bekijken.
2. Zo niet: vat hem in twee zinnen samen — welk systeem, welk bedrag, hoe lang
   de montage duurt. Bied aan om later terug te bellen als dat beter uitkomt.
3. Zo ja: vraag of er nog vragen zijn en beantwoord ze. Ga niet uit jezelf
   verkopen; laat de klant sturen.
4. Ga bij aarzeling op zoek naar de échte reden. Veelvoorkomend:
   - Te duur → leg uit wat erin zit: apparaat, materiaal, montage,
     inbedrijfstelling en de f-gassenregistratie. Er zijn geen kosten achteraf.
     Je mag {{korting_bij_direct_akkoord}} korting aanbieden bij akkoord in dit
     gesprek. Bied die één keer aan, niet twee keer.
   - Wil overleggen → prima. Vraag wanneer je mag terugbellen en rond af.
   - Vergelijkt met een andere offerte → vraag wat daarin anders is. Praat
     nooit negatief over een concurrent.
5. Bij akkoord: prik een datum. Stel twee momenten voor, minstens twee dagen
   vooruit, op een werkdag. De monteurs beginnen om acht uur 's ochtends of
   rond het begin van de middag. Bevestig de gekozen datum en tijd hardop.
6. Zeg dat de bevestiging met de afspraak per mail komt.

Verzin nooit een ander bedrag, een andere korting of een andere levertijd dan
hierboven staat. Weet je iets niet, zeg dan dat een collega erop terugkomt.

## Als gesprekstype = chase

Kort houden. De klant heeft eerdere pogingen niet beantwoord.

1. Zeg waarvoor je belt en vraag of het uitkomt.
2. Komt het niet uit: vraag wanneer wel, bevestig dat moment en hang op.
3. Komt het wel uit: ga verder volgens het qualification-blok.

## Als gesprekstype = final

Dit is de laatste poging.

1. Zeg dat je een laatste keer belt over de aanvraag.
2. Vraag of er nog interesse is.
3. Zo nee: bedank, zeg dat je verder niet meer belt, en sluit af.
4. Zo ja: ga verder volgens het qualification-blok.

# Regels die altijd gelden

- Zegt de klant dat hij niet gebeld wil worden, op welke manier dan ook, dan
  bevestig je dat direct, verontschuldig je je kort en beëindig je het gesprek.
  Blijf niet doorvragen.
- Krijg je een voicemail, spreek dan kort in: wie je bent, waarvoor je belt,
  en dat je het later nog eens probeert. Laat geen bedragen achter.
- Spreek je iemand anders dan {{klant_naam}}, vraag dan of je hem of haar kunt
  spreken. Kan dat niet, vraag dan wanneer wel en hang op. Bespreek de aanvraag
  niet met iemand anders.
- Wil iemand ons zelf bellen of een mens spreken, geef dan het nummer:
  {{bedrijf_telefoon}}.
- Vraagt een zakelijke klant naar het bedrag zonder btw, dan is dat
  {{offerte_bedrag_excl_btw}}.
- Beloof nooit iets over de prijs, de levertijd of de garantie wat niet
  hierboven staat.
- Wordt het gesprek onaangenaam, blijf beleefd en rond af.
- Vat aan het einde van elk gesprek in één zin samen wat er is afgesproken.
```

---

## 3. Wat wij meesturen

Deze variabelen vult onze code per gesprek in. De namen moeten letterlijk
overeenkomen; ze staan in `apps/api/app/Services/Voice/CallVariables.php`.

| Variabele | Inhoud |
|-----------|--------|
| `gespreksopening` | De eerste zin, inclusief de melding dat het een digitale assistent is en dat er wordt opgenomen |
| `gesprekstype` | `qualification`, `conversion`, `chase` of `final` |
| `gespreksdoel` | Het doel van dit gesprek in één zin |
| `bedrijfsnaam`, `bedrijf_telefoon` | Bedrijfsgegevens |
| `klant_naam`, `klant_voornaam`, `klant_adres`, `klant_email` | Contactgegevens |
| `ruimte_omschrijving`, `aantal_ruimtes` | Wat de klant heeft opgegeven |
| `geadviseerd_systeem`, `geadviseerd_vermogen` | Ons advies op dit moment |
| `gewenste_startdatum`, `opmerkingen_klant` | Uit de aanvraag |
| `ontbrekende_gegevens` | Wat nog uitgevraagd moet worden, of `geen` |
| `belpoging` | Het hoeveelste belpoging dit is |
| `vanaf_prijs`, `vanaf_prijs_dekking` | De geadverteerde vanaf-prijs en wat die dekt |
| `offerte_nummer`, `offerte_bedrag`, `offerte_bedrag_excl_btw`, `offerte_geldig_tot`, `montageduur`, `korting_bij_direct_akkoord` | Alleen bij een conversiegesprek |

Ontbreekt een waarde, dan sturen wij `onbekend`. De agent moet daar niet over
struikelen — vandaar de instructie om alleen te vragen wat nodig is.

---

## 4. Dataverzameling

Richt onder **Analysis → Data collection** deze velden in. De namen zijn de
sleutels die onze webhook uitleest (`ElevenLabsWebhookController` en
`LeadWorkflow::applyCollected`). Een veld dat de agent niet heeft achterhaald,
laat hij leeg; wij nemen alleen ingevulde waarden over.

### Uitkomst van het gesprek

| Veld | Type | Beschrijving voor de agent |
|------|------|----------------------------|
| `outcome` | string | Hoe het gesprek is geëindigd. Precies één van: `answered` (gesproken, geen afspraak), `appointment_booked` (datum afgesproken), `callback_requested` (klant wil later teruggebeld), `declined` (klant ziet ervan af), `do_not_contact` (klant wil niet meer benaderd worden) |
| `appointment_agreed` | boolean | True als er een concrete installatiedatum is afgesproken |
| `appointment_start` | string | De afgesproken datum en tijd als `JJJJ-MM-DD UU:MM`, in Nederlandse tijd. Bijvoorbeeld `2026-09-15 08:00` |

`outcome` is het belangrijkste veld: daarop bepaalt de workflow of er een
offerte uitgaat, een afspraak wordt geboekt of de opvolging start. Laat de agent
dit altijd invullen.

### Technische gegevens over de klus

| Veld | Type | Toegestane waarden |
|------|------|--------------------|
| `rooms_count` | number | Aantal ruimtes dat gekoeld moet worden |
| `space_size` | number | Grootte van de ruimte, als getal |
| `space_unit` | string | `m2` of `m3` — welke eenheid bij `space_size` hoort |
| `building_year` | number | Bouwjaar van de woning, vier cijfers |
| `insulation` | string | `good` bij goed geïsoleerd of nieuwbouw, `average` bij standaard, `poor` bij tochtig, oud of een zolder |
| `floor_level` | number | Verdieping van de binnenunit; begane grond is `0` |
| `wall_type` | string | Bijvoorbeeld `spouwmuur`, `steens`, `houtskeletbouw` |
| `outdoor_unit_placement` | string | Bijvoorbeeld `achtergevel`, `plat dak`, `tuin` |
| `pipe_length_m` | number | Geschatte afstand tussen binnen- en buitenunit in meters |
| `needs_condensate_pump` | boolean | True als er geen natuurlijke afvoer voor condenswater is |
| `needs_extra_group` | boolean | True als er een extra groep in de meterkast bij moet |
| `desired_start` | string | Gewenste startdatum als `JJJJ-MM-DD` |
| `email` | string | Alleen invullen als de klant een ander e-mailadres doorgeeft dan wij hadden |
| `tier` | string | `budget`, `mid` of `premium`, als de klant een voorkeur voor prijsklasse uitspreekt |
| `notes` | string | Wat de monteur moet weten en nergens anders past. Kort houden |

Let op de vaste waarden bij `insulation` en `tier`: de agent hoort "goed
geïsoleerd" maar moet `good` terugsturen. Zet dat in de veldbeschrijving bij
ElevenLabs, anders komt er vrije tekst binnen die wij negeren.

---

## 5. Webhook

Zet de post-call webhook op:

```
POST https://airco.sinoxi.nl/api/webhooks/elevenlabs/post-call
```

Het secret dat ElevenLabs toont, vul je in bij **Instellingen → Voice agent →
Webhook-secret** in het dashboard. Onze kant weigert elk verzoek zonder geldige
handtekening en elk verzoek ouder dan dertig minuten.

---

## 6. Voordat je hem op echte klanten loslaat

1. Vul in het dashboard onder **Instellingen → Voice agent** deze vijf in:

   | Veld | Waar het vandaan komt |
   |---|---|
   | Voice agent actief | aanzetten |
   | ElevenLabs API-sleutel | ElevenLabs → profiel → API key |
   | ElevenLabs agent-id | de agent uit §1, begint met `agent_` |
   | Uitgaand telefoonnummer-id | het `phnum_`-id uit §1 |
   | Webhook-secret | het secret dat ElevenLabs bij de webhook toont (§5) |

   Zet daarna onder **Instellingen → Werking** de **proefmodus uit**. Dit is de
   valkuil: alles goed invullen en die schakelaar vergeten geeft geen
   foutmelding, maar wel een nepclient die niets belt. De code valt terug op
   die nepclient zodra proefmodus aan staat *of* de voice agent uit — beide
   moeten dus goed staan.

   > Een instelling in het dashboard overschrijft `.env`. Staat het veld leeg,
   > dan beslist `.env`, en pas daarna de standaard uit `config/agent.php`.
   > Iets in `.env` zetten terwijl er een dashboardwaarde staat, doet dus niets.

2. Maak in het dashboard handmatig een lead aan met **je eigen
   telefoonnummer**, en trap "Kwalificatiegesprek inplannen" af.

   Let op het tijdstip. Er wordt alleen gebeld binnen de belvensters uit
   `config/agent.php`: maandag t/m vrijdag 09:00–20:00, zaterdag 10:00–17:00,
   zondag niet. Daarbuiten gebeurt er niets, zonder melding — een test op een
   late avond lijkt daardoor op een defect.
3. Neem op. Loop het gesprek af zoals een klant zou doen, en wijk een keer
   bewust af: onderbreek hem, stel een vraag die niet in het script staat, zeg
   dat je niet meer gebeld wil worden.
4. Kijk daarna in het CRM: staat het transcript erbij, zijn de velden
   overgenomen, klopt de uitkomst, is de offerte verstuurd?
5. Herhaal voor het conversiegesprek.

Pas als dat rondloopt, laat je echte leads erdoorheen. Zet het aantal
belpogingen desnoods eerst op één, zodat een fout niet vier keer bij dezelfde
klant terechtkomt.

---

## 7. Dit document wordt getest

`tests/Feature/VoiceAgentPromptTest.php` leest dit bestand en controleert drie
dingen: dat elke `{{variabele}}` in het prompt ook echt door onze code wordt
meegestuurd, dat elk veld uit §4 daadwerkelijk op de lead wordt overgenomen, en
dat elke uitkomst uit §4 als uitkomst bestaat.

Verzin je hier een veldnaam bij, of hernoem je er een, dan valt CI om. Dat is de
bedoeling: een veld dat alleen in de documentatie bestaat, levert een gesprek op
waarvan de helft nergens terechtkomt.

---

## 8. Wat hier bewust niet in staat

Het script belooft nergens een prijs, levertijd of garantie die niet uit onze
eigen gegevens komt. Dat is geen stijlkeuze: een voice agent die zelf een bedrag
verzint, verkoopt iets wat jullie moeten waarmaken. Alles wat over geld gaat,
komt uit de variabelen die wij meesturen.

Blijf de eerste weken transcripten teruglezen in het CRM. Daar zie je waar het
script tekortschiet — dat is sneller gevonden dan uitgedacht.
