<x-mail::message>
# Uw vrijblijvende prijsindicatie

Beste {{ $lead->name }},

Bedankt voor het prettige gesprek. Hieronder ziet u wat een installatie voor uw
situatie ongeveer kost. De volledige specificatie zit als pdf bij deze mail.

**Kenmerk:** {{ $quote->number }}
**Richtbedrag geldig tot:** {{ optional($quote->valid_until)->format('d-m-Y') }}
**Systeem:** {{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ number_format((float) $quote->total_kw, 1, ',', '.') }} kW
**Montageduur op locatie:** ongeveer {{ number_format($quote->onsite_minutes / 60, 1, ',', '.') }} uur

<x-mail::table>
| Omschrijving | Aantal | Bedrag |
| :----------- | -----: | -----: |
@foreach ($items as $item)
| {{ $item->description }} | {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->unit }} | € {{ number_format($item->line_total_cents / 100, 2, ',', '.') }} |
@endforeach
| **Subtotaal excl. btw** | | **€ {{ number_format($quote->subtotal_cents / 100, 2, ',', '.') }}** |
| Btw {{ rtrim(rtrim(number_format((float) $quote->vat_rate, 1, ',', '.'), '0'), ',') }}% | | € {{ number_format($quote->vat_cents / 100, 2, ',', '.') }} |
| **Richtbedrag incl. btw** | | **€ {{ number_format($quote->total_cents / 100, 2, ',', '.') }}** |
</x-mail::table>

**Dit is nog geen offerte.** Het is een indicatie op basis van wat u ons heeft
verteld; aan dit bedrag kunt u geen rechten ontlenen. Wat een installatie
werkelijk kost, hangt af van dingen die we ter plaatse moeten zien: de
leidinglengte, de plek van de buitenunit, de doorvoeren en de elektragroep.

Daarom komen we eerst langs voor een korte opname. Meteen daarna krijgt u de
offerte met de definitieve prijs — en die is wél bindend.

@if (! empty($quote->assumptions))
**Waar we van uit zijn gegaan**

@foreach ($quote->assumptions as $assumption)
- {{ $assumption }}
@endforeach
@endif

We bellen u binnenkort even om deze indicatie door te nemen en een moment voor
de opname af te spreken. Liever zelf contact? Bel ons op {{ $company['phone'] }}.

Met vriendelijke groet,
{{ $company['name'] }}
</x-mail::message>
