@props(['href', 'variant' => 'primary'])
@php
    $background = $variant === 'primary' ? '#0a0a0a' : '#ffffff';
    $color = $variant === 'primary' ? '#ffffff' : '#0a0a0a';
    $border = $variant === 'primary' ? '#0a0a0a' : '#e0e0e0';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 20px;">
    <tr>
        <td align="center" style="background-color:{{ $background }}; border:1px solid {{ $border }}; border-radius:8px;">
            <a href="{{ $href }}" style="display:inline-block; padding:14px 26px; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:15px; font-weight:500; line-height:20px; color:{{ $color }}; text-decoration:none;">{{ $slot }}</a>
        </td>
    </tr>
</table>
