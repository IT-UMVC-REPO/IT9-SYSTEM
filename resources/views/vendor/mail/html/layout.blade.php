<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap">
{!! $head ?? '' !!}
</head>
@php
    $parsedSlot = (string) Illuminate\Mail\Markdown::parse($slot);
    $styledSlot = preg_replace('/<a(?![^>]*style=)/i', '<a style="color:#059669;text-decoration:underline;"', $parsedSlot) ?? $parsedSlot;

    $styledSubcopy = null;

    if (isset($subcopy)) {
        $parsedSubcopy = (string) Illuminate\Mail\Markdown::parse($subcopy);
        $styledSubcopy = preg_replace('/<a(?![^>]*style=)/i', '<a style="color:#059669;text-decoration:underline;"', $parsedSubcopy) ?? $parsedSubcopy;
    }
@endphp
<body style="margin:0; padding:0; width:100%; background-color:#fbf7f2;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin:0; padding:32px 16px; background-color:#fbf7f2;">
<tr>
<td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; margin:0 auto;">
<tr>
<td style="padding:0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 18px 45px rgba(15,23,42,0.08);">
<tr>
<td align="center" style="padding:32px 24px 28px; background-color:#0a0a0a;">
<div style="margin:0; font-family:'Playfair Display', Georgia, serif; font-size:32px; line-height:1.1; font-weight:700; color:#ffffff;">SukiMarket</div>
<div style="margin-top:8px; font-family:'DM Sans', -apple-system, Arial, sans-serif; font-size:12px; line-height:1.5; letter-spacing:0.12em; text-transform:uppercase; color:#a1a1aa;">Digital palengke</div>
</td>
</tr>
<tr>
<td style="padding:40px; font-family:'DM Sans', -apple-system, Arial, sans-serif; font-size:16px; line-height:1.6; color:#374151;">
{!! $styledSlot !!}

@if ($styledSubcopy)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin-top:28px;">
<tr>
<td style="padding-top:24px; border-top:1px solid #e5e7eb; font-family:'DM Sans', -apple-system, Arial, sans-serif; font-size:13px; line-height:1.6; color:#6b7280;">
{!! $styledSubcopy !!}
</td>
</tr>
</table>
@endif
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td align="center" style="padding:20px 24px 0; background-color:#fbf7f2; font-family:'DM Sans', -apple-system, Arial, sans-serif; font-size:12px; line-height:1.5; color:#9ca3af;">
&copy; 2025 SukiMarket &middot; Digital Palengke &middot; Philippines
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
