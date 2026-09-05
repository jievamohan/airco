# Een prijslijst van de leverancier in de catalogus krijgen

De catalogus rekent met netto inkoopprijzen. Die komen uit de werkbladen die de
leverancier aanlevert. Dit is de route van zo'n werkblad naar een offerte.

## Wat er nu in staat

| Lijst | Bestand | Merk | Ontvangen |
|---|---|---|---|
| `mhi-2026` | `apps/api/database/data/pricelists/mhi-2026.csv` | Mitsubishi Heavy Industries | 3 september 2026 |
| `kaisai-2026` | `apps/api/database/data/pricelists/kaisai-2026.csv` | KAISAI | 3 september 2026 |

De `.xlsx`-bestanden zelf gaan **niet** mee de repo in: ze zijn groot en de
leverancier merkt ze aan als vertrouwelijk. `/.gitignore` houdt ze tegen. Bewaar
ze op de gebruikelijke plek buiten de repo; wat de applicatie nodig heeft is de
CSV-uitdraai.

## De drie stappen

### 1. Werkblad → CSV

```bash
python3 scripts/pricelist/xlsx-naar-csv.py "Prijslijst MHI 2026.xlsx" \
  apps/api/database/data/pricelists/mhi-2026.csv
```

Het script (vereist `openpyxl`) doet niets anders dan overtypen: blad, sectiekop,
artikelnummer, omschrijving, bruto, netto, korting. Er wordt bewust niets
omgerekend, zodat de CSV naast het werkblad te leggen is.

Een artikel herkent het aan een bruto- én nettobedrag. Regels met "op aanvraag"
vallen er dus vanzelf uit — en dat hoort: een offerte met een gok erin is erger
dan een offerte die een post mist.

### 2. Secties indelen

`apps/api/app/Services/Pricing/PriceListRegistry.php` zegt per sectiekop wat het
is: het soort (`equipment_set`, `equipment_outdoor`, `equipment_indoor`,
`equipment_heatpump`, `equipment_accessory`), de productlijn (`series`) en
eventueel de kwaliteitsklasse.

Een sectie die daar niet in staat wordt overgeslagen **met een waarschuwing** —
in het log bij een deploy, en in beeld bij het commando hieronder. Dat is de
plek waar je merkt dat een nieuwe lijst een lijn heeft die je nog moet indelen.

### 3. Importeren

```bash
docker compose exec api php artisan catalog:import-pricelist --dry-run
docker compose exec api php artisan catalog:import-pricelist
```

Zonder argument gaan alle bekende lijsten; met `mhi-2026` alleen die ene.

Op productie hoeft dit niet met de hand: `PriceListSeeder` draait mee in
`php artisan db:seed`, en dat gebeurt bij elke deploy.

## Wat de import niet aanraakt

- **Eigen invoer.** Zodra iemand in het dashboard een inkoopprijs, marge of
  normtijd aanpast, gaat de regel op `price_source = dashboard` en laat elke
  volgende import hem staan. Wie dat doet weet iets wat de prijslijst niet weet.
- **Regels waar offertes naar wijzen.** Vervangen regels worden op inactief
  gezet, nooit verwijderd. Een offerte van vorige maand moet naderhand nog te
  lezen zijn.

En er wordt pas iets op inactief gezet als er voor dat soort ook echt een
vervanger in de catalogus staat. Ontbreekt het CSV-bestand of loopt de import
stuk, dan blijft de oude regel gewoon werken.

## Welke lijn hoort bij welke klasse

Dat staat in `apps/api/config/agent.php` onder `pricing.series` — niet op de
catalogusregel zelf, want de buitenunit van een multisplit wordt door twee
klassen gedeeld en een regel kan maar één klasse dragen.

```php
'series' => [
    'budget'  => ['equipment_set' => 'kaisai-evo',        ...],
    'mid'     => ['equipment_set' => 'kaisai-ice-white',  ...],
    'premium' => ['equipment_set' => 'mhi-srk-zs-wf',     ...],
],
```

Eén regel om in de gaten te houden: **buiten- en binnenunit van een multisplit
moeten van hetzelfde merk zijn.** Houd de drie soorten per klasse dus binnen één
merk.

## Klasse en normtijd horen er altijd bij

Elke regel uit een prijslijst krijgt een kwaliteitsklasse en een normtijd. Geen
van beide mag leeg blijven:

- Een lege **klasse** leest in het dashboard als "hier ontbreekt iets".
- Een **normtijd** van nul telt in de offerte stilzwijgend als nul uur mee — de
  klus wordt dan te goedkoop aangeboden zonder dat iemand het ziet.

De klasse staat per sectie in `PriceListRegistry` (`tier`). Alle Mitsubishi is
premium; bij KAISAI zijn de instaplijnen (EVO, FLY, FLY+, GEO+) voordelig en de
rest middenklasse. De KAISAI-multisplitbuitenunit draagt `budget`, maar wordt
door beide KAISAI-klassen gebruikt — dat staat in de omschrijving van die regels.

De normtijd komt uit `PriceListImporter`: `LABOUR_MINUTES` per soort, met
`LABOUR_MINUTES_BY_SERIES` voor de uitzonderingen. Die uitzonderingen bestaan
omdat het soort te grof is — een cassette in het plafond (480 min) is een ander
karwei dan een wandunit ophangen (330 min), en zonder dat onderscheid rekent een
offerte voor inbouwwerk structureel te weinig uren.

Een test bewaakt dit: `PrijslijstImportTest::elk_artikel_uit_een_prijslijst_heeft_een_klasse_en_een_normtijd`.
Voeg je een lijn toe zonder indeling, dan valt de suite om in plaats van dat het
op een offerte opduikt.

Materiaal en toeslagen hebben geen klasse: die gaan mee ongeacht welk merk de
klant kiest. Het dashboard toont daar "Alle klassen". Vijf materiaalregels staan
bewust op normtijd nul (leidingset, beugel, condensafvoer, klein materiaal,
F-gassenregistratie): hun werk zit al in de 330 minuten van de set. Daar tijd
bij zetten telt dubbel in elke offerte.

## Vermogen en rekenklasse

De offerte kiest een unit op `capacity_class_kw`, niet op het echte vermogen.
Een 3,4 kW-set bedient de 3,5 kW-klasse; op het echte vermogen zou de offerte er
onnodig een maat overheen gaan. De importeur leidt die klasse af uit het
vermogen, met 4% speling naar beneden. Units boven 8,9 kW krijgen geen klasse:
die vallen buiten het woningwerk waar de agent op rekent en worden dus nooit
vanzelf gekozen — ze staan er om met de hand op te offreren.

Bij Mitsubishi staat het vermogen in het typenummer (SRK35 = 3,5 kW) en niet
altijd in de omschrijving; dat staat aan met `modelNumbersCarryCapacity`.

## Aansluitingen van een multisplit-buitenunit

KAISAI zet het aantal binnenunits in de omschrijving ("5,3kW | 2iu"). Mitsubishi
niet: die staan in de productdocumentatie en zijn overgenomen in
`PriceListImporter::MHI_SCM_PORTS`. Dat is dus een afleiding en geen bron —
controleer hem bij een nieuwe SCM-serie.
