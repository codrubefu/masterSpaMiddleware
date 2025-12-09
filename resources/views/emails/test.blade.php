@component('mail::message')
# Test Email

Hello,

This is a test email from **Master Spa Middleware**.

**Message:** {{ $message }}

**Sent at:** {{ $timestamp->format('Y-m-d H:i:s') }}

@component('mail::button', ['url' => config('app.url')])
Visit Application
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
