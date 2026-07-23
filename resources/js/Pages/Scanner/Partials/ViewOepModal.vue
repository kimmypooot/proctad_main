<script setup>
import { watch } from 'vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import OepIdCard from '@/Components/OepIdCard.vue';
import { useDetailsResource } from '@/Composables/useDetailsResource';

const props = defineProps({
    show: { type: Boolean, required: true },
    oepId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const { loading, data: raw, error, load } = useDetailsResource(
    () => `/other-examination-personnel/${props.oepId}/details`,
    'Could not load personnel details.',
);

const oep = () => raw.value?.oep ?? null;
const idCard = () => raw.value?.idCard ?? null;

watch(() => props.show, (open) => {
    if (open && props.oepId) {
        load();
    }
});
</script>

<template>
    <BaseModal :show="show" title="Other Examination Personnel" max-width="3xl" @close="emit('close')">
        <div v-if="loading" class="flex items-center justify-center py-16">
            <LoadingSpinner class="h-8 w-8 text-slate-300" />
        </div>

        <template v-else-if="raw">
            <div class="space-y-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-mono text-xs font-semibold text-brand-700">{{ oep().oep_id }}</p>
                        <h3 class="mt-1 text-xl font-bold text-slate-900">{{ oep().name }}</h3>
                    </div>
                    <BaseBadge :variant="oep().is_active ? 'success' : 'neutral'">
                        {{ oep().is_active ? 'Active' : 'Inactive' }}
                    </BaseBadge>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-5">
                        <h4 class="text-base font-semibold text-slate-900">Details</h4>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Personnel Type</dt>
                                <dd class="mt-0.5 text-slate-700">{{ oep().personnel_type_label }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Field Office</dt>
                                <dd class="mt-0.5 text-slate-700">{{ oep().field_office?.name ?? 'Region-wide' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Sex</dt>
                                <dd class="mt-0.5 text-slate-700">{{ oep().sex === 'male' ? 'Male' : oep().sex === 'female' ? 'Female' : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Contact Number</dt>
                                <dd class="mt-0.5 text-slate-700">{{ oep().contact_number ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Email</dt>
                                <dd class="mt-0.5 text-slate-700">{{ oep().email ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Agency</dt>
                                <dd class="mt-0.5 text-slate-700">{{ oep().agency ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Position</dt>
                                <dd class="mt-0.5 text-slate-700">{{ oep().position ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Registered</dt>
                                <dd class="mt-0.5 text-slate-700">{{ oep().created_at }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="flex flex-col items-center gap-4">
                        <OepIdCard v-if="idCard()" :card="idCard()" />
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="py-16 text-center text-sm text-slate-400">
            {{ error ?? 'Could not load personnel details.' }}
        </div>
    </BaseModal>
</template>
