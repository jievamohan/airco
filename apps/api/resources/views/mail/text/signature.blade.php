@if (! empty($company['phone'])){{ $company['phone'] }}@endif@if (! empty($company['phone']) && ! empty($company['email'])) · @endif@if (! empty($company['email'])){{ $company['email'] }}@endif

@if (! empty($company['legal_line'])){{ $company['legal_line'] }}
@endif
@if (! empty($company['website'])){{ $company['website'] }}
@endif
