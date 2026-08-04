# Apple-style interactieve scrollsectie --- Design Brief

## Doel

Ontwerp en implementeer een premium interactieve scrollsectie die
aanvoelt alsof deze afkomstig is van het designteam achter een moderne
Apple-productpagina.

Kopieer geen bestaande Apple-layout, maar gebruik dezelfde
ontwerpprincipes:

-   extreem veel witruimte;
-   grote, krachtige typografie;
-   één boodschap tegelijk;
-   premium minimalisme;
-   redactionele composities;
-   fotografie en visualisatie boven tekst;
-   subtiele motion;
-   geen marketinggevoel;
-   geen dashboardgevoel;
-   geen traditionele infographic.

De bezoeker mag **voelen** dat dezelfde hoeveelheid warmte met een airco
minder kost dan met een oudere cv-ketel, zonder eerst een technische
uitleg te hoeven lezen.

------------------------------------------------------------------------

# Hoofdboodschap

De volledige sectie draait om slechts één inzicht:

> **Voor dezelfde hoeveelheid warmte betaal je met een airco minder.**

Pas daarna mag duidelijk worden dat dit in dit rekenvoorbeeld neerkomt
op ongeveer **59% goedkoper**.

Alle overige informatie ondersteunt uitsluitend deze conclusie.

------------------------------------------------------------------------

# Visuele stijl

Gebruik:

-   witte achtergrond (#FFFFFF)
-   zwarte typografie
-   donkergrijze secundaire tekst
-   SF Pro Display of vergelijkbaar
-   veel negatieve ruimte
-   hoogwaardige productrenders
-   minimale decoratie
-   subtiele lijnen
-   rustige composities

Gebruik geen:

-   kaarten
-   dashboards
-   gradients
-   glassmorphism
-   neonkleuren
-   blobs
-   iconen in cirkels
-   vectorillustraties
-   AI-achtige visuals
-   generieke marketingcomponenten

Alles moet menselijk ontworpen voelen.

------------------------------------------------------------------------

# Scrollverhaal

Gebruik GSAP ScrollTrigger.

Gebruik één sticky container.

Ongeveer 350vh totale scrollhoogte.

## Scene 1 --- Dezelfde warmte

Volledig witte achtergrond.

Grote headline:

**Dezelfde warmte.**

Daaronder:

**Twee manieren om haar te maken.**

Toon een rustige woonkamer.

Links een airco.

Rechts een radiator.

Beide leveren exact hetzelfde comfort.

Nog geen prijzen.

Nog geen techniek.

Nog geen berekeningen.

De bezoeker moet alleen begrijpen dat we exact dezelfde warmte
vergelijken.

------------------------------------------------------------------------

## Scene 2 --- De prijs verandert

Behoud exact dezelfde warme woonkamer.

De warmte verandert niet.

Alleen de prijs verandert.

Links:

Airco

€0,07

per kWh warmte

Rechts:

CV-ketel

€0,17

per kWh warmte

Gebruik een extreem eenvoudige vergelijking.

Onder beide bedragen staat exact dezelfde visuele "warmte-eenheid".

Bijvoorbeeld één identieke warme rechthoek of warmtevlak.

Niet meer warmte.

Niet minder warmte.

Precies dezelfde hoeveelheid warmte.

Daaronder verschijnt een eenvoudige prijsbalk.

De balk van €0,17 is ongeveer 2,43× langer dan die van €0,07.

Geen assen.

Geen grafiek.

Geen legenda.

Geen techniek.

De bezoeker moet intuïtief zien:

**Dezelfde warmte. Andere prijs.**

------------------------------------------------------------------------

## Scene 3 --- De conclusie

Alle elementen verdwijnen langzaam.

Alleen blijft over:

# 59%

ongeveer goedkoper

Daaronder:

Voor dezelfde hoeveelheid warmte.

Daaronder klein:

Gebaseerd op:

-   €0,28 per kWh stroom
-   €1,40 per m³ gas
-   COP 4
-   ketelrendement 80,7%

Daaronder nog kleiner:

Dit is een rekenvoorbeeld. De werkelijke besparing hangt af van woning,
installatie, buitentemperatuur en gebruik.

Heel veel witruimte.

De 59% is het enige dominante element.

------------------------------------------------------------------------

# Motion

Gebruik uitsluitend GSAP.

Gebruik ScrollTrigger.

Gebruik:

-   opacity
-   translateY
-   kleine scale (0.98 → 1)
-   clip-path reveals
-   mask reveals

Gebruik easing:

-   power3.out
-   expo.out

Vermijd:

-   bounce
-   elastic
-   3D
-   blur tijdens lezen
-   glow
-   particles
-   snelle zooms
-   draaiende objecten

Animatie ondersteunt het verhaal.

Niet andersom.

------------------------------------------------------------------------

# Responsive

Desktop:

-   sticky storytelling

Tablet:

-   kortere sticky scenes

Mobiel:

-   lineaire verticale flow
-   dezelfde inhoud
-   minder animatie
-   geen horizontale scroll

------------------------------------------------------------------------

# Accessibility

-   respecteer prefers-reduced-motion
-   gebruik semantische HTML
-   echte tekst, geen tekst in afbeeldingen
-   voldoende contrast
-   logische DOM-volgorde
-   alt-teksten voor visuals

------------------------------------------------------------------------

# Performance

-   GSAP ScrollTrigger
-   transform + opacity
-   AVIF/WebP
-   lazy loading
-   geen layout shifts
-   60 FPS waar mogelijk

------------------------------------------------------------------------

# Zelfreview (uitvoeren vóór afronding)

Loop zelfstandig meerdere iteraties totdat de sectie voldoet aan deze
vragen:

1.  Begrijpt een niet-technische bezoeker binnen 3 seconden de
    kernboodschap?
2.  Is alle techniek ondergeschikt aan het verhaal?
3.  Voelt de sectie premium, rustig en zelfverzekerd?
4.  Zou elk overbodig element verwijderd kunnen worden?
5.  Is de scrollervaring vloeiend en subtiel?
6.  Voelt het alsof deze sectie onderdeel is van een
    Apple-productpresentatie?
7.  Is de boodschap "dezelfde warmte, lagere prijs" direct zichtbaar
    zonder te rekenen?

Blijf verbeteren totdat alle antwoorden overtuigend "ja" zijn.
