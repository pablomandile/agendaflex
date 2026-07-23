<script setup lang="ts">
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, onMounted, ref, watch } from 'vue';
import type {
    Catalog,
    CatalogService,
    BookingResult,
    Slot,
    WidgetApi,
} from './api';
import { ApiError, createApi } from './api';

const props = defineProps<{
    tenant: string;
    publicKey: string;
    apiBase?: string;
    branch?: string;
}>();

const api: WidgetApi = createApi({
    tenant: props.tenant,
    publicKey: props.publicKey,
    apiBase: props.apiBase,
});

// ── Estado global del flujo ─────────────────────────────────────────
type Step = 'service' | 'schedule' | 'details' | 'done';

const step = ref<Step>('service');
const loading = ref(true);
const loadError = ref<string | null>(null);
const catalog = ref<Catalog | null>(null);

const branchSlug = ref<string | null>(null);
const service = ref<CatalogService | null>(null);
const employeeUuid = ref<string | null>(null);
const date = ref<Date | null>(null);
const slots = ref<Slot[]>([]);
const slot = ref<Slot | null>(null);
const loadingSlots = ref(false);

const customerName = ref('');
const customerEmail = ref('');
const customerPhone = ref('');
const notes = ref('');
const submitting = ref(false);
const submitError = ref<string | null>(null);
const result = ref<BookingResult | null>(null);

onMounted(async () => {
    try {
        catalog.value = await api.catalog();
        branchSlug.value =
            props.branch ?? catalog.value.branches[0]?.slug ?? null;

        // Branding por tenant: color primario vía CSS vars de PrimeVue
        const primary = catalog.value.company.branding?.primary;

        if (primary && root.value) {
            root.value.style.setProperty('--p-primary-color', primary);
            root.value.style.setProperty('--p-primary-hover-color', primary);
            root.value.style.setProperty('--p-primary-active-color', primary);
            root.value.style.setProperty('--af-primary', primary);
        }
    } catch {
        loadError.value =
            'No pudimos cargar la agenda. Verificá la configuración del widget.';
    } finally {
        loading.value = false;
    }
});

const root = ref<HTMLElement | null>(null);

// ── Derivados ───────────────────────────────────────────────────────
const branches = computed(() => catalog.value?.branches ?? []);

const servicesByCategory = computed(() => {
    const groups = new Map<string, CatalogService[]>();

    for (const item of catalog.value?.services ?? []) {
        const key = item.category ?? 'Servicios';
        groups.set(key, [...(groups.get(key) ?? []), item]);
    }

    return [...groups.entries()].map(([category, items]) => ({
        category,
        items,
    }));
});

const employeesForSelection = computed(() => {
    if (!catalog.value || !service.value || branchSlug.value === null) {
        return [];
    }

    return catalog.value.employees.filter(
        (employee) =>
            employee.branch_slug === branchSlug.value &&
            service.value!.employee_uuids.includes(employee.uuid),
    );
});

const employeeOptions = computed(() => [
    { label: 'Cualquier profesional', value: null as string | null },
    ...employeesForSelection.value.map((employee) => ({
        label: employee.name,
        value: employee.uuid as string | null,
    })),
]);

const currencyFormat = computed(
    () =>
        new Intl.NumberFormat(catalog.value?.company.locale ?? 'es', {
            style: 'currency',
            currency: catalog.value?.company.currency ?? 'ARS',
            maximumFractionDigits: 0,
        }),
);

function employeeName(uuid: string): string {
    return (
        catalog.value?.employees.find((employee) => employee.uuid === uuid)
            ?.name ?? ''
    );
}

