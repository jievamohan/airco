@props(['title' => null])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px; background-color:#fafafa; border:1px solid #ececec; border-radius:8px;">
    <tr>
        <td style="padding:18px 20px; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif;">
            @if ($title)
                <p style="margin:0 0 12px; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#9a9a9a;">{{ $title }}</p>
            @endif
            {{ $slot }}
        </td>
    </tr>
</table>
