<script setup lang="ts">
import type {
    DateClickArg,
    EventClickArg,
    EventInput,
} from '@fullcalendar/core';
import type { CalendarOptions } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, reactive, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface BranchOption {
    id: number;
    name: string;
}

interface EmployeeOption {
    id: number;
    name: string;
    color: string;
    branch_id: number;
    service_ids: number[];
}

interface ServiceOption {
    id: number;
    name: string;
    duration_minutes: number;
    price: number;
    category: string | null;
}

interface Slot {
    starts_at: string;
    label: string;
    employee_id: number;
    group: boolean;
}

interface CustomerOption {
    id: number;
    name: string;
    email: string;
    phone: string | null;
}

const props = defineProps<{
    branches: BranchOption[];
    employees: EmployeeOption[];
    services: ServiceOption[];
    timezone: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Agenda', href: '/calendar' }];

const toast = useToast();

// ── Filtros ─────────────────────────────────────────────────────────
const branchId = ref<number | null>(props.branches[0]?.id ?? null);
const employeeId = ref<number | null>(null);

const employeesForBranch = computed(() =>
    props.employees.filter(
        (employee) =>
            branchId.value === null || employee.branch_id === branchId.value,
    ),
);

// ── Calendario ──────────────────────────────────────────────────────
const calendarRef = ref<InstanceType<typeof FullCalendar>>();

function refetchEvents() {
    calendarRef.value?.getApi().refetchEvents();
}

watch([branchId, employeeId], refetchEvents);

function fetchEvents(
    info: { startStr: string; endStr: string },
    success: (events: EventInput[]) => void,
    failure: (error: Error) => void,
) {
    const params = new URLSearchParams({
        start: info.startStr,
        end: info.endStr,
    });

    if (branchId.value !== null) {
        params.set('branch_id', String(branchId.value));
    }

    if (employeeId.value !== null) {
        params.set('employee_id', String(employeeId.value));
    }

    fetch(`/calendar/events?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    })
        .then((response) =>
            response.ok
                ? response.json()
                : Promise.reject(new Error(response.statusText)),
        )
        .then(success)
        .catch(failure);
}

const calendarOptions: CalendarOptions = {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    locale: esLocale,
    // En mobile la vista primaria es el día
    initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    events: fetchEvents,
    dateClick: onDateClick,
    eventClick: onEventClick,
    allDaySlot: false,
    slotMinTime: '07:00:00',
    slotMaxTime: '22:00:00',
    nowIndicator: true,
    height: 'auto',
    dayMaxEventRows: 4,
};

// ── Diálogo de reserva ──────────────────────────────────────────────
const createOpen = ref(false);
const slots = ref<Slot[]>([]);
const loadingSlots = ref(false);
const submitting = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive({
    service_id: null as number | null,
    employee_id: null as number | null,
    date: null as Date | null,
    slot: null as Slot | null,
    customer: null as CustomerOption | null,
    customer_query: '',
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    notes: '',
});

const customerSuggestions = ref<CustomerOption[]>([]);

const employeesForService = computed(() =>
    employeesForBranch.value.filter(
        (employee) =>
            form.service_id === null ||
            employee.service_ids.includes(form.service_id),
    ),
);

function onDateClick(arg: DateClickArg) {
    resetForm();
    form.date = arg.date;
    createOpen.value = true;
}

function openCreate() {
    resetForm();
    form.date = new Date();
    createOpen.value = true;
}

function resetForm() {
    form.service_id = null;
    form.employee_id = null;
    form.date = null;
    form.slot = null;
    form.customer = null;
    form.customer_query = '';
    form.customer_name = '';
    form.customer_email = '';
    form.customer_phone = '';
    form.notes = '';
    slots.value = [];
    errors.value = {};
}

function formatDateParam(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

async function loadSlots() {
    form.slot = null;

    if (!form.service_id || !form.date || branchId.value === null) {
        slots.value = [];

        return;
    }

    loadingSlots.value = true;

    const params = new URLSearchParams({
        branch_id: String(branchId.value),
        service_id: String(form.service_id),
        date: formatDateParam(form.date),
    });

    if (form.employee_id !== null) {
        params.set('employee_id', String(form.employee_id));
    }

    try {
        const response = await fetch(`/availability?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        slots.value = response.ok ? await response.json() : [];
    } finally {
        loadingSlots.value = false;
    }
}

watch(
    () => [form.service_id, form.employee_id, form.date],
    () => void loadSlots(),
);

async function searchCustomers() {
    if (form.customer_query.length < 2) {
        customerSuggestions.value = [];

        return;
    }

    const params = new URLSearchParams({ q: form.customer_query });
    const response = await fetch(`/customers/search?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });

    customerSuggestions.value = response.ok ? await response.json() : [];
}

function employeeName(id: number): string {
    return props.employees.find((employee) => employee.id === id)?.name ?? '';
}

function submit() {
    if (!form.slot || !form.service_id || branchId.value === null) {
        return;
    }

    submitting.value = true;
    errors.value = {};

    router.post(
        '/appointments',
        {
            branch_id: branchId.value,
            service_id: form.service_id,
            employee_id: form.slot.employee_id,
            starts_at: form.slot.starts_at,
            customer: form.customer
                ? { id: form.customer.id }
                : {
                      name: form.customer_name,
                      email: form.customer_email,
                      phone: form.customer_phone || null,
                  },
            notes: form.notes || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createOpen.value = false;
                refetchEvents();
                toast.add({
                    severity: 'success',
                    summary: 'Turno creado',
                    life: 3000,
                });
            },
            onError: (formErrors) => {
                errors.value = formErrors as Record<string, string>;
                void loadSlots();
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

// ── Diálogo de detalle (cancelar / reprogramar) ─────────────────────
interface EventDetail {
    id: string;
    start: string;
    end: string;
    customer_name: string;
    service_id: number;
    service_name: string;
    employee_id: number;
    employee_name: string;
    status: string;
    notes: string | null;
}

const detail = ref<EventDetail | null>(null);
const detailOpen = ref(false);
const rescheduling = ref(false);
const rescheduleDate = ref<Date | null>(null);
const rescheduleSlots = ref<Slot[]>([]);
const rescheduleSlot = ref<Slot | null>(null);

function onEventClick(arg: EventClickArg) {
    if (arg.event.extendedProps.kind !== 'appointment') {
        return;
    }

    detail.value = {
        id: arg.event.id,
        start: arg.event.startStr,
        end: arg.event.endStr,
        customer_name: arg.event.extendedProps.customer_name,
        service_id: arg.event.extendedProps.service_id,
        service_name: arg.event.extendedProps.service_name,
        employee_id: arg.event.extendedProps.employee_id,
        employee_name: arg.event.extendedProps.employee_name,
        status: arg.event.extendedProps.status,
        notes: arg.event.extendedProps.notes,
    };
    rescheduling.value = false;
    rescheduleDate.value = null;
    rescheduleSlots.value = [];
    rescheduleSlot.value = null;
    detailOpen.value = true;
}

watch(rescheduleDate, async (date) => {
    rescheduleSlot.value = null;
    rescheduleSlots.value = [];

    if (!date || !detail.value || branchId.value === null) {
        return;
    }

    const params = new URLSearchParams({
        branch_id: String(branchId.value),
        service_id: String(detail.value.service_id),
        employee_id: String(detail.value.employee_id),
        date: formatDateParam(date),
    });

    const response = await fetch(`/availability?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });

    rescheduleSlots.value = response.ok ? await response.json() : [];
});

