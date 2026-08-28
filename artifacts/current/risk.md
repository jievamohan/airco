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
| De offerte is te laag door verkeerde aannames | **hoog** | verlies per klus | Zie hieronder: de prijsbasis is voorlopig. Elke offerte vermeldt de gemaakte aannames en het voorbehoud bij de schouw. |
| ElevenLabs of de agenda is onbereikbaar | midden | stilstand | Elke integratie vangt de fout af, legt hem vast op het gesprek of de afspraak, en zet de lead in de opvolgcadans in plaats van hem te laten hangen. |
| De queue-worker staat stil | midden | leads blijven liggen | `next_action_at` en `lead_sequence_runs.next_run_at` blijven staan; zodra de worker draait, wordt alles alsnog uitgevoerd. Wel monitoren. |
| Webhook komt dubbel binnen | midden | dubbele offerte | Een gesprek dat al `completed` is, wordt niet nogmaals verwerkt. Getest. |

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
