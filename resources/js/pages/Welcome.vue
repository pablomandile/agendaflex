<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BriefcaseBusiness,
    Building2,
    Car,
    ChartColumn,
    Check,
    Clock,
    Code,
    Globe,
    Mail,
    PawPrint,
    Puzzle,
    Scissors,
    Sparkles,
    Stethoscope,
} from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const verticales = [
    { icon: Scissors, nombre: 'Peluquerías y barberías' },
    { icon: Sparkles, nombre: 'Estética y uñas' },
    { icon: Stethoscope, nombre: 'Salud y consultorios' },
    { icon: BriefcaseBusiness, nombre: 'Servicios profesionales' },
    { icon: PawPrint, nombre: 'Veterinarias y peluquería canina' },
    { icon: Car, nombre: 'Talleres y lavaderos' },
];

const pasos = [
    {
        numero: '01',
        titulo: 'Cargá tu negocio',
        texto: 'Servicios, duración, precios, empleados, sucursales y horarios de atención.',
    },
    {
        numero: '02',
        titulo: 'Insertá el widget en tu web',
        texto: 'Una línea de código en tu sitio. Tus clientes reservan sin salir de tu dominio.',
    },
    {
        numero: '03',
        titulo: 'Gestioná desde el calendario',
        texto: 'Turnos, reprogramaciones, bloqueos y recordatorios automáticos por email.',
    },
];

const features = [
    {
        icon: Puzzle,
        titulo: 'Widget embebible',
        texto: 'Se integra en tu sitio con tu marca y tu dominio. Tus clientes nunca se van a otra plataforma.',
    },
    {
        icon: Code,
        titulo: 'API REST pública',
        texto: 'Arquitectura headless: conectá tu app, tu web a medida o el sistema que ya usás.',
    },
    {
        icon: Building2,
        titulo: 'Multi-sucursal',
        texto: 'Varias sedes, cada una con sus empleados, servicios, recursos y zona horaria.',
    },
    {
        icon: Clock,
        titulo: 'Disponibilidad real',
        texto: 'El motor cruza horarios, vacaciones, bloqueos y recursos para no vender un turno imposible.',
    },
    {
        icon: Mail,
        titulo: 'Recordatorios por email',
        texto: 'Confirmación, recordatorio y aviso de cambios. Menos ausencias sin avisar.',
    },
    {
        icon: ChartColumn,
        titulo: 'Reportes del negocio',
        texto: 'Ingresos, ocupación, servicios más pedidos y tasa de cancelación.',
    },
];

const capacidades = [
    'Multi-idioma',
    'Multi-moneda',
    'Multi-zona horaria',
    'White-label',
];

const embedSnippet = `<div id="agendaflex"></div>
<script src="https://agendaflex.com.ar/widget/agendaflex-widget.js"><\/script>
<script>
  Agendaflex.mount('#agendaflex', {
    tenant: 'tu-negocio',
    publicKey: 'pk_...',
  });
<\/script>`;
</script>

