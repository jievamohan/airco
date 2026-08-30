@php
    $start = $appointment->starts_at->timezone($appointment->timezone);
    $end = $appointment->ends_at->timezone($appointment->timezone);
    $opname = $appointment->kind === 'survey';
@endphp
{{ $opname ? 'DE OPNAME STAAT GENOTEERD' : 'UW AFSPRAAK STAAT GENOTEERD' }}

Beste {{ $lead->name }},

@if ($opname)
Fijn dat we langs mogen komen om uw situatie te bekijken. Daarna weten we precies wat er nodig is en krijgt u de offerte met de definitieve prijs.
@else
Bedankt voor uw akkoord. We hebben de installatie voor u ingepland.
@endif

Datum: {{ $start->translatedFormat('l j F Y') }}
Tijd: {{ $start->format('H:i') }} – {{ $end->format('H:i') }} uur
Locatie: {{ $appointment->location ?? $lead->displayLocation() }}

De afspraak zit als bijlage bij deze mail, zodat u hem met één tik in uw eigen agenda zet.

@if ($opname)
WAT WE KOMEN DOEN
- De ruimte opmeten en bepalen welk vermogen u nodig heeft.
- Kijken waar de buitenunit kan staan en welke route de leiding neemt.
- Controleren of de meterkast toereikend is en waar het condenswater weg kan.

Handig als we bij beide plekken even naar binnen kunnen. Direct na het bezoek sturen we u de offerte. Het bedrag dat u eerder kreeg was een richtbedrag; de offerte is bindend.
@else
ZO HELPT U ONZE MONTEURS
- Zorg dat de ruimte waar de binnenunit komt vrij toegankelijk is.
- Houd de plek van de buitenunit en de route ernaartoe bereikbaar.
- Zorg dat er iemand aanwezig is die beslissingen kan nemen over de plaatsing.
@endif

Moet de afspraak verzet worden? Laat het ons even weten, dan zoeken we samen een ander moment.@if (! empty($company['phone'])) Bel {{ $company['phone'] }}.@endif

Met vriendelijke groet,
{{ $company['name'] }}
@include('mail.text.signature')
