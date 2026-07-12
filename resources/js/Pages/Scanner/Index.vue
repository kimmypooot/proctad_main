<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    code: { type: String, default: '' },
    examinationId: { type: Number, default: null },
    trainingId: { type: Number, default: null },
    examinationSchoolId: { type: Number, default: null },
    result: { type: Object, default: null },
    nepResult: { type: Object, default: null },
    notFound: { type: Boolean, default: false },
    attendance: { type: Object, default: null },
    venues: { type: Array, default: () => [] },
    events: { type: Object, required: true },
    attendanceSummary: { type: Object, default: null },
});

const manualCode = ref(props.code);
const mode = ref(props.trainingId ? 'training' : 'examination');
const selectedExam = ref(props.examinationId ?? '');
const selectedTraining = ref(props.trainingId ?? '');
const selectedVenue = ref(props.examinationSchoolId ?? '');
const cameraError = ref(null);
const scanning = ref(false);
let scanner = null;

// Continuous-scan duplicate guard: ignore the exact same decoded text
// scanned again within 1 second (matches legacy's client-side debounce).
let lastScan = { text: null, at: 0 };

watch(mode, () => {
    selectedExam.value = '';
    selectedTraining.value = '';
    selectedVenue.value = '';
});
watch(selectedExam, () => (selectedVenue.value = ''));

const contextParams = () => ({
    examination_id: mode.value === 'examination' ? (selectedExam.value || undefined) : undefined,
    training_id: mode.value === 'training' ? (selectedTraining.value || undefined) : undefined,
    examination_school_id: mode.value === 'examination' ? (selectedVenue.value || undefined) : undefined,
});

// Refresh the live attendance summary when the selected context changes,
// without requiring a scan first.
watch([selectedExam, selectedTraining, selectedVenue], () => {
    router.get('/scanner', contextParams(), {
        preserveState: true,
        preserveScroll: true,
        only: ['attendanceSummary', 'venues', 'examinationId', 'trainingId', 'examinationSchoolId'],
    });
});

const lookup = (code) => {
    if (!code) return;

    const now = Date.now();
    if (lastScan.text === code && now - lastScan.at < 1000) return;
    lastScan = { text: code, at: now };

    router.get('/scanner', { code, ...contextParams() }, { preserveState: true, preserveScroll: true });
};

const startCamera = async () => {
    cameraError.value = null;
    try {
        const { Html5Qrcode } = await import('html5-qrcode');
        scanner = new Html5Qrcode('qr-reader');
        scanning.value = true;
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 220, height: 220 } },
            // Continuous scan: the camera keeps running after every decode —
            // only the result panel/attendance summary refresh in place.
            (decodedText) => lookup(decodedText),
            () => {},
        );
    } catch (error) {
        scanning.value = false;
        cameraError.value = 'Camera unavailable. Check permissions, or use manual entry below.';
    }
};

const stopCamera = () => {
    if (scanner && scanning.value) {
        scanner.stop().catch(() => {});
    }
    scanning.value = false;
};

onBeforeUnmount(stopCamera);

/* --- Manual bulk attendance fallback --- */
const showManualFallback = ref(false);
const manualSearch = ref('');
const manualSelected = ref([]);
const manualForm = useForm({});

const manualRoster = computed(() => {
    const term = manualSearch.value.toLowerCase();
    const roster = props.attendanceSummary?.roster ?? [];
    return term ? roster.filter((r) => r.label.toLowerCase().includes(term)) : roster;
});

const toggleManual = (id) => {
    const index = manualSelected.value.indexOf(id);
    if (index === -1) {
        manualSelected.value.push(id);
    } else {
        manualSelected.value.splice(index, 1);
    }
};

const submitManualFallback = () => {
    const memberIds = manualSelected.value
        .filter((v) => v.startsWith('member:'))
        .map((v) => Number(v.slice('member:'.length)));
    const nepAssignmentIds = manualSelected.value
        .filter((v) => v.startsWith('nep:'))
        .map((v) => Number(v.slice('nep:'.length)));
    const coveredAttendanceIds = manualSelected.value
        .filter((v) => v.startsWith('covered:'))
        .map((v) => v.slice('covered:'.length));

    manualForm
        .transform(() => ({
            type: mode.value === 'training' ? 'training' : 'exam',
            training_id: mode.value === 'training' ? selectedTraining.value : undefined,
            examination_id: mode.value === 'examination' ? selectedExam.value : undefined,
            member_ids: memberIds,
            nep_assignment_ids: nepAssignmentIds,
            covered_attendance_ids: coveredAttendanceIds,
        }))
        .post('/scanner/mark-attendance', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                manualSelected.value = [];
                manualSearch.value = '';
                showManualFallback.value = false;
            },
        });
};
</script>

