@php use App\Support\Money; @endphp
@php
    $headings = [
        'missed_call' => 'WE HEBBEN U GEPROBEERD TE BELLEN',
        'quote_without_call' => 'UW VRIJBLIJVENDE PRIJSINDICATIE',
        'last_chance' => 'LAATSTE BERICHT OVER UW AANVRAAG',
    ];
@endphp
{{ $headings[$variant] ?? 'OVER UW AANVRAAG' }}

Beste {{ $lead->name }},
@if ($variant === 'missed_call')

We probeerden u te bereiken over uw aanvraag voor airconditioning, maar kregen geen gehoor. Geen probleem — laat ons weten wanneer het u schikt, dan bellen we op dat moment terug. U kunt ook gewoon antwoorden op deze mail.

@if (! empty($company['phone']))Bel {{ $company['phone'] }}.
@endif
@elseif ($variant === 'quote_without_call')

Het is nog niet gelukt u telefonisch te spreken. Daarom sturen we alvast een indicatie op basis van de gegevens uit uw aanvraag.
@if ($quote)

Indicatie inclusief btw en montage: {{ Money::euro($quote->total_cents) }}
Systeem: {{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ Money::kilowatt($quote->total_kw) }}
Montage op locatie: ongeveer {{ Money::hours($quote->onsite_minutes) }}

Dit is een indicatie, nog geen offerte. Bij de schouw kijken we naar de leidinglengte, de plek van de buitenunit en de elektra; pas daarna staat de prijs vast.
@endif

@if (! empty($company['phone']))Bel {{ $company['phone'] }}.
@endif
@else

We hebben u een paar keer geprobeerd te bereiken over uw aanvraag. Omdat we u niet willen blijven benaderen, is dit ons laatste bericht.

Wilt u er later toch op terugkomen? Stuur dan gerust een mail of bel ons; uw gegevens blijven bewaard.
@endif

Met vriendelijke groet,
{{ $company['name'] }}
@include('mail.text.signature')
