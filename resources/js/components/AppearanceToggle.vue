<script setup lang="ts">
import Button from 'primevue/button';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

// Alterna claro/oscuro; la opción "system" sigue disponible en Ajustes → Apariencia
function toggle() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <Button
        :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'"
        :aria-label="isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
        severity="secondary"
        text
        rounded
        @click="toggle"
    />
</template>
