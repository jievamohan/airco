# Risico's — lead-to-appointment agent

## Hoog-risicogebieden in deze wijziging

| Gebied | Raakt dit | Toelichting |
|--------|-----------|-------------|
| **Auth** | **Ja** | Nieuwe dashboard-authenticatie met Sanctum-bearertokens. Zie `security.md`; inloggen is rate-limited op twee niveaus, tokens worden bij een nieuwe login vervangen. |
| **Crypto** | **Ja** | HMAC-SHA256-verificatie van de post-call webhook, constant-time vergeleken, met tijdvenster tegen replay. Geen eigen crypto-constructies. |
| **Betalingen** | Nee | Er wordt niets afgerekend; offertes zijn informatief. |
| **Permissies** | Beperkt | Eén rol-veld (`owner` / `operator`) op `users`, nog zonder verschil in rechten. Elke ingelogde gebruiker kan alles in het dashboard. |
| **Deps** | Ja | Zie `dependency-review.md`. |
| **Infra/CI** | Ja | Zie `infra-review.md`. |
| **Migraties** | Ja | Zie `db-review.md`. |

## Operationele risico's

| Risico | Kans | Effect | Wat we hebben gedaan |
|--------|------|--------|----------------------|
| De agent belt een klant op een ongewenst moment | midden | reputatieschade | Belvensters per weekdag; buiten het venster schuift de poging automatisch door. Zondag staat uit. |
| De agent blijft nabellen | laag | irritatie, AVG-klacht | Maximaal aantal pogingen (standaard 4); daarna status `unreachable` en geen acties meer. `do_not_contact` stopt alles onmiddellijk. |
| Een verkeerd geparste mail levert een onzinlead | midden | verspilde belpoging | Een bericht zonder naam én zonder e-mail of telefoon wordt overgeslagen, niet aangemaakt. Overgeslagen berichten worden gemeld in de commando-uitvoer. |
| Dubbele aanvraag levert twee belrondes | midden | irritatie | Ontdubbeling op e-mail plus telefoon binnen 30 dagen; de bestaande lead wordt aangevuld. |
| De offerte is te laag door verkeerde aannames | **hoog** | verlies per klus | Zie hieronder: de prijsbasis is voorlopig. Elke offerte vermeldt de gemaakte aannames en het voorbehoud bij de schouw, en legt kostprijs en marge vast met een waarschuwing onder de drempel. |
| ElevenLabs of de agenda is onbereikbaar | midden | stilstand | Elke integratie vangt de fout af, legt hem vast op het gesprek of de afspraak, en zet de lead in de opvolgcadans in plaats van hem te laten hangen. |
| De queue-worker staat stil | midden | leads blijven liggen | `next_action_at` en `lead_sequence_runs.next_run_at` blijven staan; zodra de worker draait, wordt alles alsnog uitgevoerd. Wel monitoren. |
| Webhook komt dubbel binnen | midden | dubbele offerte | Een gesprek dat al `completed` is, wordt niet nogmaals verwerkt. Getest. |

## De geadverteerde vanaf-prijs van € 899

KlimaatX adverteert met "vanaf € 899". Op de huidige basis is dat **niet
haalbaar voor een geïnstalleerd systeem**: de goedkoopst mogelijke klus kost
€ 902 excl. btw en levert bij € 899 incl. btw een verlies van € 159,02 per klus,
nog vóór overhead. De laagste installatieprijzen in de markt liggen op
€ 1.295–1.300 inclusief montage. Volledige uitwerking in
`docs/research/pricing-baseline.md` §8.

Hoe het systeem hiermee omgaat:

* De vanaf-prijs is een instelbare **ondergrens** op elke offerte; standaard
  € 899 incl. btw. Bij een geïnstalleerde klus bindt die grens niet, want de
  calculatie komt daar sowieso boven uit.
* Het **instappakket** — dat een eenvoudige instapklus daadwerkelijk aftopt op
  de vanaf-prijs — staat **standaard uit**. Aanzetten is een bewuste keuze voor
  een lokkertje, geen ongeluk.
* Elke offerte legt kostprijs en brutomarge vast. Zakt die onder de
  margedrempel (standaard 15%), dan wordt de offerte gemarkeerd, komt er een
  regel in de tijdlijn en staat de waarschuwing in de mail naar de ondernemer.
  Verlies gaat dus nooit stilletjes.
* **Catalogus → Vanaf-prijs** rekent continu door of de advertentie nog klopt en
  wat de prijs zou moeten zijn om de drempel te halen. Verandert de inkoop, dan
  verandert het antwoord mee.

Zolang het instappakket uitstaat, is de laagste geoffreerde prijs € 1.459,50
incl. btw. Dat wijkt af van de advertentie; dat verschil is een marketingkeuze
die de ondernemer moet maken, niet iets wat de code kan oplossen.

## Het grootste risico: de prijsbasis

De catalogus is gevuld met cijfers die zijn **afgeleid uit openbaar
marktonderzoek**, niet uit de eigen inkoop van KlimaatX. De methode en alle
bronnen staan in `docs/research/pricing-baseline.md`. De uitkomsten vallen binnen
de waargenomen marktrange (een 3,5 kW single split komt uit op ± € 2.400 incl.
btw, waar de markt € 1.900–2.800 laat zien), maar dat is geen garantie dat er
marge op zit bij déze inkoopprijzen.

Zolang dat niet is rechtgezet:

* Draai de agent in **proefmodus** (`AGENT_DRY_RUN=true`) of laat de
  conversiegesprekken uit tot de prijzen kloppen.
* Elke offerte vermeldt expliciet de gemaakte aannames en dat de prijs bij de
  schouw definitief wordt.
* De ondernemer vervangt de cijfers via Dashboard → Catalogus; de seeder
  overschrijft aangepaste regels nooit.

## Bewust buiten scope

WhatsApp en sms als kanaal (het `channel`-veld is voorbereid, niet
geïmplementeerd), rolgebaseerde rechten binnen het dashboard, en het uitrollen
van de API op de productie-VPS.