<template>
    <Head title="QR Scanner" />

    <DashboardLayout>
        <DashboardPageHeader
            title="QR Scanner"
            subtitle="Scan a PROCTAD member or non-exam personnel QR code to verify identity and record attendance."
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Scanner panel -->
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                        :class="mode === 'examination' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600'"
                        @click="mode = 'examination'"
                    >
                        Examination
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                        :class="mode === 'training' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600'"
                        @click="mode = 'training'"
                    >
                        Training
                    </button>
                </div>

                <template v-if="mode === 'examination'">
                    <SelectInput
                        v-model="selectedExam"
                        label="Examination"
                        placeholder="Verify identity only"
                        class="mt-4"
                        :options="[{ value: '', label: 'Verify identity only' }, ...events.examinations]"
                    />
                    <SelectInput
                        v-if="selectedExam && venues.length"
                        v-model="selectedVenue"
                        label="Venue"
                        placeholder="Required for non-exam personnel attendance"
                        class="mt-4"
                        :options="venues"
                    />
                    <p class="mt-1.5 text-xs text-slate-400">
                        Members: attendance records against the whole examination. Non-exam personnel: select a venue first.
                    </p>
                </template>
                <template v-else>
                    <SelectInput
                        v-model="selectedTraining"
                        label="Training"
                        placeholder="Verify identity only"
                        class="mt-4"
                        :options="[{ value: '', label: 'Verify identity only' }, ...events.trainings]"
                    />
                </template>

                <h2 class="mt-6 text-base font-semibold text-slate-900">Camera Scan</h2>
                <p class="mt-1 text-xs text-slate-400">The camera keeps scanning continuously — no need to restart it between people.</p>
                <div id="qr-reader" class="mt-4 overflow-hidden rounded-lg bg-slate-900/5" :class="scanning ? 'min-h-64' : ''" />
                <div class="mt-4 flex gap-3">
                    <BaseButton v-if="!scanning" variant="primary" size="sm" @click="startCamera">
                        <AppIcon name="qr-code" class="h-4 w-4" />
                        Start Camera
                    </BaseButton>
                    <BaseButton v-else variant="outline" size="sm" @click="stopCamera">Stop Camera</BaseButton>
                </div>
                <p v-if="cameraError" class="mt-3 text-sm text-accent-600" role="alert">{{ cameraError }}</p>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="text-sm font-semibold text-slate-700">Manual Entry</h3>
                    <form class="mt-3 flex items-end gap-3" @submit.prevent="lookup(manualCode)">
                        <div class="flex-1">
                            <TextInput
                                v-model="manualCode"
                                label="PROCTAD or NEP ID"
                                placeholder="PROCTAD-CSCRO8-XXXXXX or NEP-CSCRO8-XXXXXX"
                            />
                        </div>
                        <BaseButton type="submit" variant="secondary" size="sm">Look up</BaseButton>
                    </form>

                    <button
                        v-if="attendanceSummary"
                        type="button"
                        class="mt-3 text-xs font-semibold text-brand-700 hover:underline"
                        @click="showManualFallback = !showManualFallback"
                    >
                        {{ showManualFallback ? 'Hide' : 'Show' }} bulk manual attendance (QR won't scan for someone?)
                    </button>

                    <div v-if="showManualFallback && attendanceSummary" class="mt-3 rounded-lg border border-slate-200 p-3">
                        <TextInput v-model="manualSearch" label="Search remaining roster" placeholder="Name, PROCTAD ID, or NEP ID" />
                        <p v-if="mode === 'examination' && !selectedVenue" class="mt-1.5 text-xs text-slate-400">
                            Select a venue above to include non-exam personnel and REC/LEC covered-school
                            attendance in this roster.
                        </p>
                        <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-slate-100">
                            <label
                                v-for="person in manualRoster"
                                :key="person.value"
                                class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-3 py-2 text-sm last:border-b-0 hover:bg-slate-50"
                            >
                                <input
                                    type="checkbox"
                                    :checked="manualSelected.includes(person.value)"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600"
                                    @change="toggleManual(person.value)"
                                >
                                <span class="text-slate-700">{{ person.label }}</span>
                            </label>
                            <p v-if="!manualRoster.length" class="px-3 py-4 text-center text-sm text-slate-400">
                                Everyone in this roster has been marked present.
                            </p>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <p class="text-sm text-slate-500">{{ manualSelected.length }} selected</p>
                            <BaseButton
                                variant="secondary"
                                size="sm"
                                :loading="manualForm.processing"
                                :disabled="manualForm.processing || !manualSelected.length"
                                @click="submitManualFallback"
                            >
                                Mark Selected Present
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Result + live attendance panel -->
            <div class="space-y-6">
                <div v-if="attendanceSummary" class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-base font-semibold text-slate-900">Live Attendance</h2>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <StatCard compact label="Total" :value="attendanceSummary.total" icon="users" />
                        <StatCard compact label="Present" :value="attendanceSummary.present" icon="check-circle" />
                        <StatCard compact label="Absent" :value="attendanceSummary.absent" icon="clock" />
                    </div>

                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Recent Scans</h3>
                    <ul class="mt-2 max-h-56 divide-y divide-slate-100 overflow-y-auto">
                        <li v-for="entry in attendanceSummary.recent" :key="entry.id" class="flex items-center justify-between py-2 text-sm">
                            <div>
                                <p class="font-medium text-slate-800">{{ entry.name }}</p>
                                <p class="font-mono text-xs text-brand-700">{{ entry.code }}</p>
                            </div>
                            <span class="text-xs text-slate-400">{{ entry.confirmed_at }}</span>
                        </li>
                        <li v-if="!attendanceSummary.recent.length" class="py-3 text-center text-sm text-slate-400">
                            No one marked present yet.
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-base font-semibold text-slate-900">Result</h2>

                    <div v-if="result" class="mt-4 space-y-4">
                        <!-- Attendance outcome -->
                        <div
                            v-if="attendance"
                            class="rounded-lg border px-4 py-3 text-sm font-medium"
                            :class="{
                                'border-emerald-200 bg-emerald-50 text-emerald-800': attendance.outcome === 'confirmed',
                                'border-sky-200 bg-sky-50 text-sky-800': attendance.outcome === 'already_confirmed',
                                'border-amber-200 bg-amber-50 text-amber-800': attendance.outcome === 'not_assigned' || attendance.outcome === 'venue_required',
                            }"
                            role="status"
                        >
                            <template v-if="attendance.outcome === 'confirmed'">
                                ✓ Attendance recorded as {{ attendance.role_label }} — {{ attendance.confirmed_at }}
                            </template>
                            <template v-else-if="attendance.outcome === 'already_confirmed'">
                                Attendance was already confirmed ({{ attendance.role_label }}, {{ attendance.confirmed_at }})
                            </template>
                            <template v-else>
                                This member is not assigned to the selected examination.
                            </template>
                        </div>

                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs font-semibold text-brand-700">{{ result.proctad_id }}</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ result.name }}</p>
                                    <p class="mt-0.5 text-sm text-slate-600">{{ result.agency }}</p>
                                    <p class="text-sm font-medium text-slate-700">{{ result.field_office }}</p>
                                </div>
                                <BaseBadge :variant="result.status_variant">{{ result.status_label }}</BaseBadge>
                            </div>
                            <div class="mt-4">
                                <Link
                                    :href="`/members/${result.id}`"
                                    class="text-sm font-semibold text-brand-700 hover:underline"
                                >
                                    Open member record →
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="nepResult" class="mt-4 space-y-4">
                        <div
                            v-if="attendance"
                            class="rounded-lg border px-4 py-3 text-sm font-medium"
                            :class="{
                                'border-emerald-200 bg-emerald-50 text-emerald-800': attendance.outcome === 'confirmed',
                                'border-sky-200 bg-sky-50 text-sky-800': attendance.outcome === 'already_confirmed',
                                'border-amber-200 bg-amber-50 text-amber-800': attendance.outcome === 'not_assigned' || attendance.outcome === 'venue_required',
                            }"
                            role="status"
                        >
                            <template v-if="attendance.outcome === 'confirmed'">
                                ✓ Attendance recorded — {{ attendance.confirmed_at }}
                            </template>
                            <template v-else-if="attendance.outcome === 'already_confirmed'">
                                Attendance was already confirmed ({{ attendance.confirmed_at }})
                            </template>
                            <template v-else-if="attendance.outcome === 'venue_required'">
                                Select a venue above to record non-exam personnel attendance.
                            </template>
                            <template v-else>
                                This person is not assigned to the selected venue.
                            </template>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs font-semibold text-brand-700">{{ nepResult.nep_id }}</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ nepResult.name }}</p>
                                    <p class="mt-0.5 text-sm text-slate-600">{{ nepResult.personnel_type_label }}</p>
                                    <p class="text-sm font-medium text-slate-700">{{ nepResult.field_office ?? 'Region-wide' }}</p>
                                </div>
                                <BaseBadge :variant="nepResult.is_active ? 'success' : 'neutral'">
                                    {{ nepResult.is_active ? 'Active' : 'Inactive' }}
                                </BaseBadge>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="notFound" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-5">
                        <p class="text-sm font-semibold text-red-800">No record found</p>
                        <p class="mt-1 text-sm text-red-700">
                            <span class="font-mono">{{ code }}</span> does not match any member or personnel record you can access.
                        </p>
                    </div>

                    <p v-else class="mt-4 text-sm text-slate-400">
                        Scan a QR code or enter an ID to see the record's details here.
                    </p>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
