@php use App\Support\Money; @endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Offerte {{ $quote->number }}</title>
    <style>
        /* Briefpapier: kop en voet staan vast, de inhoud loopt ertussen door.
           Maten in px bij 96 dpi; 13px is ongeveer 9,75pt op papier. */
        @page { margin: 92px 56px 66px; }

        * { box-sizing: border-box; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 13px;
            line-height: 1.55;
            color: #1b1b1b;
            margin: 0;
            padding: 0;
        }

        .num { text-align: right; white-space: nowrap; }

        /* --- Briefhoofd --- */
        #letterhead { position: fixed; top: -78px; left: 0; right: 0; height: 76px; }
        #letterhead table { width: 100%; border-collapse: collapse; }
        #letterhead td { vertical-align: top; padding: 0; }
        .wordmark { font-size: 21px; font-weight: bold; letter-spacing: -0.4px; color: #0a0a0a; }
        .contact { font-size: 10.5px; line-height: 1.5; color: #6b6b6b; text-align: right; }

        /* Merkstreep: van koel naar warm, in vaste stappen zodat elke
           pdf-lezer hem hetzelfde tekent. */
        .rule { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .rule td { height: 3px; line-height: 3px; font-size: 0; padding: 0; }

        /* --- Voettekst --- */
        #footer { position: fixed; bottom: -54px; left: 0; right: 0; height: 46px; font-size: 9.5px; color: #9a9a9a; }
        #footer table { width: 100%; border-collapse: collapse; border-top: 1px solid #ececec; }
        #footer td { padding-top: 8px; vertical-align: top; }
        /* Alleen het paginanummer: dompdf kent counter(pages) niet en zou
           daar "van 0" van maken. */
        .pagenum:after { content: counter(page); }

        /* --- Documentkop --- */
        h1 { font-size: 25px; line-height: 1.1; font-weight: bold; letter-spacing: -0.6px; margin: 0 0 2px; color: #0a0a0a; }
        .subtitle { font-size: 12.5px; color: #6b6b6b; margin: 0 0 16px; }

        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        table.meta td { vertical-align: top; padding: 0; }
        .label { font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #9a9a9a; padding-bottom: 4px; }
        .meta-value { font-size: 13px; line-height: 1.5; }
        .meta-value strong { color: #0a0a0a; }
        table.dates { border-collapse: collapse; float: right; }
        table.dates td { padding: 0 0 3px 18px; text-align: right; vertical-align: baseline; white-space: nowrap; }
        table.dates td.key { font-size: 11px; color: #9a9a9a; }

        /* --- Specificatie --- */
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th {
            text-align: left; padding: 0 6px 7px 0; border-bottom: 1.2px solid #0a0a0a;
            font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #6b6b6b;
        }
        table.lines th.num, table.lines td.num { text-align: right; padding-right: 0; padding-left: 6px; }
        table.lines td { padding: 4px 6px 4px 0; border-bottom: 1px solid #ececec; font-size: 12.5px; vertical-align: top; }
        table.lines tr.credit td { color: #6b6b6b; }

        table.totals { width: 47%; margin-left: 53%; border-collapse: collapse; margin-top: 9px; }
        table.totals td { padding: 3px 0; font-size: 12.5px; }
        table.totals td.key { color: #6b6b6b; }
        table.totals tr.grand td { border-top: 1.2px solid #0a0a0a; padding-top: 9px; font-size: 14px; font-weight: bold; color: #0a0a0a; }

        /* --- Blokken --- */
        .blocks { width: 100%; border-collapse: collapse; margin-top: 18px; page-break-inside: avoid; }
        .blocks td { vertical-align: top; padding: 0 26px 0 0; }
        .blocks td.last { padding-right: 0; }
        .block h2 { font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #9a9a9a; margin: 0 0 7px; }
        ul { margin: 0; padding-left: 15px; }
        li { margin-bottom: 3px; font-size: 12.5px; line-height: 1.5; }

        .note {
            margin-top: 18px; padding: 12px 15px; background-color: #fafafa;
            border: 1px solid #ececec; border-radius: 6px;
            font-size: 11px; line-height: 1.55; color: #6b6b6b; page-break-inside: avoid;
        }
    </style>
</head>
<body>

<div id="letterhead">
    <table>
        <tr>
            <td class="wordmark">{{ $company['name'] }}</td>
            <td class="contact">
                @if ($company['address_line']){{ $company['address_line'] }}<br>@endif
                @if ($company['phone']){{ $company['phone'] }}@endif
                @if ($company['phone'] && $company['email']) &nbsp;·&nbsp; @endif
                @if ($company['email']){{ $company['email'] }}@endif
                @if ($company['website_label']) &nbsp;·&nbsp; {{ $company['website_label'] }}@endif
            </td>
        </tr>
    </table>
    <table class="rule">
        <tr>
            @foreach (['#4aa8ff', '#5da4f0', '#71a0e2', '#849cd4', '#9898c6', '#ab94b8', '#bf90aa', '#d28c9c', '#e6888e', '#f28580', '#f78a5f', '#ff8a3d'] as $step)
                <td style="background-color: {{ $step }}">&nbsp;</td>
            @endforeach
        </tr>
    </table>
</div>

<div id="footer">
    <table>
        <tr>
            <td>{{ $company['legal_line'] }}</td>
            <td class="num">Offerte {{ $quote->number }} &nbsp;·&nbsp; pagina <span class="pagenum"></span></td>
        </tr>
    </table>
</div>

<h1>Offerte</h1>
<p class="subtitle">Airconditioning, geleverd en gemonteerd</p>

<table class="meta">
    <tr>
        <td width="55%">
            <div class="label">Voor</div>
            <div class="meta-value">
                <strong>{{ $lead->name }}</strong><br>
                @if ($lead->address){{ $lead->address }}<br>@endif
                {{ trim($lead->postcode.' '.$lead->city) }}
            </div>
        </td>
        <td>
            <table class="dates">
                <tr>
                    <td class="key">Offertenummer</td>
                    <td class="meta-value"><strong>{{ $quote->number }}</strong></td>
                </tr>
                <tr>
                    <td class="key">Datum</td>
                    <td class="meta-value">{{ $quote->created_at->translatedFormat('j F Y') }}</td>
                </tr>
                <tr>
                    <td class="key">Geldig tot</td>
                    <td class="meta-value">{{ optional($quote->valid_until)->translatedFormat('j F Y') ?? '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th style="width:50%">Omschrijving</th>
        <th class="num" style="width:14%">Aantal</th>
        <th class="num" style="width:18%">Prijs</th>
        <th class="num" style="width:18%">Bedrag</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($items as $item)
        <tr @class(['credit' => $item->line_total_cents < 0])>
            <td>{{ $item->description }}</td>
            <td class="num">{{ Money::quantity((float) $item->quantity) }} {{ $item->unit }}</td>
            <td class="num">{{ Money::euro($item->unit_price_cents) }}</td>
            <td class="num">{{ Money::euro($item->line_total_cents) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="key">Subtotaal excl. btw</td>
        <td class="num">{{ Money::euro($quote->subtotal_cents) }}</td>
    </tr>
    <tr>
        <td class="key">Btw {{ Money::percentage((float) $quote->vat_rate) }}</td>
        <td class="num">{{ Money::euro($quote->vat_cents) }}</td>
    </tr>
    <tr class="grand">
        <td>Totaal incl. btw</td>
        <td class="num">{{ Money::euro($quote->total_cents) }}</td>
    </tr>
</table>

<table class="blocks">
    <tr>
        <td style="width:50%" @class(['last' => empty($quote->assumptions)])>
            <div class="block">
                <h2>Uitvoering</h2>
                <ul>
                    <li>Systeem: {{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ Money::kilowatt($quote->total_kw) }}</li>
                    <li>Geschatte montageduur op locatie: {{ Money::hours($quote->onsite_minutes) }}</li>
                    <li>Inclusief inbedrijfstelling, drukproef, vacumeren en uitleg van de bediening</li>
                </ul>
            </div>
        </td>
        @if (! empty($quote->assumptions))
            <td class="last">
                <div class="block">
                    <h2>Aannames</h2>
                    <ul>
                        @foreach ($quote->assumptions as $assumption)
                            <li>{{ $assumption }}</li>
                        @endforeach
                    </ul>
                </div>
            </td>
        @endif
    </tr>
</table>

<div class="note">
    Deze offerte is opgesteld op basis van de door u verstrekte gegevens. Wijkt de situatie op
    locatie af — bijvoorbeeld in leidinglengte, bereikbaarheid van de gevel of de beschikbare
    elektragroep — dan stemmen we een aangepaste prijs met u af voordat het werk begint.
</div>

</body>
</html>
