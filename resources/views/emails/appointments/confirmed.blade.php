<x-mail::message>
# ¡Tu turno está confirmado!

Hola {{ $appointment->customer->name }},

Te confirmamos tu turno en **{{ $appointment->company->name }}**:

<x-mail::panel>
**{{ $appointment->service->name }}** con {{ $appointment->employee->name }}<br>
📅 {{ $appointment->starts_at->setTimezone($tz)->isoFormat('dddd D [de] MMMM [a las] HH:mm') }} hs<br>
📍 {{ $appointment->branch->name }}@if($appointment->branch->address) — {{ $appointment->branch->address }}@endif
</x-mail::panel>

<x-mail::button :url="$manageUrl">
Ver o cancelar mi turno
</x-mail::button>

Si no podés asistir, cancelá con anticipación desde el botón de arriba.

Gracias,<br>
{{ $appointment->company->name }}
</x-mail::message>
