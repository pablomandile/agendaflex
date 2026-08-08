<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- El isotipo es un PNG a color: no se recolorea con fill/text-*.
         object-contain evita que se deforme cuando lo dimensionan con size-* (no es cuadrado). -->
    <img
        src="/img/agendaflex-icono.png"
        alt="AgendaFlex"
        class="object-contain"
        :class="className"
        v-bind="$attrs"
    />
</template>
