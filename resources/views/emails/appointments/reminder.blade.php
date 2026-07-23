<x-mail::message>
# Te esperamos pronto

Hola {{ $appointment->customer->name }},

Te recordamos tu turno en **{{ $appointment->company->name }}**:

<x-mail::panel>
**{{ $appointment->service->name }}** con {{ $appointment->employee->name }}<br>
📅 {{ $appointment->starts_at->setTimezone($tz)->isoFormat('dddd D [de] MMMM [a las] HH:mm') }} hs<br>
📍 {{ $appointment->branch->name }}@if($appointment->branch->address) — {{ $appointment->branch->address }}@endif
</x-mail::panel>

<x-mail::button :url="$manageUrl">
Ver o cancelar mi turno
</x-mail::button>

Si no podés asistir, avisanos cancelando desde el botón.

¡Nos vemos!<br>
{{ $appointment->company->name }}
</x-mail::message>
