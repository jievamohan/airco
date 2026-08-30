<x-mail::message>
@if ($variant === 'missed_call')
# We hebben u geprobeerd te bellen

Beste {{ $lead->name }},

We probeerden u te bereiken over uw aanvraag voor airconditioning, maar kregen
geen gehoor. Geen probleem — laat ons weten wanneer het u schikt, dan bellen we
op dat moment terug. U kunt ook direct antwoorden op deze mail.

@elseif ($variant === 'quote_without_call')
# Uw vrijblijvende prijsindicatie

Beste {{ $lead->name }},

Het is nog niet gelukt u telefonisch te spreken, daarom sturen we alvast een
indicatie op basis van de gegevens uit uw aanvraag.

@if ($quote)
Voor uw situatie komen we uit op **€ {{ number_format($quote->total_cents / 100, 2, ',', '.') }} inclusief btw en montage**
({{ $quote->system_type === 'multi_split' ? 'multisplit' : 'single split' }}, {{ number_format((float) $quote->total_kw, 1, ',', '.') }} kW).
De montage duurt ongeveer {{ number_format($quote->onsite_minutes / 60, 1, ',', '.') }} uur.

Dit is een indicatie, geen offerte: aan dit bedrag kunt u geen rechten ontlenen.
Bij de opname ter plaatse kijken we naar de leidinglengte, de plek van de
buitenunit en de elektra. Pas daarna sturen we de offerte, en die is bindend.
@endif

@else
# Laatste bericht over uw aanvraag

Beste {{ $lead->name }},

We hebben u een paar keer geprobeerd te bereiken over uw aanvraag. Omdat we u
niet willen blijven benaderen, is dit ons laatste bericht. Wilt u er later toch
op terugkomen? Stuur dan gerust een mail of bel ons; uw gegevens blijven bewaard.

@endif

Met vriendelijke groet,
{{ $company['name'] }}
{{ $company['phone'] }}
</x-mail::message>
