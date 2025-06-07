@component('mail::message')
# Hola {{ $name }}

Gracias por registrarte. Por favor verifica tu correo electrónico haciendo clic en el siguiente botón.

@component('mail::button', ['url' => $url])
Verificar correo
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
