@props(['label'])
<tr>
    <td width="42%" valign="top" style="padding:4px 12px 4px 0; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:14px; line-height:1.5; color:#6b6b6b;">{{ $label }}</td>
    <td valign="top" style="padding:4px 0; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:14px; line-height:1.5; color:#0a0a0a; font-weight:500;">{{ $slot }}</td>
</tr>