// ── Slots ───────────────────────────────────────────────────────────
function formatDateParam(value: Date): string {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

watch([service, employeeUuid, date, branchSlug], async () => {
    slot.value = null;
    slots.value = [];

    if (!service.value || !date.value || !branchSlug.value) {
        return;
    }

    loadingSlots.value = true;

    try {
        slots.value = await api.availability({
            branch: branchSlug.value,
            service: service.value.uuid,
            employee: employeeUuid.value ?? undefined,
            date: formatDateParam(date.value),
        });
    } catch {
        slots.value = [];
    } finally {
        loadingSlots.value = false;
    }
});

function pickService(item: CatalogService) {
    service.value = item;
    employeeUuid.value = null;
    date.value = new Date();
    step.value = 'schedule';
}

const canSubmit = computed(
    () =>
        slot.value !== null &&
        customerName.value.trim() !== '' &&
        /.+@.+\..+/.test(customerEmail.value),
);

async function submit() {
    if (!slot.value || !service.value || !branchSlug.value) {
        return;
    }

    submitting.value = true;
    submitError.value = null;

    try {
        result.value = await api.book({
            branch: branchSlug.value,
            service: service.value.uuid,
            employee: slot.value.employee_uuid,
            starts_at: slot.value.starts_at,
            customer: {
                name: customerName.value.trim(),
                email: customerEmail.value.trim(),
                phone: customerPhone.value.trim() || null,
            },
            notes: notes.value.trim() || null,
        });

        step.value = 'done';
    } catch (error) {
        submitError.value =
            error instanceof ApiError
                ? error.message
                : 'No pudimos crear la reserva. Probá de nuevo.';

        // El slot pudo haberse tomado: recargar disponibilidad
        if (error instanceof ApiError && error.status === 422) {
            slot.value = null;
            const current = date.value;
            date.value = null;
            date.value = current;
        }
    } finally {
        submitting.value = false;
    }
}

function restart() {
    step.value = 'service';
    service.value = null;
    employeeUuid.value = null;
    date.value = null;
    slots.value = [];
    slot.value = null;
    customerName.value = '';
    customerEmail.value = '';
    customerPhone.value = '';
    notes.value = '';
    submitError.value = null;
    result.value = null;
}
</script>

<template>
    <div ref="root" class="agendaflex-widget">
        <!-- Carga inicial / error -->
        <div v-if="loading" class="af-state">Cargando agenda…</div>
        <div v-else-if="loadError" class="af-state af-error">
            {{ loadError }}
        </div>

        <template v-else-if="catalog">
            <header class="af-header">
                <strong>{{ catalog.company.name }}</strong>
                <span v-if="step !== 'done'" class="af-steps">
                    {{
                        step === 'service'
                            ? 'Paso 1 de 3'
                            : step === 'schedule'
                              ? 'Paso 2 de 3'
                              : 'Paso 3 de 3'
                    }}
                </span>
            </header>

            <!-- Paso 1: sucursal + servicio -->
            <section v-if="step === 'service'">
                <div v-if="branches.length > 1" class="af-field">
                    <label>Sucursal</label>
                    <Select
                        v-model="branchSlug"
                        :options="branches"
                        option-label="name"
                        option-value="slug"
                    />
                </div>

                <div
                    v-for="group in servicesByCategory"
                    :key="group.category"
                    class="af-group"
                >
                    <h3>{{ group.category }}</h3>
                    <button
                        v-for="item in group.items"
                        :key="item.uuid"
                        type="button"
                        class="af-service"
                        @click="pickService(item)"
                    >
                        <span class="af-service-name">{{ item.name }}</span>
                        <span class="af-service-meta">
                            {{ item.duration_minutes }} min ·
                            {{ currencyFormat.format(item.price) }}
                        </span>
                    </button>
                </div>
            </section>

            <!-- Paso 2: profesional + fecha + horario -->
            <section v-else-if="step === 'schedule'">
                <div class="af-summary">
                    {{ service?.name }}
                    <Button
                        label="Cambiar"
                        link
                        size="small"
                        @click="step = 'service'"
                    />
                </div>

                <div class="af-field">
                    <label>Profesional</label>
                    <Select
                        v-model="employeeUuid"
                        :options="employeeOptions"
                        option-label="label"
                        option-value="value"
                    />
                </div>

                <div class="af-field">
                    <label>Fecha</label>
                    <DatePicker
                        v-model="date"
                        inline
                        :min-date="new Date()"
                        class="af-datepicker"
                    />
                </div>

                <div class="af-field">
                    <label>Horarios disponibles</label>
                    <div v-if="loadingSlots" class="af-state">Buscando…</div>
                    <div v-else-if="slots.length === 0" class="af-state">
                        No hay horarios para esa fecha. Probá otro día.
                    </div>
                    <div v-else class="af-slots">
                        <button
                            v-for="item in slots"
                            :key="`${item.starts_at}-${item.employee_uuid}`"
                            type="button"
                            class="af-slot"
                            :class="{ 'af-slot-active': slot === item }"
                            @click="slot = item"
                        >
                            {{ item.label }}
                            <small v-if="employeeUuid === null">
                                {{ employeeName(item.employee_uuid) }}
                            </small>
                        </button>
                    </div>
                </div>

                <footer class="af-actions">
                    <Button
                        label="Volver"
                        severity="secondary"
                        text
                        @click="step = 'service'"
                    />
                    <Button
                        label="Continuar"
                        :disabled="!slot"
                        @click="step = 'details'"
                    />
                </footer>
            </section>

            <!-- Paso 3: datos del cliente -->
            <section v-else-if="step === 'details'">
                <div class="af-summary">
                    {{ service?.name }} · {{ slot?.label }} hs con
                    {{ employeeName(slot?.employee_uuid ?? '') }}
                    <Button
                        label="Cambiar"
                        link
                        size="small"
                        @click="step = 'schedule'"
                    />
                </div>

                <div class="af-field">
                    <label>Nombre y apellido *</label>
                    <InputText v-model="customerName" autocomplete="name" />
                </div>
                <div class="af-field">
                    <label>Email *</label>
                    <InputText
                        v-model="customerEmail"
                        type="email"
                        autocomplete="email"
                    />
                </div>
                <div class="af-field">
                    <label>Teléfono</label>
                    <InputText
                        v-model="customerPhone"
                        type="tel"
                        autocomplete="tel"
                    />
                </div>
                <div class="af-field">
                    <label>Comentarios</label>
                    <Textarea v-model="notes" rows="2" auto-resize />
                </div>

                <p v-if="submitError" class="af-error">{{ submitError }}</p>

                <footer class="af-actions">
                    <Button
                        label="Volver"
                        severity="secondary"
                        text
                        @click="step = 'schedule'"
                    />
                    <Button
                        label="Confirmar reserva"
                        icon="pi pi-check"
                        :disabled="!canSubmit"
                        :loading="submitting"
                        @click="submit"
                    />
                </footer>
            </section>

            <!-- Confirmación -->
            <section v-else-if="step === 'done' && result" class="af-done">
                <div class="af-done-icon">✓</div>
                <h3>¡Turno confirmado!</h3>
                <p>
                    <strong>{{ result.booking.service }}</strong>
                    con {{ result.booking.employee }}
                </p>
                <p class="af-done-time">{{ result.booking.local_time }} hs</p>
                <p>{{ result.booking.branch }}</p>
                <p class="af-muted">
                    Te enviamos la confirmación por email con un link para
                    gestionar tu turno.
                </p>
                <footer class="af-actions af-actions-center">
                    <a
                        :href="result.manage_url"
                        target="_blank"
                        rel="noopener"
                        class="af-link"
                    >
                        Ver mi turno
                    </a>
                    <Button
                        label="Reservar otro"
                        severity="secondary"
                        outlined
                        @click="restart"
                    />
                </footer>
            </section>
        </template>
    </div>
</template>

<style scoped>
.agendaflex-widget {
    /* El ancho lo define el contenedor del sitio anfitrión: nunca 100vw */
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
    color: inherit;
    --af-primary-fallback: var(--af-primary, var(--p-primary-color, #6366f1));
}

.agendaflex-widget :deep(*),
.agendaflex-widget :deep(*)::before,
.agendaflex-widget :deep(*)::after {
    box-sizing: border-box;
}

.af-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 1rem;
    gap: 0.5rem;
}

.af-steps,
.af-muted {
    opacity: 0.65;
    font-size: 0.85rem;
}

.af-state {
    padding: 0.75rem 0;
    font-size: 0.9rem;
    opacity: 0.75;
}

.af-error {
    color: #dc2626;
    font-size: 0.9rem;
}

.af-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 0.9rem;
}

.af-field > label {
    font-size: 0.85rem;
    font-weight: 600;
}

.af-group h3 {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.6;
    margin: 1rem 0 0.4rem;
}

.af-service {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.7rem 0.9rem;
    margin-bottom: 0.4rem;
    border: 1px solid rgba(100, 116, 139, 0.35);
    border-radius: 0.6rem;
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: pointer;
    text-align: left;
    transition: border-color 0.15s;
}

.af-service:hover {
    border-color: var(--af-primary-fallback);
}

.af-service-name {
    font-weight: 600;
}

.af-service-meta {
    font-size: 0.85rem;
    opacity: 0.7;
    white-space: nowrap;
}

.af-summary {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-weight: 600;
    margin-bottom: 1rem;
}

.af-slots {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    max-height: 12rem;
    overflow-y: auto;
}

.af-slot {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.45rem 0.8rem;
    border: 1px solid rgba(100, 116, 139, 0.35);
    border-radius: 0.5rem;
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: pointer;
}

.af-slot small {
    font-size: 0.7rem;
    opacity: 0.65;
}

.af-slot-active {
    border-color: var(--af-primary-fallback);
    background: color-mix(in srgb, var(--af-primary-fallback) 12%, transparent);
}

.af-actions {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 1rem;
}

.af-actions-center {
    justify-content: center;
    align-items: center;
}

.af-datepicker {
    max-width: 100%;
}

.af-done {
    text-align: center;
    padding: 1rem 0;
}

.af-done-icon {
    width: 3rem;
    height: 3rem;
    margin: 0 auto 0.75rem;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 1.4rem;
    font-weight: 700;
}

.af-done-time {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0.25rem 0;
}

.af-link {
    color: var(--af-primary-fallback);
    font-weight: 600;
    text-decoration: underline;
}
</style>
