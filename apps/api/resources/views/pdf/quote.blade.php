<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1b1b1b; margin: 0; padding: 32px 36px; }
        h1 { font-size: 20px; margin: 0 0 4px; letter-spacing: -0.02em; }
        .muted { color: #6b6b6b; }
        .head { width: 100%; margin-bottom: 28px; }
        .head td { vertical-align: top; }
        .head .right { text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th { text-align: left; border-bottom: 1px solid #1b1b1b; padding: 6px 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; }
        table.lines td { padding: 6px 4px; border-bottom: 1px solid #e6e6e6; }
        table.lines .num { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin-left: 55%; margin-top: 14px; border-collapse: collapse; }
        .totals td { padding: 4px 4px; }
        .totals .num { text-align: right; white-space: nowrap; }
        .totals tr.grand td { border-top: 1px solid #1b1b1b; font-weight: bold; }
        .block { margin-top: 24px; }
        .block h2 { font-size: 12px; margin: 0 0 6px; }
        ul { margin: 0; padding-left: 16px; }
        li { margin-bottom: 3px; }
        .footer { margin-top: 28px; font-size: 10px; color: #6b6b6b; border-top: 1px solid #e6e6e6; padding-top: 10px; }
    </style>
</head>
<body>
<table class="head">
    <tr>
        <td>
            <h1>{{ $quote->kind->label() }}</h1>
            <div class="muted">{{ $quote->number }}</div>
            @unless ($quote->isBinding())
                <div class="muted">Vrijblijvend richtbedrag, onder voorbehoud van de opname ter plaatse</div>
            @endunless
        </td>
        <td class="right">
            <strong>{{ $company['name'] }}</strong><br>
            {{ $company['postcode'] }} {{ $company['city'] }}<br>
            {{ $company['phone'] }}<br>
            {{ $company['email'] }}
            @if ($company['kvk'])<br>KvK {{ $company['kvk'] }}@endif
            @if ($company['vat_number'])<br>Btw {{ $company['vat_number'] }}@endif
        </td>
    </tr>
</table>

<table class="head">
    <tr>
        <td>
            <div class="muted">Voor</div>
            <strong>{{ $lead->name }}</strong><br>
            {{ $lead->address }}<br>
            {{ $lead->postcode }} {{ $lead->city }}
        </td>
        <td class="right">
            <div class="muted">Datum</div>
            {{ $quote->created_at->format('d-m-Y') }}<br>
            <div class="muted" style="margin-top:6px">{{ $quote->isBinding() ? 'Geldig tot' : 'Richtbedrag geldig tot' }}</div>
            {{ optional($quote->valid_until)->format('d-m-Y') }}
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th style="width:52%">Omschrijving</th>
        <th class="num" style="width:14%">Aantal</th>
        <th class="num" style="width:17%">Prijs</th>
        <th class="num" style="width:17%">Bedrag</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
            <td class="num">€ {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }}</td>
            <td class="num">€ {{ number_format($item->line_total_cents / 100, 2, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Subtotaal excl. btw</td>
        <td class="num">€ {{ number_format($quote->subtotal_cents / 100, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Btw {{ rtrim(rtrim(number_format((float) $quote->vat_rate, 1, ',', '.'), '0'), ',') }}%</td>
        <td class="num">€ {{ number_format($quote->vat_cents / 100, 2, ',', '.') }}</td>
    </tr>
    <tr class="grand">
        <td>Totaal incl. btw</td>
        <td class="num">€ {{ number_format($quote->total_cents / 100, 2, ',', '.') }}</td>
    </tr>
</table>

<div class="block">
    <h2>Uitvoering</h2>
    <ul>
        <li>Systeem: {{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ number_format((float) $quote->total_kw, 1, ',', '.') }} kW</li>
        <li>Geschatte montageduur op locatie: {{ number_format($quote->onsite_minutes / 60, 1, ',', '.') }} uur</li>
        <li>Inclusief inbedrijfstelling, drukproef, vacumeren en uitleg van de bediening</li>
    </ul>
</div>

@if (! empty($quote->assumptions))
    <div class="block">
        <h2>Aannames</h2>
        <ul>
            @foreach ($quote->assumptions as $assumption)
                <li>{{ $assumption }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($quote->isBinding())
    <div class="block">
        <h2>Wat u hieraan heeft</h2>
        <ul>
            <li>Dit is een aanbod: gaat u akkoord, dan geldt dit bedrag voor het beschreven werk.</li>
            <li>De prijs is vastgesteld na de opname ter plaatse; er komen geen kosten achteraf bij voor wat hierin staat.</li>
            <li>Meerwerk waar u zelf om vraagt, stemmen we vooraf met u af.</li>
        </ul>
    </div>

    <div class="footer">
        Deze offerte is opgesteld na een opname ter plaatse en geldt tot de hierboven
        genoemde datum. Wilt u iets wijzigen aan de opstelling of de uitvoering, dan
        maken we een aangepaste offerte voordat het werk begint.
    </div>
@else
    <div class="block">
        <h2>Wat dit wel en niet is</h2>
        <ul>
            <li>Dit is een <strong>prijsindicatie</strong> op basis van de gegevens die u ons heeft gegeven, geen offerte.</li>
            <li>Aan dit bedrag kunnen geen rechten worden ontleend.</li>
            <li>We komen eerst langs voor een opname: leidinglengte, plek van de buitenunit, doorvoeren en de elektragroep.</li>
            <li>Direct daarna krijgt u de offerte met de definitieve prijs. Die is bindend, deze indicatie niet.</li>
        </ul>
    </div>

    <div class="footer">
        Deze prijsindicatie is opgesteld op basis van de door u verstrekte gegevens en
        is vrijblijvend. Wijkt de situatie op locatie af — in leidinglengte,
        bereikbaarheid van de gevel of de beschikbare elektragroep — dan ziet u dat
        terug in de offerte die na de opname volgt.
    </div>
@endif
</body>
</html>
