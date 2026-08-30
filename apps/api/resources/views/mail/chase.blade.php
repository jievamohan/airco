@php use App\Support\Money; @endphp
@php
    $headings = [
        'missed_call' => 'We hebben u geprobeerd te bellen',
        'quote_without_call' => 'Uw vrijblijvende prijsindicatie',
        'last_chance' => 'Laatste bericht over uw aanvraag',
    ];
    $preheaders = [
        'missed_call' => 'Laat weten wanneer het u schikt, dan bellen we op dat moment terug.',
        'quote_without_call' => 'Een eerste indicatie op basis van de gegevens uit uw aanvraag.',
        'last_chance' => 'We benaderen u hierna niet meer; uw gegevens blijven bewaard.',
    ];
    $heading = $headings[$variant] ?? 'Over uw aanvraag';
@endphp
<x-mail.layout :company="$company" :title="$heading" :preheader="$preheaders[$variant] ?? ''">
    <x-mail.heading>{{ $heading }}</x-mail.heading>

    <x-mail.text>Beste {{ $lead->name }},</x-mail.text>

    @if ($variant === 'missed_call')
        <x-mail.text>
            We probeerden u te bereiken over uw aanvraag voor airconditioning, maar kregen geen
            gehoor. Geen probleem — laat ons weten wanneer het u schikt, dan bellen we op dat
            moment terug. U kunt ook gewoon antwoorden op deze mail.
        </x-mail.text>

        @if (! empty($company['phone']))
            <x-mail.button :href="'tel:'.$company['phone_link']">Bel {{ $company['phone'] }}</x-mail.button>
        @endif

    @elseif ($variant === 'quote_without_call')
        <x-mail.text>
            Het is nog niet gelukt u telefonisch te spreken. Daarom sturen we alvast een indicatie
            op basis van de gegevens uit uw aanvraag.
        </x-mail.text>

        @if ($quote)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 18px; background-color:#0a0a0a; border-radius:8px;">
                <tr>
                    <td style="padding:22px 24px; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif;">
                        <p style="margin:0 0 4px; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#9a9a9a;">Indicatie inclusief btw en montage</p>
                        <p style="margin:0; font-size:30px; line-height:1.15; font-weight:600; letter-spacing:-0.02em; color:#ffffff;">{{ Money::euro($quote->total_cents) }}</p>
                    </td>
                </tr>
            </table>

            <x-mail.panel title="Waar deze indicatie op gebaseerd is">
                <x-mail.facts>
                    <x-mail.fact label="Systeem">{{ $quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split' }}, {{ Money::kilowatt($quote->total_kw) }}</x-mail.fact>
                    <x-mail.fact label="Montage op locatie">ongeveer {{ Money::hours($quote->onsite_minutes) }}</x-mail.fact>
                </x-mail.facts>
            </x-mail.panel>

            <x-mail.text muted small>
                Dit is een indicatie, nog geen offerte: u kunt er geen rechten aan ontlenen.
                Bij de opname ter plaatse kijken we naar de leidinglengte, de plek van de
                buitenunit en de elektra. Pas daarna sturen we de offerte, en die is bindend.
            </x-mail.text>
        @endif

        @if (! empty($company['phone']))
            <x-mail.button :href="'tel:'.$company['phone_link']">Bel {{ $company['phone'] }}</x-mail.button>
        @endif

    @else
        <x-mail.text>
            We hebben u een paar keer geprobeerd te bereiken over uw aanvraag. Omdat we u niet
            willen blijven benaderen, is dit ons laatste bericht.
        </x-mail.text>

        <x-mail.text>
            Wilt u er later toch op terugkomen? Stuur dan gerust een mail of bel ons; uw gegevens
            blijven bewaard.
        </x-mail.text>
    @endif
</x-mail.layout>
