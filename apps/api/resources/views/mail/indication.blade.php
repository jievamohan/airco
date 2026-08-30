@php use App\Support\Money; @endphp
<x-mail.layout
    :company="$company"
    title="Uw vrijblijvende prijsindicatie"
    :preheader="'Richtbedrag '.Money::euro($quote->total_cents).' inclusief btw en montage — de offerte volgt na de opname.'"
>
    <x-mail.heading>Uw vrijblijvende prijsindicatie</x-mail.heading>

    <x-mail.text>Beste {{ $lead->name }},</x-mail.text>

    <x-mail.text>
        Bedankt voor het prettige gesprek. Hieronder ziet u wat een installatie voor uw
        situatie ongeveer kost. De volledige specificatie zit als pdf bij deze mail.
    </x-mail.text>

    {{-- De prijs waar het om gaat, in één oogopslag --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 18px; background-color:#0a0a0a; border-radius:8px;">
        <tr>
            <td style="padding:22px 24px; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif;">
                <p style="margin:0 0 4px; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#9a9a9a;">Richtbedrag inclusief btw en montage</p>
                <p style="margin:0; font-size:30px; line-height:1.15; font-weight:600; letter-spacing:-0.02em; color:#ffffff;">{{ Money::euro($quote->total_cents) }}</p>
            </td>
        </tr>
    </table>

    <x-mail.panel title="Prijsindicatie {{ $quote->number }}">
        <x-mail.facts>
            <x-mail.fact label="Systeem">{{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ Money::kilowatt($quote->total_kw) }}</x-mail.fact>
            <x-mail.fact label="Montage op locatie">ongeveer {{ Money::hours($quote->onsite_minutes) }}</x-mail.fact>
            <x-mail.fact label="Richtbedrag geldig tot">{{ optional($quote->valid_until)->translatedFormat('j F Y') ?? '—' }}</x-mail.fact>
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
            <td colspan="2" align="right" style="padding:10px 8px 0 0; border-top:1px solid #0a0a0a; font-size:15px; font-weight:600; color:#0a0a0a;">Richtbedrag incl. btw</td>
            <td align="right" style="padding:10px 0 0 8px; border-top:1px solid #0a0a0a; font-size:15px; font-weight:600; color:#0a0a0a; white-space:nowrap;">{{ Money::euro($quote->total_cents) }}</td>
        </tr>
    </table>

    {{-- Aan de telefoon ligt er nog geen bedrag, dus daar valt over de
         uitvoering niets te kiezen. Hier wel: hetzelfde systeem, alleen een
         ander merkniveau, met het verschil erbij. --}}
    @if (! empty($alternatieven))
        <x-mail.divider />
        <x-mail.panel title="Liever een andere uitvoering?">
            <x-mail.text small>
                Dit richtbedrag rekent met de {{ strtolower($klasse->label()) }}. Hetzelfde systeem
                en dezelfde montage kan ook in een ander merkniveau — alleen de apparatuur verschilt:
            </x-mail.text>
            <x-mail.bullets :items="$alternatieven" />
            <x-mail.text small muted>
                Zegt u er niets over, dan houden we het richtbedrag hierboven aan. Wilt u een andere
                uitvoering, geef het door in het gesprek of per mail — dan rekenen we het opnieuw door.
            </x-mail.text>
        </x-mail.panel>
    @endif

    @if (! empty($quote->assumptions))
        <x-mail.divider />
        <x-mail.panel title="Waar we van uit zijn gegaan">
            <x-mail.bullets :items="$quote->assumptions" />
        </x-mail.panel>
    @else
        <x-mail.divider />
    @endif

    <x-mail.panel title="Dit is nog geen offerte">
        <x-mail.bullets :items="[
            'Dit is een richtbedrag op basis van wat u ons heeft verteld. U kunt er geen rechten aan ontlenen.',
            'We komen eerst kort langs: leidinglengte, plek van de buitenunit, doorvoeren en de elektragroep.',
            'Direct daarna krijgt u de offerte met de definitieve prijs. Die is wél bindend.',
        ]" />
    </x-mail.panel>

    <x-mail.text>
        We bellen u binnenkort om deze indicatie door te nemen en een moment voor de opname af
        te spreken. Liever zelf contact opnemen? Dat kan ook.
    </x-mail.text>

    @if (! empty($company['phone']))
        <x-mail.button :href="'tel:'.$company['phone_link']">Bel {{ $company['phone'] }}</x-mail.button>
    @endif

    <x-mail.text muted small>
        Deze prijsindicatie is opgesteld op basis van de gegevens die u heeft doorgegeven en is
        vrijblijvend. Wijkt de situatie op locatie af — in leidinglengte, bereikbaarheid van de
        gevel of de beschikbare elektragroep — dan ziet u dat terug in de offerte die na de
        opname volgt.
    </x-mail.text>
</x-mail.layout>
