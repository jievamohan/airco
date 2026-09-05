# Prijs- en montagetijdbasis (airco-installatie NL)

**Status per 5 september 2026: apparatuur is ECHT, de rest is nog VOORLOPIG.**

| Onderdeel | Herkomst |
|---|---|
| Apparatuur (sets, buiten- en binnenunits, warmtepompen, toebehoren) | Netto inkoopprijzen uit de prijslijsten van Airco Techniek B.V. — MHI 2026 en KAISAI 2026-6, beide versie 0526, ontvangen 3 september 2026 |
| Materiaal en toeslagen | Nog afgeleid uit marktonderzoek (dit document, §5) |
| Normtijden, uurtarief, marges | Nog afgeleid uit marktonderzoek (dit document, §6 en §7) |

De catalogus houdt dat per regel bij in `price_source`, en **Dashboard → Catalogus**
laat het zien: *Prijslijst*, *Eigen invoer* of *Voorlopig*. Wat hieronder nog als
voorlopig staat, is dus precies wat er nog vervangen moet worden.

Hoe een nieuwe prijslijst erin komt, staat in
[docs/runbooks/prijslijsten.md](../runbooks/prijslijsten.md).

Alle bedragen blijven aanpasbaar via **Dashboard → Catalogus** en
**Dashboard → Instellingen → Prijsstelling**; een import laat een regel die daar
is aangepast met rust.

Alle bedragen in dit document zijn **exclusief btw** tenzij anders vermeld.

---

## 1. Gebruikte bronnen

| # | Bron | Wat is eruit gehaald |
|---|------|----------------------|
| 1 | technim.nl (installatiegroothandel) | Single split vanaf ± €400 inkoop; A-merken Daikin/Fujitsu/Haier/LG |
| 2 | installatieplatform.nl | Mitsubishi MSZ-HR €1.500–1.900, MSZ-AP €1.700–2.300, MSZ-EF €1.900–2.500, MSZ-LN €2.200–2.800 (incl. btw én montage); multisplit 2 units €3.000–4.500, 3–4 units €4.000–6.000 |
| 3 | aircoprijzen.nl | Montageloon split €690, multisplit €825, extra binnenunit €600–810; meerprijs wifi €90–190 (incl. btw) |
| 4 | cvkoopjes.nl / airco-webwinkel.nl | Koelleidingsets: 1/4"x3/8" 3 m €39, 5 m €59–64, 10 m €117–119, 15 m €162–179; 1/4"x1/2" 5 m €89 (incl. btw) |
| 5 | werkspot.nl / klussendirect.nl | Meerprijs leiding ± €15/m; condenspomp ± €200; standaard 3–5 m leiding inbegrepen |
| 6 | offerteadviseur.nl / mijnzzp.nl / knab.nl | Uurtarief airco-monteur zzp €50–85, gemiddeld €65 excl. btw |
| 7 | profijtairco.nl / homedeal.nl | Montageduur single split 3–8 uur, multisplit 5–8 uur, grote multisplit soms meerdere dagen |
| 8 | noordpool-airconditioning.nl / degoedkoopsteaircoshop.nl / slimster.nl | Vuistregel koellast 30/40/50 W per m³ (goed/gemiddeld/slecht geïsoleerd), ± 75/100/125 W per m² bij 2,5 m plafond; kamertype→kW tabel |
| 9 | aircodoc.nl / dsg-aircotechniek.nl / haanservice.nl | Laagst geadverteerde installatieprijzen: € 1.295 en € 1.300 inclusief montage; actieprijs € 1.199 (LG 2,5–3,5 kW met montage) — gebruikt voor de toets in §8 |

---

## 2. Afleidingsmethode

Webshopprijzen zijn consumentenprijzen **inclusief** 21% btw. De inkoopprijs voor een
installateur is daaruit benaderd met:

```
inkoop_ex_btw ≈ (consumentenprijs_incl_btw / 1,21) × 0,80
```

De factor 0,80 modelleert de gebruikelijke installateurskorting bij een groothandel
(bron 1: "vanaf €400" tegenover consumentenprijzen van €560+ voor dezelfde klasse).
De uitkomsten zijn afgerond op logische inkoopstaffels.

