{{ mb_strtoupper($headline) }}
@foreach ($lines as $line)

{{ $line }}
@endforeach

LEAD
Naam: {{ $lead->name }}
Telefoon: {{ $lead->phone ?? '—' }}
E-mail: {{ $lead->email ?? '—' }}
Adres: {{ $lead->displayLocation() ?: '—' }}
Status: {{ $lead->status->label() }}
Bron: {{ $lead->source }}
Belpogingen: {{ $lead->call_attempts }}
@if ($lead->notes)

OPMERKINGEN
{{ $lead->notes }}
@endif

De volledige tijdlijn staat in het dashboard onder lead {{ $lead->uuid }}.
