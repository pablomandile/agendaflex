<x-mail::message>
# Tu turno fue cancelado

Hola {{ $appointment->customer->name }},

Tu turno de **{{ $appointment->service->name }}** del
{{ $appointment->starts_at->setTimezone($tz)->isoFormat('dddd D [de] MMMM [a las] HH:mm') }} hs
en **{{ $appointment->company->name }}** fue cancelado.

Si querés reservar un nuevo horario, podés hacerlo desde el sitio del negocio.

Gracias,<br>
{{ $appointment->company->name }}
</x-mail::message>
