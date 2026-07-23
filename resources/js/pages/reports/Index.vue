<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface ServiceRow {
    name: string;
    count: number;
    revenue: number;
}

interface EmployeeRow {
    name: string;
    color: string;
    count: number;
    revenue: number;
    booked_minutes: number;
    occupancy: number | null;
}

const props = defineProps<{
    report: {
        totals: {
            appointments: number;
            billable: number;
            cancelled: number;
            no_show: number;
            revenue: number;
            unique_customers: number;
            cancellation_rate: number;
        };
        services: ServiceRow[];
        employees: EmployeeRow[];
    };
    filters: { from: string; to: string };
    currency: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reportes', href: '/reports' }];

// El DatePicker trabaja con Date locales; los filtros viajan como Y-m-d
const range = ref<[Date, Date]>([
    new Date(`${props.filters.from}T00:00:00`),
    new Date(`${props.filters.to}T00:00:00`),
]);

function formatDateParam(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function applyRange() {
    const [from, to] = range.value;

    if (!from || !to) {
        return;
    }

    router.get(
        '/reports',
        { from: formatDateParam(from), to: formatDateParam(to) },
        { preserveState: true, preserveScroll: true },
    );
}

const money = computed(
    () =>
        new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: props.currency,
            maximumFractionDigits: 0,
        }),
);

const tiles = computed(() => [
    {
        label: 'Ingresos (confirmado + completado)',
        value: money.value.format(props.report.totals.revenue),
    },
    {
        label: 'Turnos facturables',
        value: String(props.report.totals.billable),
        detail: `${props.report.totals.appointments} totales en el rango`,
    },
    {
        label: 'Clientes únicos',
        value: String(props.report.totals.unique_customers),
    },
    {
        label: 'Tasa de cancelación',
        value: `${props.report.totals.cancellation_rate}%`,
        detail: `${props.report.totals.cancelled} cancelados · ${props.report.totals.no_show} ausencias`,
    },
]);

function minutesToHours(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest > 0 ? `${hours} h ${rest} m` : `${hours} h`;
}
</script>

<template>
    <Head title="Reportes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- Filtro de rango -->
            <div class="flex flex-wrap items-center gap-3">
                <DatePicker
                    v-model="range"
                    selection-mode="range"
                    :manual-input="false"
                    date-format="dd/mm/yy"
                    class="w-full sm:w-72"
                />
                <Button
                    label="Aplicar"
                    icon="pi pi-filter"
                    severity="secondary"
                    @click="applyRange"
                />
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="tile in tiles"
                    :key="tile.label"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ tile.label }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ tile.value }}
                    </p>
                    <p
                        v-if="tile.detail"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ tile.detail }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <!-- Servicios populares -->
                <section class="min-w-0">
                    <h2 class="mb-2 text-sm font-semibold">
                        Servicios más pedidos
                    </h2>
                    <DataTable
                        :value="props.report.services"
                        size="small"
                        striped-rows
                        scrollable
                    >
                        <Column field="name" header="Servicio" />
                        <Column
                            field="count"
                            header="Turnos"
                            class="text-right tabular-nums"
                        />
                        <Column header="Ingresos" class="text-right">
                            <template #body="{ data }">
                                <span class="tabular-nums">
                                    {{ money.format(data.revenue) }}
                                </span>
                            </template>
                        </Column>
                    </DataTable>
                </section>

                <!-- Rendimiento por empleado -->
                <section class="min-w-0">
                    <h2 class="mb-2 text-sm font-semibold">
                        Rendimiento por empleado
                    </h2>
                    <DataTable
                        :value="props.report.employees"
                        size="small"
                        striped-rows
                        scrollable
                    >
                        <Column header="Empleado">
                            <template #body="{ data }">
                                <span class="inline-flex items-center gap-2">
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        :style="{ background: data.color }"
                                        aria-hidden="true"
                                    />
                                    {{ data.name }}
                                </span>
                            </template>
                        </Column>
                        <Column
                            field="count"
                            header="Turnos"
                            class="text-right tabular-nums"
                        />
                        <Column header="Horas" class="text-right">
                            <template #body="{ data }">
                                <span class="tabular-nums">
                                    {{ minutesToHours(data.booked_minutes) }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Ocupación" class="text-right">
                            <template #body="{ data }">
                                <span class="tabular-nums">
                                    {{
                                        data.occupancy === null
                                            ? '—'
                                            : `${data.occupancy}%`
                                    }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Ingresos" class="text-right">
                            <template #body="{ data }">
                                <span class="tabular-nums">
                                    {{ money.format(data.revenue) }}
                                </span>
                            </template>
                        </Column>
                    </DataTable>
                </section>
            </div>

            <p class="text-xs text-muted-foreground">
                Ocupación = horas reservadas / horas de agenda publicada en el
                rango. Ingresos = turnos confirmados y completados (sin módulo
                de pagos).
            </p>
        </div>
    </AppLayout>
</template>
