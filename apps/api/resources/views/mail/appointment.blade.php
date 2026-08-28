<x-mail::message>
# Uw afspraak staat genoteerd

Beste {{ $lead->name }},

Bedankt voor uw akkoord. We komen langs op:

**{{ $appointment->starts_at->timezone($appointment->timezone)->translatedFormat('l j F Y') }}
van {{ $appointment->starts_at->timezone($appointment->timezone)->format('H:i') }}
tot {{ $appointment->ends_at->timezone($appointment->timezone)->format('H:i') }} uur**

Locatie: {{ $appointment->location ?? $lead->displayLocation() }}

De afspraak zit als bijlage bij deze mail, zodat u hem direct in uw eigen agenda
kunt zetten. Zorgt u ervoor dat de ruimte en de plek van de buitenunit
bereikbaar zijn? Dan kunnen onze monteurs meteen aan de slag.

Moet de afspraak verzet worden? Bel ons op {{ $company['phone'] }}.

Met vriendelijke groet,
{{ $company['name'] }}
</x-mail::message>
