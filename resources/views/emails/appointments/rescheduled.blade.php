<x-mail::message>
# Tu turno fue reprogramado

Hola {{ $appointment->customer->name }},

Tu turno en **{{ $appointment->company->name }}** tiene nuevo horario:

<x-mail::panel>
**{{ $appointment->service->name }}** con {{ $appointment->employee->name }}<br>
@if($original)
~~{{ $original->starts_at->setTimezone($tz)->isoFormat('dddd D [de] MMMM, HH:mm') }} hs~~<br>
@endif
📅 **{{ $appointment->starts_at->setTimezone($tz)->isoFormat('dddd D [de] MMMM [a las] HH:mm') }} hs**<br>
📍 {{ $appointment->branch->name }}
</x-mail::panel>

<x-mail::button :url="$manageUrl">
Ver o cancelar mi turno
</x-mail::button>

Gracias,<br>
{{ $appointment->company->name }}
</x-mail::message>
