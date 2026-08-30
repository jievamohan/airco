@props(['muted' => false, 'small' => false])
<p style="margin:0 0 14px; font-family:'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif; font-size:{{ $small ? '13px' : '15px' }}; line-height:1.65; color:{{ $muted ? '#6b6b6b' : '#2f2f2f' }};">{{ $slot }}</p>
