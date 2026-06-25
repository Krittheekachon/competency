@component('mail::message')
# {{ $title }}

{{ $body }}

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
{{ $buttonText }}
@endcomponent
@endcomponent
