<x-mail::message>
# ¡Se liberó un lugar!

Hola {{ $entry->customer->name }},

Estabas en la lista de espera de **{{ $entry->service->name }}**
en **{{ $entry->company->name }}** y se liberó un horario dentro del rango
que pediste ({{ $entry->desired_from->setTimezone($tz)->isoFormat('D/M HH:mm') }}
— {{ $entry->desired_to->setTimezone($tz)->isoFormat('D/M HH:mm') }} hs).

Reservalo cuanto antes desde el sitio del negocio: los lugares se asignan
por orden de llegada.

Gracias,<br>
{{ $entry->company->name }}
</x-mail::message>