function cancelAppointment() {
    if (!detail.value) {
        return;
    }

    router.post(
        `/appointments/${detail.value.id}/cancel`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                detailOpen.value = false;
                refetchEvents();
                toast.add({
                    severity: 'info',
                    summary: 'Turno cancelado',
                    life: 3000,
                });
            },
        },
    );
}

function confirmReschedule() {
    if (!detail.value || !rescheduleSlot.value) {
        return;
    }

    router.post(
        `/appointments/${detail.value.id}/reschedule`,
        { starts_at: rescheduleSlot.value.starts_at },
        {
            preserveScroll: true,
            onSuccess: () => {
                detailOpen.value = false;
                refetchEvents();
                toast.add({
                    severity: 'success',
                    summary: 'Turno reprogramado',
                    life: 3000,
                });
            },
        },
    );
}

const statusSeverity: Record<string, string> = {
    confirmed: 'success',
    pending: 'warn',
    completed: 'secondary',
    no_show: 'danger',
};

function formatEventTime(iso: string): string {
    return new Date(iso).toLocaleString('es-AR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Agenda" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Toast position="top-right" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Filtros -->
            <div class="flex flex-wrap items-center gap-3">
                <Select
                    v-model="branchId"
                    :options="props.branches"
                    option-label="name"
                    option-value="id"
                    placeholder="Sucursal"
                    class="w-full sm:w-56"
                />
                <Select
                    v-model="employeeId"
                    :options="employeesForBranch"
                    option-label="name"
                    option-value="id"
                    placeholder="Todos los empleados"
                    show-clear
                    class="w-full sm:w-56"
                />
                <Button
                    label="Nuevo turno"
                    icon="pi pi-plus"
                    class="sm:ml-auto"
                    data-test="new-appointment"
                    @click="openCreate"
                />
            </div>

            <!-- Calendario -->
            <div
                class="agendaflex-calendar min-w-0 rounded-xl border border-sidebar-border/70 p-2 sm:p-4 dark:border-sidebar-border"
            >
                <FullCalendar ref="calendarRef" :options="calendarOptions" />
            </div>
        </div>

        <!-- Diálogo: nuevo turno -->
        <Dialog
            v-model:visible="createOpen"
            modal
            header="Nuevo turno"
            class="w-full max-w-lg"
        >
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Servicio</label>
                    <Select
                        v-model="form.service_id"
                        :options="props.services"
                        option-label="name"
                        option-value="id"
                        placeholder="Elegí un servicio"
                        filter
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Empleado</label>
                        <Select
                            v-model="form.employee_id"
                            :options="employeesForService"
                            option-label="name"
                            option-value="id"
                            placeholder="Cualquiera"
                            show-clear
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Fecha</label>
                        <DatePicker
                            v-model="form.date"
                            date-format="dd/mm/yy"
                            :min-date="new Date()"
                        />
                    </div>
                </div>

                <!-- Slots -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Horarios</label>
                    <div
                        v-if="loadingSlots"
                        class="text-sm text-muted-foreground"
                    >
                        Buscando horarios…
                    </div>
                    <div
                        v-else-if="slots.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            form.service_id && form.date
                                ? 'Sin horarios disponibles para esa fecha.'
                                : 'Elegí servicio y fecha para ver horarios.'
                        }}
                    </div>
                    <div
                        v-else
                        class="flex max-h-40 flex-wrap gap-2 overflow-y-auto"
                    >
                        <Button
                            v-for="slot in slots"
                            :key="`${slot.starts_at}-${slot.employee_id}`"
                            :label="`${slot.label} · ${employeeName(slot.employee_id)}`"
                            size="small"
                            :severity="
                                form.slot === slot ? undefined : 'secondary'
                            "
                            :outlined="form.slot !== slot"
                            @click="form.slot = slot"
                        />
                    </div>
                    <small v-if="errors.starts_at" class="text-red-500">
                        {{ errors.starts_at }}
                    </small>
                </div>

                <!-- Cliente -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Cliente</label>
                    <div v-if="form.customer" class="flex items-center gap-2">
                        <Tag
                            :value="`${form.customer.name} (${form.customer.email})`"
                        />
                        <Button
                            icon="pi pi-times"
                            text
                            size="small"
                            aria-label="Quitar cliente"
                            @click="form.customer = null"
                        />
                    </div>
                    <template v-else>
                        <InputText
                            v-model="form.customer_query"
                            placeholder="Buscar cliente existente…"
                            @input="searchCustomers"
                        />
                        <div
                            v-if="customerSuggestions.length"
                            class="flex flex-col rounded-md border border-sidebar-border/70"
                        >
                            <button
                                v-for="suggestion in customerSuggestions"
                                :key="suggestion.id"
                                type="button"
                                class="px-3 py-2 text-left text-sm hover:bg-muted"
                                @click="
                                    form.customer = suggestion;
                                    customerSuggestions = [];
                                "
                            >
                                {{ suggestion.name }} — {{ suggestion.email }}
                            </button>
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <InputText
                                v-model="form.customer_name"
                                placeholder="Nombre"
                            />
                            <InputText
                                v-model="form.customer_email"
                                placeholder="Email"
                                type="email"
                            />
                            <InputText
                                v-model="form.customer_phone"
                                placeholder="Teléfono"
                            />
                        </div>
                        <small
                            v-if="
                                errors['customer.email'] ||
                                errors['customer.name']
                            "
                            class="text-red-500"
                        >
                            {{
                                errors['customer.name'] ??
                                errors['customer.email']
                            }}
                        </small>
                    </template>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Notas</label>
                    <Textarea v-model="form.notes" rows="2" auto-resize />
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancelar"
                    severity="secondary"
                    text
                    @click="createOpen = false"
                />
                <Button
                    label="Reservar"
                    icon="pi pi-check"
                    :disabled="
                        !form.slot ||
                        (!form.customer &&
                            (!form.customer_name || !form.customer_email))
                    "
                    :loading="submitting"
                    data-test="confirm-booking"
                    @click="submit"
                />
            </template>
        </Dialog>

        <!-- Diálogo: detalle del turno -->
        <Dialog
            v-model:visible="detailOpen"
            modal
            header="Detalle del turno"
            class="w-full max-w-md"
        >
            <div v-if="detail" class="flex flex-col gap-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-lg font-semibold">
                        {{ detail.customer_name }}
                    </span>
                    <Tag
                        :value="detail.status"
                        :severity="statusSeverity[detail.status] ?? 'info'"
                    />
                </div>
                <div>
                    <span class="font-medium">{{ detail.service_name }}</span>
                    con {{ detail.employee_name }}
                </div>
                <div class="capitalize">
                    {{ formatEventTime(detail.start) }}
                </div>
                <p v-if="detail.notes" class="text-muted-foreground">
                    {{ detail.notes }}
                </p>

                <!-- Reprogramar -->
                <template v-if="rescheduling">
                    <div class="flex flex-col gap-2 border-t pt-3">
                        <label class="text-sm font-medium">Nueva fecha</label>
                        <DatePicker
                            v-model="rescheduleDate"
                            date-format="dd/mm/yy"
                            :min-date="new Date()"
                        />
                        <div
                            v-if="rescheduleSlots.length"
                            class="flex max-h-32 flex-wrap gap-2 overflow-y-auto"
                        >
                            <Button
                                v-for="slot in rescheduleSlots"
                                :key="slot.starts_at"
                                :label="slot.label"
                                size="small"
                                :outlined="rescheduleSlot !== slot"
                                :severity="
                                    rescheduleSlot === slot
                                        ? undefined
                                        : 'secondary'
                                "
                                @click="rescheduleSlot = slot"
                            />
                        </div>
                        <div
                            v-else-if="rescheduleDate"
                            class="text-sm text-muted-foreground"
                        >
                            Sin horarios disponibles para esa fecha.
                        </div>
                    </div>
                </template>
            </div>

            <template #footer>
                <template v-if="!rescheduling">
                    <Button
                        label="Cancelar turno"
                        severity="danger"
                        text
                        icon="pi pi-trash"
                        @click="cancelAppointment"
                    />
                    <Button
                        label="Reprogramar"
                        severity="secondary"
                        icon="pi pi-calendar"
                        @click="rescheduling = true"
                    />
                </template>
                <template v-else>
                    <Button
                        label="Volver"
                        severity="secondary"
                        text
                        @click="rescheduling = false"
                    />
                    <Button
                        label="Confirmar"
                        icon="pi pi-check"
                        :disabled="!rescheduleSlot"
                        @click="confirmReschedule"
                    />
                </template>
            </template>
        </Dialog>
    </AppLayout>
