@php
    $start = $appointment->starts_at->timezone($appointment->timezone);
    $end = $appointment->ends_at->timezone($appointment->timezone);
@endphp
UW AFSPRAAK STAAT GENOTEERD

Beste {{ $lead->name }},

Bedankt voor uw akkoord. We hebben de installatie voor u ingepland.

Datum: {{ $start->translatedFormat('l j F Y') }}
Tijd: {{ $start->format('H:i') }} – {{ $end->format('H:i') }} uur
Locatie: {{ $appointment->location ?? $lead->displayLocation() }}

De afspraak zit als bijlage bij deze mail, zodat u hem met één tik in uw eigen agenda zet.

ZO HELPT U ONZE MONTEURS
- Zorg dat de ruimte waar de binnenunit komt vrij toegankelijk is.
- Houd de plek van de buitenunit en de route ernaartoe bereikbaar.
- Zorg dat er iemand aanwezig is die beslissingen kan nemen over de plaatsing.

Moet de afspraak verzet worden? Laat het ons even weten, dan zoeken we samen een ander moment.@if (! empty($company['phone'])) Bel {{ $company['phone'] }}.@endif

Met vriendelijke groet,
{{ $company['name'] }}
@include('mail.text.signature')
