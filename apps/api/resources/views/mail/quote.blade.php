<x-mail::message>
# Uw offerte voor airconditioning

Beste {{ $lead->name }},

Bedankt voor het prettige gesprek. Hieronder vindt u de offerte zoals we die
hebben besproken. De volledige specificatie zit als pdf bij deze mail.

**Offertenummer:** {{ $quote->number }}
**Geldig tot:** {{ optional($quote->valid_until)->format('d-m-Y') }}
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
| **Totaal incl. btw** | | **€ {{ number_format($quote->total_cents / 100, 2, ',', '.') }}** |
</x-mail::table>

@if (! empty($quote->assumptions))
**Waar we van uit zijn gegaan**

@foreach ($quote->assumptions as $assumption)
- {{ $assumption }}
@endforeach
@endif

We bellen u binnenkort even na om de offerte door te nemen en, als u
akkoord bent, meteen een installatiedatum te prikken. Liever zelf contact?
Bel ons op {{ $company['phone'] }}.

Met vriendelijke groet,
{{ $company['name'] }}
</x-mail::message>
