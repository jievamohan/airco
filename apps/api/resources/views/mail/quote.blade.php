@php use App\Support\Money; @endphp
<x-mail.layout
    :company="$company"
    title="Uw offerte voor airconditioning"
    :preheader="'Offerte '.$quote->number.' — '.Money::euro($quote->total_cents).' inclusief btw en montage.'"
>
    <x-mail.heading>Uw offerte voor airconditioning</x-mail.heading>

    <x-mail.text>Beste {{ $lead->name }},</x-mail.text>

    <x-mail.text>
        Bedankt dat we bij u langs mochten komen. Hieronder staat de offerte, opgesteld op
        basis van wat we ter plaatse hebben gezien. De volledige specificatie zit als pdf bij
        deze mail.
    </x-mail.text>

    {{-- De prijs waar het om gaat, in één oogopslag --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 18px; background-color:#0a0a0a; border-radius:8px;">
        <tr>
            <td style="padding:22px 24px; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif;">
                <p style="margin:0 0 4px; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#9a9a9a;">Totaal inclusief btw en montage</p>
                <p style="margin:0; font-size:30px; line-height:1.15; font-weight:600; letter-spacing:-0.02em; color:#ffffff;">{{ Money::euro($quote->total_cents) }}</p>
            </td>
        </tr>
    </table>

    <x-mail.panel title="Offerte {{ $quote->number }}">
        <x-mail.facts>
            <x-mail.fact label="Systeem">{{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ Money::kilowatt($quote->total_kw) }}</x-mail.fact>
            <x-mail.fact label="Montage op locatie">ongeveer {{ Money::hours($quote->onsite_minutes) }}</x-mail.fact>
            <x-mail.fact label="Geldig tot">{{ optional($quote->valid_until)->translatedFormat('j F Y') ?? '—' }}</x-mail.fact>
        </x-mail.facts>
    </x-mail.panel>

    {{-- Specificatie --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif;">
        <tr>
            <th align="left" style="padding:0 8px 8px 0; border-bottom:1px solid #0a0a0a; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#6b6b6b;">Omschrijving</th>
            <th align="right" style="padding:0 8px 8px; border-bottom:1px solid #0a0a0a; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#6b6b6b; white-space:nowrap;">Aantal</th>
            <th align="right" style="padding:0 0 8px 8px; border-bottom:1px solid #0a0a0a; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#6b6b6b; white-space:nowrap;">Bedrag</th>
        </tr>
        @foreach ($items as $item)
            <tr>
                <td align="left" style="padding:9px 8px 9px 0; border-bottom:1px solid #ececec; font-size:14px; line-height:1.5; color:#2f2f2f;">{{ $item->description }}</td>
                <td align="right" style="padding:9px 8px; border-bottom:1px solid #ececec; font-size:14px; line-height:1.5; color:#6b6b6b; white-space:nowrap;">{{ Money::quantity((float) $item->quantity) }} {{ $item->unit }}</td>
                <td align="right" style="padding:9px 0 9px 8px; border-bottom:1px solid #ececec; font-size:14px; line-height:1.5; color:#2f2f2f; white-space:nowrap;">{{ Money::euro($item->line_total_cents) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" align="right" style="padding:12px 8px 4px 0; font-size:14px; color:#6b6b6b;">Subtotaal excl. btw</td>
            <td align="right" style="padding:12px 0 4px 8px; font-size:14px; color:#2f2f2f; white-space:nowrap;">{{ Money::euro($quote->subtotal_cents) }}</td>
        </tr>
        <tr>
            <td colspan="2" align="right" style="padding:4px 8px 10px 0; font-size:14px; color:#6b6b6b;">Btw {{ Money::percentage((float) $quote->vat_rate) }}</td>
            <td align="right" style="padding:4px 0 10px 8px; font-size:14px; color:#2f2f2f; white-space:nowrap;">{{ Money::euro($quote->vat_cents) }}</td>
        </tr>
        <tr>
            <td colspan="2" align="right" style="padding:10px 8px 0 0; border-top:1px solid #0a0a0a; font-size:15px; font-weight:600; color:#0a0a0a;">Totaal incl. btw</td>
            <td align="right" style="padding:10px 0 0 8px; border-top:1px solid #0a0a0a; font-size:15px; font-weight:600; color:#0a0a0a; white-space:nowrap;">{{ Money::euro($quote->total_cents) }}</td>
        </tr>
    </table>

    @if (! empty($quote->assumptions))
        <x-mail.divider />
        <x-mail.panel title="Waar we van uit zijn gegaan">
            <x-mail.bullets :items="$quote->assumptions" />
        </x-mail.panel>
    @else
        <x-mail.divider />
    @endif

    <x-mail.text>
        Dit bedrag is vastgesteld na de opname bij u thuis. Gaat u akkoord, dan geldt het voor
        het werk zoals hierboven beschreven; er komen achteraf geen kosten bij voor wat hierin
        staat.
    </x-mail.text>

    <x-mail.text>
        We bellen u binnenkort na om de offerte door te nemen en, als u akkoord bent,
        meteen een installatiedatum te prikken. Liever zelf contact opnemen? Dat kan ook.
    </x-mail.text>

    @if (! empty($company['phone']))
        <x-mail.button :href="'tel:'.$company['phone_link']">Bel {{ $company['phone'] }}</x-mail.button>
    @endif

    <x-mail.text muted small>
        Deze offerte is opgesteld na een opname ter plaatse en geldt tot de genoemde datum.
        Wilt u iets wijzigen aan de opstelling of de uitvoering, dan maken we daar een
        aangepaste offerte voor voordat het werk begint.
    </x-mail.text>
</x-mail.layout>
