<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2 } from 'lucide-vue-next';
import Select from 'primevue/select';
import { computed } from 'vue';

interface CompanyOption {
    id: number;
    name: string;
}

const page = usePage<{
    auth: {
        companies: CompanyOption[];
        currentCompanyId: number | null;
    };
}>();

const companies = computed(() => page.props.auth.companies ?? []);
const currentId = computed(() => page.props.auth.currentCompanyId);

const currentName = computed(
    () =>
        companies.value.find((company) => company.id === currentId.value)
            ?.name ?? companies.value[0]?.name,
);

function switchCompany(companyId: number) {
    if (companyId === currentId.value) {
        return;
    }

    router.post(
        '/company/switch',
        { company_id: companyId },
        { preserveScroll: true },
    );
}
</script>

<template>
    <!-- Sin empresas (super-admin puro): no se muestra nada -->
    <div
        v-if="companies.length === 1"
        class="flex items-center gap-2 px-2 py-1.5 text-sm"
    >
        <Building2 class="h-4 w-4 shrink-0 opacity-70" aria-hidden="true" />
        <span class="truncate font-medium">{{ currentName }}</span>
    </div>

    <Select
        v-else-if="companies.length > 1"
        :model-value="currentId"
        :options="companies"
        option-label="name"
        option-value="id"
        size="small"
        class="w-full"
        aria-label="Cambiar de empresa"
        @update:model-value="switchCompany"
    />
</template>
