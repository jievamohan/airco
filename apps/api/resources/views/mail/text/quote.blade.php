@php use App\Support\Money; @endphp
UW OFFERTE VOOR AIRCONDITIONING

Beste {{ $lead->name }},

Bedankt voor het prettige gesprek. Hieronder staat de offerte zoals we die hebben besproken. De volledige specificatie zit als pdf bij deze mail.

Totaal inclusief btw en montage: {{ Money::euro($quote->total_cents) }}

Offerte {{ $quote->number }}
Systeem: {{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ Money::kilowatt($quote->total_kw) }}
Montage op locatie: ongeveer {{ Money::hours($quote->onsite_minutes) }}
Geldig tot: {{ optional($quote->valid_until)->translatedFormat('j F Y') ?? '—' }}

SPECIFICATIE
@foreach ($items as $item)
- {{ $item->description }} — {{ Money::quantity((float) $item->quantity) }} {{ $item->unit }} — {{ Money::euro($item->line_total_cents) }}
@endforeach

Subtotaal excl. btw: {{ Money::euro($quote->subtotal_cents) }}
Btw {{ Money::percentage((float) $quote->vat_rate) }}: {{ Money::euro($quote->vat_cents) }}
Totaal incl. btw: {{ Money::euro($quote->total_cents) }}
@if (! empty($quote->assumptions))

WAAR WE VAN UIT ZIJN GEGAAN
@foreach ($quote->assumptions as $assumption)
- {{ $assumption }}
@endforeach
@endif

We bellen u binnenkort na om de offerte door te nemen en, als u akkoord bent, meteen een installatiedatum te prikken. Liever zelf contact opnemen? Bel {{ $company['phone'] }}.

Deze offerte is opgesteld op basis van de gegevens die u heeft doorgegeven. Wijkt de situatie op locatie af — bijvoorbeeld in leidinglengte, bereikbaarheid van de gevel of de beschikbare elektragroep — dan stemmen we een aangepaste prijs met u af voordat het werk begint.

Met vriendelijke groet,
{{ $company['name'] }}
@include('mail.text.signature')
