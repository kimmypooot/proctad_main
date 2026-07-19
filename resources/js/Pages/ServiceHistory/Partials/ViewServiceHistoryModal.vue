<script setup>
import { watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useDetailsResource } from '@/Composables/useDetailsResource';

const props = defineProps({
    show: { type: Boolean, required: true },
    memberId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const { loading, data: raw, error, load } = useDetailsResource(
    () => `/service-history/${props.memberId}`,
    'Could not load service history.',
);

const member = () => raw.value?.member ?? null;
const summary = () => raw.value?.summary ?? { total_served: 0, designations: [] };
const records = () => raw.value?.records ?? [];

watch(() => props.show, (open) => {
    if (open && props.memberId) load();
});

</script>

<template>
    <BaseModal :show="show" title="Service History" max-width="4xl" @close="emit('close')">
        <div v-if="loading" class="py-16 text-center text-sm text-slate-400">
            Loading service history…
        </div>

        <template v-else-if="raw">
            <div class="space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-brand-700">{{ member().proctad_id }}</p>
                        <h3 class="text-xl font-semibold text-slate-900">{{ member().name }}</h3>
                        <BaseBadge v-if="member().field_office" variant="neutral" class="mt-1">
                            <AppIcon name="building-office" class="h-3.5 w-3.5" />
                            {{ member().field_office.name }}
                        </BaseBadge>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-slate-900">{{ summary().total_served }}</p>
                        <p class="text-xs text-slate-400">Examinations Served</p>
                    </div>
                </div>

                <div v-if="summary().designations.length" class="flex flex-wrap gap-2">
                    <BaseBadge v-for="d in summary().designations" :key="d.label" variant="brand">
                        {{ d.label }}: {{ d.count }}
                    </BaseBadge>
                </div>

                <div v-if="records().length" class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Examination</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="hidden px-3 py-2 sm:table-cell">Designation</th>
                                <th class="hidden px-3 py-2 md:table-cell">Venue / Room</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="record in records()" :key="record.id" class="transition-colors hover:bg-brand-50/40">
                                <td class="px-3 py-2">
                                    <p class="font-medium text-slate-900">{{ record.exam_title }}</p>
                                    <p class="text-xs text-slate-500">{{ record.exam_type }}</p>
                                    <p class="text-xs text-slate-400 sm:hidden">{{ record.role_label }}</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ record.exam_date }}</td>
                                <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ record.role_label }}</td>
                                <td class="hidden px-3 py-2 text-slate-600 md:table-cell">
                                    <template v-if="record.testing_center">
                                        {{ record.testing_center }}<span v-if="record.room"> — {{ record.room }}</span>
                                    </template>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="px-3 py-2">
                                    <BaseBadge :variant="record.status_variant">{{ record.status_label }}</BaseBadge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EmptyState
                    v-else
                    icon="clock"
                    title="No service records yet"
                    description="This Test Administrator has no attendance-confirmed assignments matching the tracked designations."
                />
            </div>
        </template>

        <div v-else class="py-16 text-center text-sm text-slate-400">
            {{ error ?? 'Could not load service history.' }}
        </div>

        <template #footer>
            <BaseButton variant="outline" size="sm" @click="emit('close')">Close</BaseButton>
        </template>
    </BaseModal>
</template>
