<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    hasRecord: { type: Boolean, required: true },
    certificates: { type: Array, required: true },
});

const releasedCount = computed(() => props.certificates.filter((c) => c.status === 'released').length);
const pendingCount = computed(() => props.certificates.filter((c) => c.status === 'pending').length);

const typeIcons = {
    appearance: 'check-badge',
    appreciation: 'trophy',
    completion: 'academic-cap',
    designation_order: 'document-text',
};

const iconFor = (certificate) => typeIcons[certificate.type] ?? 'document-check';

/* --- Filters (client-side; the list is scoped to the logged-in member) --- */
const search = ref('');
const statusFilter = ref('');
const typeFilter = ref('');

const statusOptions = computed(() => {
    const seen = new Map();
    props.certificates.forEach((c) => seen.set(c.status, c.status_label));
    return [...seen].map(([value, label]) => ({ value, label }));
});

const typeOptions = computed(() => {
    const seen = new Map();
    props.certificates.forEach((c) => seen.set(c.type, c.type_label));
    return [...seen].map(([value, label]) => ({ value, label }));
});

const filteredCertificates = computed(() => props.certificates.filter((c) => {
    if (statusFilter.value && c.status !== statusFilter.value) return false;
    if (typeFilter.value && c.type !== typeFilter.value) return false;
    if (search.value) {
        const needle = search.value.toLowerCase();
        const haystack = `${c.certificate_no ?? ''} ${c.source ?? ''}`.toLowerCase();
        if (!haystack.includes(needle)) return false;
    }
    return true;
}));

const hasActiveFilters = computed(() => !!(search.value || statusFilter.value || typeFilter.value));

const resetFilters = () => {
    search.value = '';
    statusFilter.value = '';
    typeFilter.value = '';
};

/* --- View modal --- */
const viewing = ref(null);
const viewCertificate = (certificate) => (viewing.value = certificate);
const closeViewer = () => (viewing.value = null);
</script>

<template>
    <Head title="My Certificates" />

    <DashboardLayout>
        <DashboardPageHeader title="My Certificates" subtitle="Certificates and Designation Orders issued to you." />

        <div v-if="!hasRecord" class="mt-6">
            <EmptyState
                icon="document-check"
                title="No PROCTAD record linked to your account"
                description="Your certificates become available once your Field Office registers you in the PROCTAD registry."
            />
        </div>

        <template v-else-if="certificates.length">
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <StatCard compact label="Total Certificates" :value="certificates.length" icon="document-check" />
                <StatCard compact label="Released" :value="releasedCount" icon="check-badge" accent="emerald" />
                <StatCard compact label="Pending Approval" :value="pendingCount" icon="clock" accent="amber" />
            </div>

            <!-- Filters -->
            <BaseCard padding="sm" class="mt-6 grid gap-4 sm:grid-cols-3">
                <TextInput
                    v-model="search"
                    label="Search"
                    icon="magnifying-glass"
                    placeholder="Certificate no. or source"
                />
                <SelectInput
                    v-model="statusFilter"
                    label="Status"
                    placeholder="All statuses"
                    :options="[{ value: '', label: 'All statuses' }, ...statusOptions]"
                />
                <SelectInput
                    v-model="typeFilter"
                    label="Type"
                    placeholder="All types"
                    :options="[{ value: '', label: 'All types' }, ...typeOptions]"
                />
            </BaseCard>
            <div v-if="hasActiveFilters" class="mt-2 flex items-center justify-between text-sm text-slate-500">
                <span>{{ filteredCertificates.length }} of {{ certificates.length }} certificate(s)</span>
                <BaseButton variant="link" size="sm" @click="resetFilters">Clear filters</BaseButton>
            </div>

            <div v-if="filteredCertificates.length" class="mt-6 space-y-3">
                <BaseCard
                    v-for="certificate in filteredCertificates"
                    :key="certificate.id"
                    padding="none"
                    class="flex flex-wrap items-center justify-between gap-4 p-5 transition-shadow duration-200 hover:shadow-md"
                >
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <AppIcon :name="iconFor(certificate)" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ certificate.type_label }}</p>
                            <p class="mt-1 font-mono text-sm font-semibold text-slate-900">{{ certificate.certificate_no ?? '— pending —' }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">{{ certificate.source }}</p>
                            <p v-if="certificate.source_date" class="text-xs text-slate-400">{{ certificate.source_date }}</p>
                            <p v-if="certificate.released_at" class="text-xs text-slate-400">Released {{ certificate.released_at }}</p>
                            <p v-if="certificate.disapproval_remarks" class="mt-1 max-w-md text-xs text-accent-600">
                                {{ certificate.disapproval_remarks }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <BaseBadge :variant="certificate.status_variant">{{ certificate.status_label }}</BaseBadge>
                        <template v-if="certificate.status === 'released'">
                            <BaseButton variant="link" @click="viewCertificate(certificate)" icon="eye">
                                View
                            </BaseButton>
                            <BaseButton
                                variant="link"
                                external
                                :href="`/certificates/${certificate.id}/download`" icon="arrow-down-tray"
                            >
                                Download
                            </BaseButton>
                        </template>
                    </div>
                </BaseCard>
            </div>
            <div v-else class="mt-6">
                <EmptyState
                    icon="document-check"
                    title="No certificates match your filters"
                    description="Try adjusting or clearing the filters above."
                />
            </div>
        </template>

        <div v-else class="mt-6">
            <EmptyState
                icon="document-check"
                title="No certificates yet"
                description="Certificates you earn from examinations and trainings will appear here."
            />
        </div>

        <!-- View certificate modal -->
        <BaseModal :show="!!viewing" :title="viewing?.certificate_no ?? 'Certificate'" max-width="3xl" @close="closeViewer">
            <div v-if="viewing" class="-mx-6 -my-5 overflow-hidden rounded-b-xl bg-slate-100">
                <iframe
                    :key="viewing.id"
                    :src="`/certificates/${viewing.id}/view`"
                    :title="`${viewing.certificate_no ?? 'Certificate'} preview`"
                    class="h-[75vh] w-full border-0"
                />
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="closeViewer">Close</BaseButton>
                <BaseButton
                    v-if="viewing"
                    variant="primary"
                    size="sm"
                    external
                    :href="`/certificates/${viewing.id}/download`" icon="arrow-down-tray"
                >
                    Download
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
