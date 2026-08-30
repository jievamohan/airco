@props(['items' => []])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    @foreach ($items as $item)
        <tr>
            <td width="16" valign="top" style="padding:3px 0; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:14px; line-height:1.6; color:#b8b8b8;">&bull;</td>
            <td valign="top" style="padding:3px 0; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:14px; line-height:1.6; color:#2f2f2f;">{{ $item }}</td>
        </tr>
    @endforeach
</table>
