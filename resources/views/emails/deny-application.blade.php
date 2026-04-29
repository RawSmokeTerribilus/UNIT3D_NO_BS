@component('mail::message')
# Your {{ config('other.title') }} application
Your application has been denied for the following reason:
{{ $deniedMessage }}
Thanks,
{{ config('other.title') }}
@endcomponent
