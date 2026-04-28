@component('mail::message')
# Welcome to SukiMarket, {{ $user->name }}!

Thank you for creating an account. Please click the button below to verify your email address and activate your account.

@component('mail::button', ['url' => $verificationUrl])
Verify my email
@endcomponent

This link expires in 60 minutes. If you did not create an account, you can safely ignore this email.

@if ($verificationCode)
If you'd rather verify from the page already open, you can also enter this 6-digit code: **{{ $verificationCode }}**. That code stays valid for 10 minutes.
@endif

The SukiMarket Team
@endcomponent
