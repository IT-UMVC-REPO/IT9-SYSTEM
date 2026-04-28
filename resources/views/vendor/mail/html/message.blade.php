<x-mail::layout>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin:0 0 24px;">
<tr>
<td style="padding:0 0 24px;">
<span style="display:inline-block; padding:8px 14px; border-radius:999px; background-color:#ecfdf5; color:#047857; font-family:'DM Sans', -apple-system, Arial, sans-serif; font-size:12px; line-height:1; font-weight:700; letter-spacing:0.08em; text-transform:uppercase;">
Fresh from your digital palengke
</span>
</td>
</tr>
</table>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
{!! $subcopy !!}
</x-slot:subcopy>
@endisset
</x-mail::layout>
