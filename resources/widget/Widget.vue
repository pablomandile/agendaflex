<script setup lang="ts">
// Placeholder del widget de reservas — el flujo completo
// (servicio → empleado → fecha/slot → datos → confirmación) llega en la Etapa 6.
import Button from 'primevue/button';
import Card from 'primevue/card';

const props = defineProps<{
    tenant: string;
    publicKey: string;
    apiBase?: string;
    branch?: string;
}>();
</script>

<template>
    <div class="agendaflex-widget">
        <Card>
            <template #title>Reservá tu turno</template>
            <template #content>
                <p>
                    Widget de Agendaflex para
                    <strong>{{ props.tenant }}</strong> — próximamente el flujo
                    de reserva completo.
                </p>
            </template>
            <template #footer>
                <Button label="Reservar" icon="pi pi-calendar" disabled />
            </template>
        </Card>
    </div>
</template>

<style scoped>
.agendaflex-widget {
    /* El ancho lo define el contenedor del sitio anfitrión: nunca 100vw */
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
}

.agendaflex-widget :deep(*),
.agendaflex-widget :deep(*)::before,
.agendaflex-widget :deep(*)::after {
    box-sizing: border-box;
}
</style>