Controle achteraf: de gegenereerde offerte voor een 3,5 kW single split premium komt uit
op ± €2.400 incl. btw en voor budget op ± €1.650 incl. btw. Beide vallen binnen de in
bronnen 2 en 3 waargenomen marktrange van €1.600–2.800.

---

## 3. Capaciteitsbepaling (sizing)

Koellast = inhoud (m³) × isolatiefactor.

| Isolatie | W/m³ | W/m² bij 2,5 m |
|----------|------|-----------------|
| goed (nieuwbouw / na 2010) | 30 | 75 |
| gemiddeld (standaard) | 40 | 100 |
| slecht (voor 1990 / zolder) | 50 | 125 |

Wordt de ruimte in m² opgegeven, dan rekenen we met een plafondhoogte van 2,60 m.
De uitkomst wordt opgehoogd naar de eerstvolgende standaardklasse:
**2,0 / 2,5 / 3,5 / 5,0 / 7,1 kW**. Boven 7,1 kW of bij meer dan één ruimte
adviseert de engine een multisplit.

---

## 4. Apparatuur — inkoopprijs per klasse (excl. btw)

> **Vervallen per 5 september 2026.** De tabellen in deze paragraaf zijn vervangen
> door de netto inkoopprijzen uit de leveranciersprijslijsten. Ze staan hier nog
> omdat ze de onderbouwing zijn van de vanaf-prijstoets in §8 en van het bedrag
> waar de proof of concept mee gerekend heeft.
>
> **Wat er nu gevoerd wordt** (`config/agent.php`, `pricing.series`):
>
> | Klasse | Single split set | Binnenunit multisplit | Buitenunit multisplit |
> |---|---|---|---|
> | budget | KAISAI EVO | KAISAI FLY+ | KAISAI multisplit (M-line) |
> | mid | KAISAI ICE WHITE | KAISAI ICE WHITE | KAISAI multisplit (M-line) |
> | premium | Mitsubishi SRK-ZS-WF | Mitsubishi SRK-ZS-WF | Mitsubishi SCM |
>
> Buiten- en binnenunit van een multisplit moeten van hetzelfde merk zijn; daarom
> delen budget en mid dezelfde KAISAI-buitenunit. De overige lijnen uit de
> prijslijsten (ICE BLACK, PRO-HEAT+, GEO+, ART, ZSX, Titanium, cassettes,
> plafondonderbouw, slim duct, warmtepompen) staan wél in de catalogus, maar
> worden niet vanzelf gekozen — die zijn er om met de hand op te offreren.
>
> **Normtijden.** Elke ingelezen regel krijgt er een: 330 minuten voor een
> single split set, 180 voor een losse binnenunit, 390 voor een
> multisplit-buitenunit — dezelfde waarden als de proof of concept, dus de
> offerteprijs is hierdoor niet verschoven. Voor inbouwwerk gelden hogere
> normen (cassette 480, kanaalunit 540, plafondonderbouw 450), en voor
> warmtepompen 960 tot 2400 minuten. Die zijn afgeleid, niet nagecalculeerd:
> zie §6, en pas ze aan zodra er eigen cijfers zijn.
>
> **Wat opvalt aan de echte prijzen.** Ze liggen fors lager dan dit
> marktonderzoek aannam: een 3,5 kW-set kost netto € 210 (KAISAI EVO) tot € 678
> (Mitsubishi SRK35ZS-WF), waar de afleiding hieronder op € 450–900 uitkwam. Met
> de standaardmarge van 45% landt een 3,5 kW single split inclusief montage
> daardoor op ± € 1.376 incl. btw, ónder de marktrange van € 1.600–2.800 uit
> bron 2. De rekenkant klopt; het is de opslag die ruimte heeft. De knop daarvoor
> is `pricing.equipment_margin_pct` (of per regel in het dashboard) — dat is een
> ondernemersbeslissing, geen technische.

Drie kwaliteitstiers zoals het marktonderzoek ze indeelde:

* **budget** — Gree, Haier, Lamborghini
* **mid** — LG, Fujitsu, Toshiba
* **premium** — Daikin, Mitsubishi Electric

### Single split (buiten- + binnenunit als set)

