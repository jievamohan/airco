<x-mail.layout :company="$company" :title="$headline" :preheader="$lines[0] ?? ''" internal>
    <x-mail.heading>{{ $headline }}</x-mail.heading>

    @foreach ($lines as $line)
        <x-mail.text>{{ $line }}</x-mail.text>
    @endforeach

    <x-mail.panel title="Lead">
        <x-mail.facts>
            <x-mail.fact label="Naam">{{ $lead->name }}</x-mail.fact>
            <x-mail.fact label="Telefoon">{{ $lead->phone ?? '—' }}</x-mail.fact>
            <x-mail.fact label="E-mail">{{ $lead->email ?? '—' }}</x-mail.fact>
            <x-mail.fact label="Adres">{{ $lead->displayLocation() ?: '—' }}</x-mail.fact>
            <x-mail.fact label="Status">{{ $lead->status->label() }}</x-mail.fact>
            <x-mail.fact label="Bron">{{ $lead->source }}</x-mail.fact>
            <x-mail.fact label="Belpogingen">{{ $lead->call_attempts }}</x-mail.fact>
        </x-mail.facts>
    </x-mail.panel>

    @if ($lead->notes)
        <x-mail.panel title="Opmerkingen">
            <x-mail.text>{{ $lead->notes }}</x-mail.text>
        </x-mail.panel>
    @endif

    <x-mail.text muted small>
        De volledige tijdlijn staat in het dashboard onder lead {{ $lead->uuid }}.
    </x-mail.text>
</x-mail.layout>
