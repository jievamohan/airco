<x-mail::message>
# {{ $headline }}

@foreach ($lines as $line)
{{ $line }}

@endforeach

<x-mail::table>
| | |
| :--- | :--- |
| Naam | {{ $lead->name }} |
| Telefoon | {{ $lead->phone ?? '—' }} |
| E-mail | {{ $lead->email ?? '—' }} |
| Adres | {{ $lead->displayLocation() ?: '—' }} |
| Status | {{ $lead->status->label() }} |
| Bron | {{ $lead->source }} |
| Belpogingen | {{ $lead->call_attempts }} |
</x-mail::table>

@if ($lead->notes)
**Opmerkingen**

{{ $lead->notes }}
@endif

Bekijk de volledige tijdlijn in het dashboard onder lead {{ $lead->uuid }}.
</x-mail::message>
