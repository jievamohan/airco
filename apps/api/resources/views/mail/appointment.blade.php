@php
    $start = $appointment->starts_at->timezone($appointment->timezone);
    $end = $appointment->ends_at->timezone($appointment->timezone);
    // De opname gaat vooraf aan de offerte; de installatie volgt erop. Twee
    // bezoeken die niets met elkaar te maken hebben behalve de voordeur.
    $opname = $appointment->kind === 'survey';
@endphp
<x-mail.layout
    :company="$company"
    :title="$opname ? 'De opname bij u thuis staat genoteerd' : 'Uw installatieafspraak staat genoteerd'"
    :preheader="$start->translatedFormat('l j F').' van '.$start->format('H:i').' tot '.$end->format('H:i').' uur.'"
>
    <x-mail.heading>{{ $opname ? 'De opname staat genoteerd' : 'Uw afspraak staat genoteerd' }}</x-mail.heading>

    <x-mail.text>Beste {{ $lead->name }},</x-mail.text>

    @if ($opname)
        <x-mail.text>
            Fijn dat we langs mogen komen om uw situatie te bekijken. Daarna weten we precies
            wat er nodig is en krijgt u de offerte met de definitieve prijs.
        </x-mail.text>
    @else
        <x-mail.text>
            Bedankt voor uw akkoord. We hebben de installatie voor u ingepland.
        </x-mail.text>
    @endif

    <x-mail.panel :title="$opname ? 'De opname' : 'Uw afspraak'">
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

    @if ($opname)
        <x-mail.panel title="Wat we komen doen">
            <x-mail.bullets :items="[
                'De ruimte opmeten en bepalen welk vermogen u nodig heeft.',
                'Kijken waar de buitenunit kan staan en welke route de leiding neemt.',
                'Controleren of de meterkast toereikend is en waar het condenswater weg kan.',
            ]" />
        </x-mail.panel>

        <x-mail.text>
            Handig als we bij beide plekken even naar binnen kunnen. Direct na het bezoek
            sturen we u de offerte. Het bedrag dat u eerder kreeg was een richtbedrag; de
            offerte is bindend.
        </x-mail.text>
    @else
        <x-mail.panel title="Zo helpt u onze monteurs">
            <x-mail.bullets :items="[
                'Zorg dat de ruimte waar de binnenunit komt vrij toegankelijk is.',
                'Houd de plek van de buitenunit en de route ernaartoe bereikbaar.',
                'Zorg dat er iemand aanwezig is die beslissingen kan nemen over de plaatsing.',
            ]" />
        </x-mail.panel>
    @endif

    <x-mail.text>
        Moet de afspraak verzet worden? Laat het ons even weten, dan zoeken we samen een
        ander moment.
    </x-mail.text>

    @if (! empty($company['phone']))
        <x-mail.button :href="'tel:'.$company['phone_link']">Bel {{ $company['phone'] }}</x-mail.button>
    @endif
</x-mail.layout>
