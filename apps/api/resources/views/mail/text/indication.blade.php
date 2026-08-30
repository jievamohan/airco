@php use App\Support\Money; @endphp
UW VRIJBLIJVENDE PRIJSINDICATIE

Beste {{ $lead->name }},

Bedankt voor het prettige gesprek. Hieronder ziet u wat een installatie voor uw situatie ongeveer kost. De volledige specificatie zit als pdf bij deze mail.

Richtbedrag inclusief btw en montage: {{ Money::euro($quote->total_cents) }}

Prijsindicatie {{ $quote->number }}
Systeem: {{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ Money::kilowatt($quote->total_kw) }}
Montage op locatie: ongeveer {{ Money::hours($quote->onsite_minutes) }}
Richtbedrag geldig tot: {{ optional($quote->valid_until)->translatedFormat('j F Y') ?? '—' }}

SPECIFICATIE
@foreach ($items as $item)
- {{ $item->description }} — {{ Money::quantity((float) $item->quantity) }} {{ $item->unit }} — {{ Money::euro($item->line_total_cents) }}
@endforeach

Subtotaal excl. btw: {{ Money::euro($quote->subtotal_cents) }}
Btw {{ Money::percentage((float) $quote->vat_rate) }}: {{ Money::euro($quote->vat_cents) }}
Richtbedrag incl. btw: {{ Money::euro($quote->total_cents) }}
@if (! empty($alternatieven))

LIEVER EEN ANDERE UITVOERING?
Dit richtbedrag rekent met de {{ strtolower($klasse->label()) }}. Hetzelfde systeem en dezelfde montage kan ook in een ander merkniveau:
@foreach ($alternatieven as $regel)
- {{ $regel }}
@endforeach

Zegt u er niets over, dan houden we het richtbedrag hierboven aan.
@endif
@if (! empty($quote->assumptions))

WAAR WE VAN UIT ZIJN GEGAAN
@foreach ($quote->assumptions as $assumption)
- {{ $assumption }}
@endforeach
@endif

DIT IS NOG GEEN OFFERTE
- Dit is een richtbedrag op basis van wat u ons heeft verteld. U kunt er geen rechten aan ontlenen.
- We komen eerst kort langs: leidinglengte, plek van de buitenunit, doorvoeren en de elektragroep.
- Direct daarna krijgt u de offerte met de definitieve prijs. Die is wel bindend.

We bellen u binnenkort om deze indicatie door te nemen en een moment voor de opname af te spreken. Liever zelf contact opnemen?@if (! empty($company['phone'])) Bel {{ $company['phone'] }}.@endif

Deze prijsindicatie is opgesteld op basis van de gegevens die u heeft doorgegeven en is vrijblijvend. Wijkt de situatie op locatie af — in leidinglengte, bereikbaarheid van de gevel of de beschikbare elektragroep — dan ziet u dat terug in de offerte die na de opname volgt.

Met vriendelijke groet,
{{ $company['name'] }}
@include('mail.text.signature')