| kW | budget | mid | premium |
|----|--------|-----|---------|
| 2,0 | € 360 | € 520 | € 730 |
| 2,5 | € 380 | € 560 | € 780 |
| 3,5 | € 450 | € 650 | € 900 |
| 5,0 | € 620 | € 850 | € 1.150 |
| 7,1 | € 850 | € 1.150 | € 1.500 |

### Multisplit buitenunits

| Aansluitingen | budget | mid | premium |
|---------------|--------|-----|---------|
| 2 | € 700 | € 900 | € 1.100 |
| 3 | € 950 | € 1.250 | € 1.550 |
| 4 | € 1.250 | € 1.600 | € 2.000 |
| 5 | € 1.600 | € 2.050 | € 2.550 |

### Losse binnenunits (multisplit)

| kW | budget | mid | premium |
|----|--------|-----|---------|
| 2,0 | € 210 | € 280 | € 340 |
| 2,5 | € 220 | € 300 | € 360 |
| 3,5 | € 260 | € 350 | € 430 |
| 5,0 | € 340 | € 460 | € 570 |

Standaardmarge apparatuur: **45%**.

---

## 5. Materialen — inkoopprijs (excl. btw)

| SKU | Omschrijving | Eenheid | Inkoop | Marge | Montagetijd |
|-----|--------------|---------|--------|-------|-------------|
| MAT-LEIDING-5M | Koelleidingset 1/4"x3/8" 5 m, geïsoleerd | set | € 42 | 60% | 0 min (in basis) |
| MAT-LEIDING-EXTRA | Extra koelleiding boven 5 m | meter | € 9 | 60% | 9 min |
| MAT-BEUGEL | Trillingsvrije wandbeugel buitenunit | stuk | € 35 | 60% | 0 min (in basis) |
| MAT-CONDENS | Condensafvoer + slang | set | € 18 | 60% | 0 min (in basis) |
| MAT-CONDENSPOMP | Condenspomp (bij ontbreken natuurlijk afschot) | stuk | € 110 | 60% | 45 min |
| MAT-KABELGOOT | Leidinggoot + elektrakabel | meter | € 7 | 60% | 6 min |
| MAT-KERNBORING | Kernboring gevel Ø 65 mm | stuk | € 12 | 60% | 30 min |
| MAT-KLEIN | Klein materiaal, stikstof, vacuüm, bevestiging | post | € 30 | 60% | 0 min (in basis) |
| MAT-FGAS | F-gassenregistratie en afvoerbijdrage | post | € 15 | 0% | 0 min |
| MAT-GROEP | Extra elektragroep in meterkast | stuk | € 95 | 60% | 120 min |
| MAT-DAKDOORVOER | Dakdoorvoer waterdicht afgewerkt | stuk | € 65 | 60% | 60 min |

---

## 6. Arbeid en normtijden

* Verkooptarief arbeid: **€ 75 per monteursuur excl. btw** (inkoop/kostprijs € 65, bron 6).
* Ploeggrootte standaard **2 monteurs**.
* Doorlooptijd op locatie = monteursuren ÷ ploeggrootte, afgerond op halve uren, minimaal 2,0 uur.

| Normtijd | Monteursuren |
|----------|--------------|
| Basis single split (incl. 5 m leiding, beugel, condens, 1 kernboring) | 6,0 |
| — waarvan op de apparatuurregel (de kernboring is een eigen regel) | 5,5 |
| Basis multisplit buitenunit (incl. 1 kernboring) | 7,0 |
| Per extra binnenunit (incl. eigen kernboring) | 3,5 |
| Per extra meter leiding boven 5 m | 0,15 |
| Kernboring (per stuk, ook de eerste) | 0,5 |
| Condenspomp | 0,75 |
| Plaatsing op verdieping ≥ 2 of gevelwerk op hoogte | 1,5 |
| Extra elektragroep | 2,0 |
| Dakdoorvoer | 1,0 |

De normtijden worden per offerteregel opgeteld; de kernboring staat als eigen
regel in de calculatie, zodat een tweede doorvoer vanzelf meetelt.

Voorbeeld: single split 3,5 kW, 8 m leiding, 1 kernboring →
5,5 + 0,5 + 3×(0,15+0,10) = 6,75 monteursuren → ± 3,5 uur op locatie met 2 monteurs.
Voor het inplannen van de afspraak wordt daar 30 minuten reis- en opruimtijd bij geteld.

