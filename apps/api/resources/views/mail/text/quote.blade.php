@php use App\Support\Money; @endphp
UW OFFERTE VOOR AIRCONDITIONING

Beste {{ $lead->name }},

Bedankt dat we bij u langs mochten komen. Hieronder staat de offerte, opgesteld op basis van wat we ter plaatse hebben gezien. De volledige specificatie zit als pdf bij deze mail.

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

Dit bedrag is vastgesteld na de opname bij u thuis. Gaat u akkoord, dan geldt het voor het werk zoals hierboven beschreven; er komen achteraf geen kosten bij voor wat hierin staat.

We bellen u binnenkort na om de offerte door te nemen en, als u akkoord bent, meteen een installatiedatum te prikken. Liever zelf contact opnemen?@if (! empty($company['phone'])) Bel {{ $company['phone'] }}.@endif

Deze offerte is opgesteld na een opname ter plaatse en geldt tot de genoemde datum. Wilt u iets wijzigen aan de opstelling of de uitvoering, dan maken we daar een aangepaste offerte voor voordat het werk begint.

Met vriendelijke groet,
{{ $company['name'] }}
@include('mail.text.signature')
