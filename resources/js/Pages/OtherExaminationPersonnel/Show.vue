<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import OepIdCard from '@/Components/OepIdCard.vue';

const props = defineProps({
    oep: { type: Object, required: true },
    idCard: { type: Object, required: true },
    can: { type: Object, required: true },
});

const printCard = () => window.print();

const showDeleteModal = ref(false);
const deleting = ref(false);

const destroy = () => {
    deleting.value = true;
    router.delete(`/other-examination-personnel/${props.oep.id}`, {
        onFinish: () => {
            deleting.value = false;
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="oep.name" />

    <DashboardLayout>
        <DashboardPageHeader :title="oep.name">
            <template #eyebrow>{{ oep.oep_id }}</template>
            <template #badges>
                <BaseBadge :variant="oep.is_active ? 'success' : 'neutral'">
                    {{ oep.is_active ? 'Active' : 'Inactive' }}
                </BaseBadge>
            </template>
            <template v-if="can.update" #actions>
                <BaseButton :href="`/other-examination-personnel/${oep.id}/edit`" variant="outline" size="sm">Edit</BaseButton>
                <BaseButton variant="accent" size="sm" @click="showDeleteModal = true">Remove</BaseButton>
            </template>
        </DashboardPageHeader>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
                <h2 class="text-base font-semibold text-slate-900">Details</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Personnel Type</dt>
                        <dd class="mt-0.5 text-slate-700">{{ oep.personnel_type_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Testing Center</dt>
                        <dd class="mt-0.5 text-slate-700">{{ oep.field_office?.name ?? 'Region-wide' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Contact Number</dt>
                        <dd class="mt-0.5 text-slate-700">{{ oep.contact_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Email</dt>
                        <dd class="mt-0.5 text-slate-700">{{ oep.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Agency</dt>
                        <dd class="mt-0.5 text-slate-700">{{ oep.agency ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Position</dt>
                        <dd class="mt-0.5 text-slate-700">{{ oep.position ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flex flex-col items-center gap-4 print:block">
                <OepIdCard :card="idCard" />
                <BaseButton variant="outline" size="sm" class="print:hidden" @click="printCard">
                    Print ID
                </BaseButton>
            </div>
        </div>

        <!-- Delete confirm -->
        <BaseModal :show="showDeleteModal" title="Remove personnel" @close="showDeleteModal = false">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ oep.name }}</strong> ({{ oep.oep_id }})? This cannot be undone.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showDeleteModal = false">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="deleting" @click="destroy">Remove</BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