---

## 7. Overige instelbare parameters

| Parameter | Startwaarde | Toelichting |
|-----------|-------------|-------------|
| Btw-tarief | 21% | Aanpasbaar; als voor een deel van het werk een verlaagd tarief geldt, in het dashboard wijzigen |
| Voorrijkosten | € 0 tot 30 km, daarna € 0,55/km | Vanaf vestigingspostcode |
| Geadverteerde vanaf-prijs | € 899 incl. btw | Ondergrens voor elke offerte; zie §9 |
| Instappakket op de vanaf-prijs | uit | Aan betekent: een eenvoudige instapklus wordt afgetopt op de vanaf-prijs, ook onder de kostprijs |
| Margedrempel | 15% | Daaronder wordt een offerte gemarkeerd en de ondernemer gewaarschuwd |
| Kostprijs arbeid | € 65 per monteursuur excl. btw | Nodig om de marge te bepalen; verkoop staat op € 75 |
| Geldigheid offerte | 21 dagen | |
| Standaard tier | mid | Voorgesteld tenzij de lead anders aangeeft |
| Korting bij direct akkoord tijdens conversiegesprek | 3%, max € 150 | Argument voor de voice agent |

---

## 8. De geadverteerde vanaf-prijs van € 899

KlimaatX adverteert met "vanaf € 899". Op de hier vastgelegde basis is dat
**niet haalbaar voor een geïnstalleerd systeem**. De rekensom voor de
goedkoopst mogelijke klus (2,0 kW voordelig, 5 m leiding, begane grond, geen
extra voorzieningen):

| Post | Bedrag excl. btw |
|------|------------------|
| Apparaat, inkoop | € 360,00 |
| Materiaal, inkoop | € 152,00 |
| Arbeid, 6,0 monteursuren × € 65 | € 390,00 |
| **Kostprijs** | **€ 902,00** |
| Verkoop bij € 899 incl. btw | € 742,98 |
| **Resultaat per klus** | **− € 159,02** |

Ter vergelijking: de laagste installatieprijzen die in augustus 2026 in de
Nederlandse markt geadverteerd werden, liggen op € 1.295 tot € 1.300 inclusief
montage (aircodoc.nl, dsg-aircotechniek.nl); de goedkoopste actieprijs die we
vonden was € 1.199 (haanservice.nl). Niemand adverteert een geïnstalleerd
systeem vanaf € 899.

Wat wél kan:

| Invulling | Uitkomst |
|-----------|----------|
| € 899 voor **het apparaat**, montage apart | Ruim haalbaar: op een inkoop van € 360 is dat 51% marge. Marktconform: losse units van 3,5 kW gaan online voor € 450 tot € 909. |
| € 899 **inclusief montage** als lokkertje | € 159 verlies per klus, plus overhead. Alleen zinnig als er structureel wordt bijverkocht. Het systeem markeert elke zo'n offerte. |
| € 1.295 inclusief montage | 15,7% marge — precies op de markbodem en net boven de drempel. |
| € 1.449 inclusief montage | 24,7% marge — dit is waar de calculatie vanzelf op uitkomt. |

Het dashboard rekent dit continu door onder **Catalogus → Vanaf-prijs**, zodat
het antwoord meebeweegt zodra de inkoopprijzen of normtijden veranderen.

---

## 9. Wat KlimaatX zelf moet aanleveren om dit definitief te maken

1. ~~Werkelijke inkoopprijslijst van de eigen groothandel (per merk/model/kW).~~
   **Geleverd op 3 september 2026** voor apparatuur: MHI 2026 en KAISAI 2026-6.
   Nog niet geleverd: materiaal (koelleiding, beugels, condens, kernboring,
   elektra) en toebehoren buiten deze twee lijsten.
2. Eigen gehanteerde margestaffels per productgroep. Nu nog één opslag van 45%
   op apparatuur en 60% op materiaal.
3. Eigen normtijden per werksoort en de werkelijke ploeggrootte.
4. Werkelijk uurtarief en voorrijregeling.
5. Vestigingspostcode en werkgebied.
6. Merken/modellen die daadwerkelijk gevoerd worden, inclusief garantietermijnen.
7. Wat de advertentieprijs van € 899 precies dekt: het apparaat, of apparaat plus montage.