<template>
    <Head title="AgendaFlex — Turnos online para tu negocio" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Nav -->
        <header
            class="sticky top-0 z-50 border-b border-border/60 bg-background/80 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6"
            >
                <Link :href="'/'" class="flex items-center gap-2">
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-indigo-600"
                    >
                        <AppLogoIcon class="size-5 fill-current text-white" />
                    </div>
                    <span class="text-lg font-semibold tracking-tight"
                        >AgendaFlex</span
                    >
                </Link>

                <nav class="hidden items-center gap-8 text-sm md:flex">
                    <a
                        href="#como-funciona"
                        class="text-muted-foreground transition-colors hover:text-foreground"
                        >Cómo funciona</a
                    >
                    <a
                        href="#funciones"
                        class="text-muted-foreground transition-colors hover:text-foreground"
                        >Funciones</a
                    >
                    <a
                        href="#integracion"
                        class="text-muted-foreground transition-colors hover:text-foreground"
                        >Integración</a
                    >
                </nav>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                    >
                        Ir al panel
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                        >
                            Crear cuenta
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section class="relative overflow-hidden">
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,rgba(99,102,241,0.13),transparent)]"
                />

                <div
                    class="relative mx-auto grid max-w-6xl gap-14 px-6 pt-16 pb-20 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:pt-24 lg:pb-28"
                >
                    <div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:border-indigo-500/25 dark:bg-indigo-500/10 dark:text-indigo-300"
                        >
                            <Sparkles class="size-3.5" />
                            Reservas online para cualquier rubro
                        </span>

                        <h1
                            class="mt-6 text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                        >
                            Tu agenda, en tu web,
                            <span class="text-indigo-600 dark:text-indigo-400"
                                >abierta 24 horas</span
                            >
                        </h1>

                        <p
                            class="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground"
                        >
                            AgendaFlex es el motor de turnos que se integra a tu
                            sitio. Tus clientes reservan solos, vos dejás de
                            contestar mensajes para coordinar horarios.
                        </p>

                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-7 py-3.5 text-base font-medium text-white transition-colors hover:bg-indigo-700"
                            >
                                Empezar gratis
                                <ArrowRight class="size-4" />
                            </Link>
                            <a
                                href="#como-funciona"
                                class="inline-flex items-center justify-center rounded-full border border-border px-7 py-3.5 text-base font-medium transition-colors hover:bg-muted"
                            >
                                Ver cómo funciona
                            </a>
                        </div>

                        <ul
                            class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
                        >
                            <li
                                v-for="cap in capacidades"
                                :key="cap"
                                class="flex items-center gap-1.5"
                            >
                                <Check
                                    class="size-4 text-indigo-600 dark:text-indigo-400"
                                />
                                {{ cap }}
                            </li>
                        </ul>
                    </div>

                    <!-- Vista previa del widget -->
                    <div class="relative">
                        <div
                            class="absolute -inset-4 rounded-4xl bg-linear-to-tr from-indigo-500/15 via-transparent to-indigo-400/10 blur-2xl"
                        />
                        <div
                            class="relative rounded-2xl border border-border bg-card p-6 shadow-xl shadow-indigo-950/5"
                        >
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">
                                    Reservar turno
                                </p>
                                <span class="text-xs text-muted-foreground"
                                    >Estudio Norte</span
                                >
                            </div>

                            <div class="mt-5 space-y-4">
                                <div>
                                    <p
                                        class="mb-1.5 text-xs text-muted-foreground"
                                    >
                                        Servicio
                                    </p>
                                    <div
                                        class="flex items-center justify-between rounded-lg border border-border px-3 py-2.5 text-sm"
                                    >
                                        <span>Corte de pelo</span>
                                        <span class="text-muted-foreground"
                                            >45 min</span
                                        >
                                    </div>
                                </div>

                                <div>
                                    <p
                                        class="mb-1.5 text-xs text-muted-foreground"
                                    >
                                        Horarios disponibles
                                    </p>
                                    <div class="grid grid-cols-4 gap-2">
                                        <span
                                            class="rounded-lg border border-border py-2 text-center text-sm text-muted-foreground"
                                            >09:00</span
                                        >
                                        <span
                                            class="rounded-lg border border-indigo-600 bg-indigo-600 py-2 text-center text-sm font-medium text-white"
                                            >09:45</span
                                        >
                                        <span
                                            class="rounded-lg border border-border py-2 text-center text-sm text-muted-foreground"
                                            >10:30</span
                                        >
                                        <span
                                            class="rounded-lg border border-border py-2 text-center text-sm text-muted-foreground"
                                            >11:15</span
                                        >
                                    </div>
                                </div>

                                <div
                                    class="rounded-lg bg-indigo-600 py-2.5 text-center text-sm font-medium text-white"
                                >
                                    Confirmar reserva
                                </div>
                            </div>
                        </div>

                        <div
                            class="absolute -right-2 -bottom-8 flex items-center gap-2 rounded-xl border border-border bg-card px-3.5 py-2.5 shadow-lg sm:-right-5"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/15"
                            >
                                <Check
                                    class="size-4 text-emerald-600 dark:text-emerald-400"
                                />
                            </span>
                            <div class="text-xs leading-tight">
                                <p class="font-medium">Turno confirmado</p>
                                <p class="text-muted-foreground">
                                    Te llegó un email
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Verticales -->
            <section class="border-y border-border bg-muted/40">
                <div class="mx-auto max-w-6xl px-6 py-16">
                    <h2
                        class="text-center text-2xl font-semibold tracking-tight"
                    >
                        Un motor, cualquier rubro
                    </h2>
                    <p
                        class="mx-auto mt-3 max-w-2xl text-center text-muted-foreground"
                    >
                        La lógica de turnos es la misma en todos lados:
                        clientes, servicios, personas y disponibilidad.
                    </p>

                    <div
                        class="mt-10 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6"
                    >
                        <div
                            v-for="v in verticales"
                            :key="v.nombre"
                            class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-4 py-6 text-center transition-colors hover:border-indigo-300 dark:hover:border-indigo-500/40"
                        >
                            <component
                                :is="v.icon"
                                class="size-6 text-indigo-600 dark:text-indigo-400"
                            />
                            <span class="text-sm leading-snug">{{
                                v.nombre
                            }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Cómo funciona -->
            <section id="como-funciona" class="mx-auto max-w-6xl px-6 py-20">
                <h2
                    class="text-center text-3xl font-semibold tracking-tight text-balance"
                >
                    Andando en tres pasos
                </h2>

                <div class="mt-14 grid gap-10 md:grid-cols-3">
                    <div v-for="paso in pasos" :key="paso.numero">
                        <span
                            class="text-sm font-semibold text-indigo-600 dark:text-indigo-400"
                            >{{ paso.numero }}</span
                        >
                        <h3 class="mt-3 text-lg font-medium">
                            {{ paso.titulo }}
                        </h3>
                        <p class="mt-2 leading-relaxed text-muted-foreground">
                            {{ paso.texto }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Funciones -->
            <section id="funciones" class="border-t border-border bg-muted/40">
                <div class="mx-auto max-w-6xl px-6 py-20">
                    <h2
                        class="text-center text-3xl font-semibold tracking-tight text-balance"
                    >
                        Todo lo que necesita una agenda seria
                    </h2>

                    <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="f in features"
                            :key="f.titulo"
                            class="rounded-2xl border border-border bg-card p-6"
                        >
                            <span
                                class="flex size-10 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-500/10"
                            >
                                <component
                                    :is="f.icon"
                                    class="size-5 text-indigo-600 dark:text-indigo-400"
                                />
                            </span>
                            <h3 class="mt-4 font-medium">{{ f.titulo }}</h3>
                            <p
                                class="mt-2 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ f.texto }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Integración -->
            <section id="integracion" class="mx-auto max-w-6xl px-6 py-20">
                <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                    <div>
                        <span
                            class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400"
                        >
                            <Globe class="size-4" />
                            En tu dominio, con tu marca
                        </span>
                        <h2
                            class="mt-4 text-3xl font-semibold tracking-tight text-balance"
                        >
                            Se integra con una línea de código
                        </h2>
                        <p class="mt-4 leading-relaxed text-muted-foreground">
                            Pegá el snippet donde quieras que aparezca el
                            calendario de reservas. No hay redirecciones ni
                            páginas de terceros: el cliente reserva sin salir de
                            tu sitio.
                        </p>
                        <p class="mt-4 leading-relaxed text-muted-foreground">
                            Si preferís armar tu propia experiencia, consumí la
                            API REST directamente.
                        </p>
                    </div>

                    <div
                        class="overflow-hidden rounded-2xl border border-border bg-neutral-950"
                    >
                        <div
                            class="flex items-center gap-1.5 border-b border-white/10 px-4 py-3"
                        >
                            <span class="size-2.5 rounded-full bg-red-400/70" />
                            <span
                                class="size-2.5 rounded-full bg-amber-400/70"
                            />
                            <span
                                class="size-2.5 rounded-full bg-emerald-400/70"
                            />
                        </div>
                        <pre
                            class="p-5 text-[13px] leading-relaxed wrap-break-word whitespace-pre-wrap text-neutral-200"
                        ><code>{{ embedSnippet }}</code></pre>
                    </div>
                </div>
            </section>

            <!-- CTA final -->
            <section class="mx-auto max-w-6xl px-6 pb-24">
                <div
                    class="rounded-3xl bg-indigo-600 px-8 py-16 text-center text-white"
                >
                    <h2
                        class="text-3xl font-semibold tracking-tight text-balance"
                    >
                        Empezá a recibir turnos hoy
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-indigo-100">
                        Configurás tu negocio en minutos y el widget queda
                        andando en tu web.
                    </p>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="mt-8 inline-flex items-center gap-2 rounded-full bg-white px-7 py-3.5 text-base font-medium text-indigo-700 transition-colors hover:bg-indigo-50"
                    >
                        Crear mi cuenta
                        <ArrowRight class="size-4" />
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="mt-8 inline-flex items-center gap-2 rounded-full bg-white px-7 py-3.5 text-base font-medium text-indigo-700 transition-colors hover:bg-indigo-50"
                    >
                        Iniciar sesión
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </section>
        </main>

        <footer class="border-t border-border">
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-10 sm:flex-row"
            >
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-indigo-600"
                    >
                        <AppLogoIcon class="size-4 fill-current text-white" />
                    </div>
                    <span class="text-sm font-medium">AgendaFlex</span>
                </div>
                <p class="text-sm text-muted-foreground">
                    © {{ new Date().getFullYear() }} AgendaFlex
                </p>
            </div>
        </footer>
    </div>
</template>