</template>

<style>
/* Integración visual de FullCalendar con los tokens de PrimeVue/Tailwind */
.agendaflex-calendar {
    --fc-border-color: var(--p-content-border-color, #e2e8f0);
    --fc-page-bg-color: transparent;
    --fc-today-bg-color: color-mix(
        in srgb,
        var(--p-primary-color, #6366f1) 8%,
        transparent
    );
    --fc-button-bg-color: var(--p-primary-color, #6366f1);
    --fc-button-border-color: var(--p-primary-color, #6366f1);
    --fc-button-hover-bg-color: var(--p-primary-hover-color, #4f46e5);
    --fc-button-hover-border-color: var(--p-primary-hover-color, #4f46e5);
    --fc-button-active-bg-color: var(--p-primary-active-color, #4338ca);
    --fc-button-active-border-color: var(--p-primary-active-color, #4338ca);
}

.agendaflex-calendar .fc {
    max-width: 100%;
}

.agendaflex-calendar .fc .fc-toolbar-title {
    font-size: 1.1rem;
    text-transform: capitalize;
}

.agendaflex-calendar .fc .fc-button {
    font-size: 0.85rem;
    padding: 0.35rem 0.7rem;
}

.agendaflex-calendar .fc-event {
    cursor: pointer;
}

@media (max-width: 640px) {
    .agendaflex-calendar .fc .fc-toolbar {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>
