@props([
    'title' => '',
    'preheader' => '',
    'company' => [],
    'internal' => false,
])
<!DOCTYPE html>
<html lang="nl" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title }}</title>
    <style type="text/css">
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; }
        table { border-collapse: collapse; }
        img { border: 0; line-height: 100%; }
        a { color: #0a0a0a; }
        @media only screen and (max-width: 620px) {
            .kx-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .kx-title { font-size: 22px !important; }
            .kx-hide-sm { display: none !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f3;">
<div style="display:none; max-height:0; max-width:0; opacity:0; overflow:hidden; font-size:1px; line-height:1px; color:#f4f4f3;">{{ $preheader }}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f3;">
    <tr>
        <td align="center" style="padding:32px 12px;">
            {{-- Outlook op Windows rekent met Word en negeert max-width; die
                 krijgt daarom een eigen tabel van 600. Alle andere clients
                 krijgen een tabel die meekrimpt, anders staat er op een
                 telefoon 600 pixels in een venster van 375 en zoomt de mail
                 zichzelf tot onleesbaar uit. --}}
            <!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border:1px solid #e8e8e8; border-radius:10px;">

                {{-- Briefhoofd --}}
                <tr>
                    <td class="kx-pad" style="padding:26px 36px 20px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="left" style="font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:20px; font-weight:600; letter-spacing:-0.02em; color:#0a0a0a;">
                                    {{ $company['name'] ?? '' }}
                                </td>
                                @if (! empty($company['phone']))
                                    <td class="kx-hide-sm" align="right" style="font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:13px; color:#6b6b6b;">
                                        <a href="tel:{{ $company['phone_link'] ?? '' }}" style="color:#6b6b6b; text-decoration:none;">{{ $company['phone'] }}</a>
                                    </td>
                                @endif
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Merkstreep: koel naar warm, precies zoals op de site --}}
                <tr>
                    <td style="padding:0 36px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td height="3" style="height:3px; line-height:3px; font-size:0; background-color:#4aa8ff; background-image:linear-gradient(90deg,#4aa8ff 0%,#ff8a3d 100%); border-radius:2px;">&nbsp;</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Inhoud --}}
                <tr>
                    <td class="kx-pad" style="padding:30px 36px 8px; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:15px; line-height:1.65; color:#2f2f2f;">
                        {{ $slot }}
                    </td>
                </tr>

                {{-- Ondertekening --}}
                @unless ($internal)
                    <tr>
                        <td class="kx-pad" style="padding:12px 36px 30px; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:15px; line-height:1.65; color:#2f2f2f;">
                            <p style="margin:0;">Met vriendelijke groet,</p>
                            <p style="margin:2px 0 0; font-weight:600; color:#0a0a0a;">{{ $company['name'] ?? '' }}</p>
                            <p style="margin:10px 0 0; font-size:14px; color:#6b6b6b;">
                                @if (! empty($company['phone']))
                                    <a href="tel:{{ $company['phone_link'] ?? '' }}" style="color:#6b6b6b; text-decoration:none;">{{ $company['phone'] }}</a>
                                @endif
                                @if (! empty($company['phone']) && ! empty($company['email'])) &nbsp;·&nbsp; @endif
                                @if (! empty($company['email']))
                                    <a href="mailto:{{ $company['email'] }}" style="color:#6b6b6b; text-decoration:none;">{{ $company['email'] }}</a>
                                @endif
                            </p>
                        </td>
                    </tr>
                @endunless

            </table>
            <!--[if mso]></td></tr></table><![endif]-->

            {{-- Voettekst --}}
            <!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px;">
                <tr>
                    <td class="kx-pad" align="center" style="padding:18px 36px 0; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:12px; line-height:1.6; color:#9a9a9a;">
                        @if (! empty($company['legal_line']))
                            <p style="margin:0;">{{ $company['legal_line'] }}</p>
                        @endif
                        @if (! empty($company['website']))
                            <p style="margin:6px 0 0;">
                                <a href="{{ $company['website'] }}" style="color:#9a9a9a; text-decoration:underline;">{{ $company['website_label'] ?? $company['website'] }}</a>
                                @unless ($internal)
                                    &nbsp;·&nbsp;
                                    <a href="{{ rtrim($company['website'], '/') }}/privacy" style="color:#9a9a9a; text-decoration:underline;">Privacyverklaring</a>
                                    &nbsp;·&nbsp;
                                    <a href="{{ rtrim($company['website'], '/') }}/algemene-voorwaarden" style="color:#9a9a9a; text-decoration:underline;">Algemene voorwaarden</a>
                                @endunless
                            </p>
                        @endif
                    </td>
                </tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->

        </td>
    </tr>
</table>
</body>
</html>
