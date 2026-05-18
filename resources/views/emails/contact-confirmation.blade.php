<x-mail::message>
# {{ __('We received your message') }}

{{ __('Hi :name,', ['name' => $contactMessage->name]) }}

{{ __('Thank you for contacting Sukimarket. Our team received your message about ":subject" and will respond within 1-2 business days.', ['subject' => $contactMessage->subjectLabel()]) }}

{{ __('For urgent order concerns, please keep your order details ready so our support team can help faster.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
