@php
    $bereikbaar = implode(' · ', array_filter([$company['phone'] ?? '', $company['email'] ?? '']));
@endphp
{{ $bereikbaar }}
@if (! empty($company['legal_line']))
{{ $company['legal_line'] }}
@endif
@if (! empty($company['website']))
{{ $company['website'] }}
@endif
