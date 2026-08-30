<x-mail::message>
@php($opname = $appointment->kind === 'survey')
# {{ $opname ? 'De opname staat genoteerd' : 'Uw installatieafspraak staat genoteerd' }}

Beste {{ $lead->name }},

@if ($opname)
Fijn dat we langs mogen komen om uw situatie te bekijken. We zijn er op:
@else
Bedankt voor uw akkoord. We komen langs op:
@endif

**{{ $appointment->starts_at->timezone($appointment->timezone)->translatedFormat('l j F Y') }}
van {{ $appointment->starts_at->timezone($appointment->timezone)->format('H:i') }}
tot {{ $appointment->ends_at->timezone($appointment->timezone)->format('H:i') }} uur**

Locatie: {{ $appointment->location ?? $lead->displayLocation() }}

De afspraak zit als bijlage bij deze mail, zodat u hem direct in uw eigen agenda
kunt zetten.

@if ($opname)
Tijdens het bezoek meten we de ruimte op, kijken we waar de buitenunit kan
komen, welke route de leiding neemt en of de meterkast toereikend is. Handig als
we bij beide plekken even kunnen kijken.

Direct na dit bezoek sturen we u de offerte met de definitieve prijs. De
indicatie die u eerder kreeg, was een richtbedrag; deze offerte is bindend.
@else
Zorgt u ervoor dat de ruimte en de plek van de buitenunit bereikbaar zijn? Dan
kunnen onze monteurs meteen aan de slag.
@endif

Moet de afspraak verzet worden? Bel ons op {{ $company['phone'] }}.

Met vriendelijke groet,
{{ $company['name'] }}
</x-mail::message>
