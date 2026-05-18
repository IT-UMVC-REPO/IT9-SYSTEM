<x-mail::message>
# {{ __('New contact message') }}

**{{ __('Name') }}:** {{ $contactMessage->name }}  
**{{ __('Email') }}:** {{ $contactMessage->email }}  
**{{ __('Phone') }}:** {{ $contactMessage->phone ?: __('Not provided') }}  
**{{ __('Subject') }}:** {{ $contactMessage->subjectLabel() }}  
**{{ __('IP Address') }}:** {{ $contactMessage->ip_address ?: __('Unknown') }}

{{ __('Message') }}

{{ $contactMessage->message }}

@if ($contactMessage->attachment_path)
{{ __('Attachment path: :path', ['path' => $contactMessage->attachment_path]) }}
@endif
</x-mail::message>
