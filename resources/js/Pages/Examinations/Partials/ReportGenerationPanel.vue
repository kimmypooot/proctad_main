<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { fetchJson, messageFor } from '@/Composables/useJsonFetch';

const props = defineProps({
    examination: { type: Object, required: true },
    venues: { type: Array, required: true },
});

const reports = [
    {
        key: 'room-assignment',
        title: 'Room Assignment',
        description: 'Proctor and Room/Supervising Examiner assignments per room, ready to print.',
        icon: 'clipboard-check',
        venueRequired: false,
    },
    {
        key: 'payroll',
        title: 'Payroll',
        description: 'Honorarium payroll sheet computed from configured fee rates.',
        icon: 'document-text',
        venueRequired: false,
    },
    {
        key: 'payroll-posting',
        title: 'Payroll Posting',
        description: 'Disbursement acknowledgment form with signatory and total.',
        icon: 'document-check',
        venueRequired: true,
    },
];

const venueOptions = computed(() => props.venues.map((v) => ({ value: v.id, label: v.school_name })));

const activeReport = ref(null);
const selectedVenueId = ref('');
const checking = ref(false);
const blocking = ref([]);
const warnings = ref([]);
const checked = ref(false);

const open = (report) => {
    activeReport.value = report;
    selectedVenueId.value = '';
    blocking.value = [];
    warnings.value = [];
    checked.value = false;
    runPrecheck();
};

const close = () => {
    activeReport.value = null;
};

const precheckUrl = computed(() => {
    if (!activeReport.value) return null;
    const params = selectedVenueId.value ? `?venue_id=${selectedVenueId.value}` : '';
    return `/examinations/${props.examination.id}/reports/${activeReport.value.key}/precheck${params}`;
});

const exportUrl = computed(() => {
    if (!activeReport.value) return null;
    const params = selectedVenueId.value ? `?venue_id=${selectedVenueId.value}` : '';
    return `/examinations/${props.examination.id}/reports/${activeReport.value.key}${params}`;
});

const runPrecheck = async () => {
    if (!precheckUrl.value) return;
    checking.value = true;
    checked.value = false;

    try {
        const data = await fetchJson(precheckUrl.value);
        blocking.value = data.blocking ?? [];
        warnings.value = data.warnings ?? [];
    } catch (e) {
        // Fails closed: an unreadable precheck becomes a blocking item, so a
        // report is never generated on the strength of a check that never ran.
        blocking.value = [messageFor(e, 'Could not check report readiness. Please try again.')];
        warnings.value = [];
    } finally {
        checking.value = false;
        checked.value = true;
    }
};

const onVenueChange = () => runPrecheck();

const canGenerate = computed(() => {
    if (!checked.value || checking.value) return false;
    if (activeReport.value?.venueRequired && !selectedVenueId.value) return false;
    return blocking.value.length === 0;
});

const generate = () => {
    if (!canGenerate.value || !exportUrl.value) return;
    window.location.href = exportUrl.value;
};
</script>

<template>
    <div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-base font-semibold text-slate-900">Step 5 · Generate Reports</h2>
            <p class="mt-1 text-sm text-slate-500">
                Generate the final printable and payroll documents for this examination. Each report has its own
                filters and downloads directly as an Excel file.
            </p>

            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div
                    v-for="report in reports"
                    :key="report.key"
                    class="flex flex-col rounded-xl border border-slate-200 p-4"
                >
                    <AppIcon :name="report.icon" class="h-6 w-6 text-brand-600" />
                    <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ report.title }}</h3>
                    <p class="mt-1 flex-1 text-xs text-slate-500">{{ report.description }}</p>
                    <BaseButton class="mt-3" variant="primary" size="sm" @click="open(report)">
                        Generate
                    </BaseButton>
                </div>
            </div>
        </div>

        <BaseModal :show="!!activeReport" :title="activeReport ? `Generate ${activeReport.title}` : ''" @close="close">
            <div v-if="activeReport" class="space-y-4">
                <SelectInput
                    v-model="selectedVenueId"
                    label="Testing Center"
                    :options="venueOptions"
                    :required="activeReport.venueRequired"
                    :optional="!activeReport.venueRequired"
                    placeholder="All Testing Centers"
                    @update:model-value="onVenueChange"
                />

                <p v-if="checking" class="text-sm text-slate-500">Checking report readiness…</p>

                <BaseAlert v-if="!checking && blocking.length" variant="error" title="Cannot generate yet">
                    <ul class="list-disc space-y-1 pl-4">
                        <li v-for="(message, i) in blocking" :key="i">{{ message }}</li>
                    </ul>
                </BaseAlert>

                <BaseAlert v-if="!checking && warnings.length" variant="warning" title="Heads up">
                    <ul class="list-disc space-y-1 pl-4">
                        <li v-for="(message, i) in warnings" :key="i">{{ message }}</li>
                    </ul>
                </BaseAlert>
            </div>

            <template #footer>
                <BaseButton variant="outline" size="sm" @click="close">Cancel</BaseButton>
                <BaseButton variant="primary" size="sm" :disabled="!canGenerate" @click="generate">
                    Generate
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>
