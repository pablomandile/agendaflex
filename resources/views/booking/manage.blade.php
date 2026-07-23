<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu turno — {{ $appointment->company->name }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; margin: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: light-dark(#f8fafc, #0f172a);
            color: light-dark(#0f172a, #e2e8f0);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1rem;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            background: light-dark(#ffffff, #1e293b);
            border: 1px solid light-dark(#e2e8f0, #334155);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 30px rgb(0 0 0 / 0.08);
        }
        h1 { font-size: 1.15rem; margin-bottom: .25rem; }
        .muted { color: light-dark(#64748b, #94a3b8); font-size: .9rem; }
        dl { margin: 1.25rem 0; display: grid; gap: .6rem; font-size: .95rem; }
        dt { font-weight: 600; }
        dd { margin: 0; }
        .status {
            display: inline-block; padding: .2rem .6rem; border-radius: 999px;
            font-size: .8rem; font-weight: 600; text-transform: uppercase;
        }
        .status.confirmed { background: #dcfce7; color: #166534; }
        .status.cancelled { background: #fee2e2; color: #991b1b; }
        .status.completed, .status.pending, .status.no_show { background: #e2e8f0; color: #334155; }
        .flash {
            background: #dcfce7; color: #166534; border-radius: .5rem;
            padding: .75rem 1rem; font-size: .9rem; margin-bottom: 1rem;
        }
        button {
            width: 100%; padding: .7rem 1rem; border: 0; border-radius: .6rem;
            background: #dc2626; color: #fff; font-size: .95rem; font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <main class="card">
        @if (session('status'))
            <p class="flash">{{ session('status') }}</p>
        @endif

        <h1>{{ $appointment->company->name }}</h1>
        <p class="muted">Detalle de tu turno</p>

        <dl>
            <div>
                <dt>Estado</dt>
                <dd><span class="status {{ $appointment->status }}">{{ $appointment->status }}</span></dd>
            </div>
            <div>
                <dt>Servicio</dt>
                <dd>{{ $appointment->service->name }} con {{ $appointment->employee->name }}</dd>
            </div>
            <div>
                <dt>Fecha y hora</dt>
                <dd>{{ $appointment->starts_at->setTimezone($tz)->isoFormat('dddd D [de] MMMM, HH:mm') }} hs</dd>
            </div>
            <div>
                <dt>Sucursal</dt>
                <dd>{{ $appointment->branch->name }}@if($appointment->branch->address) — {{ $appointment->branch->address }}@endif</dd>
            </div>
            <div>
                <dt>Cliente</dt>
                <dd>{{ $appointment->customer->name }}</dd>
            </div>
        </dl>

        @if ($cancelUrl)
            <form method="POST" action="{{ $cancelUrl }}"
                  onsubmit="return confirm('¿Seguro que querés cancelar el turno?')">
                @csrf
                <button type="submit">Cancelar turno</button>
            </form>
            <p class="muted" style="margin-top:.75rem; text-align:center">
                ¿Necesitás otro horario? Cancelá y reservá de nuevo desde el sitio del negocio.
            </p>
        @endif
    </main>
</body>
</html>
