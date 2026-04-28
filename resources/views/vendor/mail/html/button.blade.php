@props([
    'url',
    'align' => 'center',
])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin:32px 0 24px;">
<tr>
<td align="{{ $align }}" style="text-align:{{ $align }};">
<a href="{{ $url }}" target="_blank" rel="noopener" style="display:inline-block; padding:14px 28px; border-radius:12px; background-color:#059669; color:#ffffff; text-decoration:none; font-family:'DM Sans', -apple-system, Arial, sans-serif; font-size:14px; line-height:1; font-weight:600; border:none;">
{!! $slot !!}
</a>
</td>
</tr>
</table>
