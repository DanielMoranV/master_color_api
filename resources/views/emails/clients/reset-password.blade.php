@component('mail::message')
# Hola {{ $name }}

Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para crear una nueva contraseña.

@component('mail::button', ['url' => $url])
Restablecer contraseña
@endcomponent

Este enlace expirará en 60 minutos.

Si no solicitaste restablecer tu contraseña, puedes ignorar este correo.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
