@php
    $start = $appointment->starts_at->timezone($appointment->timezone);
    $end = $appointment->ends_at->timezone($appointment->timezone);
@endphp
<x-mail.layout
    :company="$company"
    title="Uw installatieafspraak staat genoteerd"
    :preheader="$start->translatedFormat('l j F').' van '.$start->format('H:i').' tot '.$end->format('H:i').' uur.'"
>
    <x-mail.heading>Uw afspraak staat genoteerd</x-mail.heading>

    <x-mail.text>Beste {{ $lead->name }},</x-mail.text>

    <x-mail.text>
        Bedankt voor uw akkoord. We hebben de installatie voor u ingepland.
    </x-mail.text>

    <x-mail.panel title="Uw afspraak">
        <x-mail.facts>
            <x-mail.fact label="Datum">{{ $start->translatedFormat('l j F Y') }}</x-mail.fact>
            <x-mail.fact label="Tijd">{{ $start->format('H:i') }} – {{ $end->format('H:i') }} uur</x-mail.fact>
            <x-mail.fact label="Locatie">{{ $appointment->location ?? $lead->displayLocation() }}</x-mail.fact>
        </x-mail.facts>
    </x-mail.panel>

    <x-mail.text>
        De afspraak zit als bijlage bij deze mail, zodat u hem met één tik in uw eigen agenda zet.
    </x-mail.text>

    <x-mail.divider />

    <x-mail.panel title="Zo helpt u onze monteurs">
        <x-mail.bullets :items="[
            'Zorg dat de ruimte waar de binnenunit komt vrij toegankelijk is.',
            'Houd de plek van de buitenunit en de route ernaartoe bereikbaar.',
            'Zorg dat er iemand aanwezig is die beslissingen kan nemen over de plaatsing.',
        ]" />
    </x-mail.panel>

    <x-mail.text>
        Moet de afspraak verzet worden? Laat het ons even weten, dan zoeken we samen een
        ander moment.
    </x-mail.text>

    @if (! empty($company['phone']))
        <x-mail.button :href="'tel:'.$company['phone_link']">Bel {{ $company['phone'] }}</x-mail.button>
    @endif
</x-mail.layout>
