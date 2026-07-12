<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';
import { useToasts } from '@/Composables/useToasts';
import { useScanQueue } from '@/Composables/useScanQueue';
import Tooltip from '@/Components/Tooltip.vue';

const props = defineProps({
    code: { type: String, default: '' },
    examinationId: { type: Number, default: null },
    trainingId: { type: Number, default: null },
    examinationSchoolId: { type: Number, default: null },
    result: { type: Object, default: null },
    oepResult: { type: Object, default: null },
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

// In-flight guard: without this, two rapid decodes (or a decode racing a
// manual submit) can fire overlapping requests whose responses may resolve
// out of order, letting an older response clobber a newer one.
const scanLocked = ref(false);

const { push: pushToast } = useToasts();
const { queue: pendingScans, enqueue: enqueuePendingScan, remove: removePendingScan, retryAll } = useScanQueue();

const muted = ref(typeof window !== 'undefined' && window.localStorage.getItem('scanner:muted') === '1');
watch(muted, (value) => {
    if (typeof window !== 'undefined') window.localStorage.setItem('scanner:muted', value ? '1' : '0');
});

/** Short synthesized confirmation tone — no audio asset needed. */
const beep = (frequency, durationMs = 150) => {
    if (muted.value || typeof window === 'undefined') return;
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioContextClass();
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        oscillator.connect(gain);
        gain.connect(ctx.destination);
        oscillator.start();
        oscillator.stop(ctx.currentTime + durationMs / 1000);
        oscillator.onended = () => ctx.close();
    } catch {
        // Web Audio unsupported/blocked — feedback degrades to toast-only.
    }
};

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
const rosterLoading = ref(false);
watch([selectedExam, selectedTraining, selectedVenue], () => {
    router.get('/scanner', contextParams(), {
        preserveState: true,
        preserveScroll: true,
        only: ['attendanceSummary', 'venues', 'examinationId', 'trainingId', 'examinationSchoolId'],
        onStart: () => (rosterLoading.value = true),
        onFinish: () => (rosterLoading.value = false),
    });
});

/** Toast + audio feedback for a scan outcome, driven by the response props. */
const handleScanOutcome = (resultProps) => {
    if (resultProps.notFound) {
        pushToast('error', 'No record found for this code.');
        beep(220, 250);
        return;
    }

    const outcome = resultProps.attendance?.outcome;
    if (outcome === 'confirmed') {
        pushToast('success', 'Attendance confirmed.');
        beep(880, 150);
    } else if (outcome === 'already_confirmed') {
        pushToast('info', 'Already confirmed earlier.');
        beep(660, 150);
    } else if (outcome === 'venue_required') {
        pushToast('warning', 'Select a venue to record attendance.');
        beep(440, 200);
    } else if (outcome === 'not_assigned') {
        pushToast('warning', 'Not assigned to this examination or venue.');
        beep(440, 200);
    } else if (resultProps.result || resultProps.oepResult) {
        pushToast('success', 'Identity verified.');
        beep(880, 120);
    }
};

/** Replays a scan (fresh or queued) against the same idempotent endpoint. */
const replayScan = (code, context) => new Promise((resolve, reject) => {
    router.get('/scanner', { code, ...context }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            handleScanOutcome(page.props);
            resolve();
        },
        onError: () => reject(),
    });
});

const retryPendingScans = () => {
    if (pendingScans.length && (typeof navigator === 'undefined' || navigator.onLine)) {
        retryAll(replayScan);
    }
};

const manualRetryPendingScan = (entry) => {
    if (entry.status === 'failed') entry.status = 'pending';
    retryPendingScans();
};

const lookup = (code) => {
    if (!code || scanLocked.value) return;

    const now = Date.now();
    if (lastScan.text === code && now - lastScan.at < 1000) return;
    lastScan = { text: code, at: now };

    const context = contextParams();
    scanLocked.value = true;

    router.get('/scanner', { code, ...context }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => handleScanOutcome(page.props),
        onError: () => {
            enqueuePendingScan(code, context);
            pushToast('warning', 'No connection — scan queued and will retry automatically.');
        },
        onFinish: () => (scanLocked.value = false),
    });
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

let retryInterval = null;

onMounted(() => {
    retryPendingScans();
    retryInterval = setInterval(retryPendingScans, 5000);
    window.addEventListener('online', retryPendingScans);
});

onBeforeUnmount(() => {
    stopCamera();
    clearInterval(retryInterval);
    window.removeEventListener('online', retryPendingScans);
});

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

/**
 * Manual Entry name search — scoped to the selected examination/training's
 * full roster (both not-yet-present and already-confirmed), so an operator
 * can find and re-check anyone, not just people still absent. Sourced from
 * `attendanceSummary`, already loaded for the live-attendance panel, so this
 * is a client-side filter rather than a new server endpoint.
 */
const nameMatches = computed(() => {
    const term = manualCode.value.trim().toLowerCase();
    if (term.length < 2) return [];

    const notYetPresent = (props.attendanceSummary?.roster ?? []).map((r) => ({
        key: r.value,
        code: r.code,
        label: r.label,
        present: false,
    }));
    const alreadyPresent = (props.attendanceSummary?.recent ?? []).map((r) => ({
        key: r.id,
        code: r.code,
        label: r.code ? `${r.name} (${r.code})` : r.name,
        present: true,
    }));

    return [...notYetPresent, ...alreadyPresent]
        .filter((entry) => entry.code && entry.label?.toLowerCase().includes(term))
        .slice(0, 8);
});

const selectNameMatch = (entry) => {
    lookup(entry.code);
    manualCode.value = '';
};

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
    const oepAssignmentIds = manualSelected.value
        .filter((v) => v.startsWith('oep:'))
        .map((v) => Number(v.slice('oep:'.length)));
    const coveredAttendanceIds = manualSelected.value
        .filter((v) => v.startsWith('covered:'))
        .map((v) => v.slice('covered:'.length));

    manualForm
        .transform(() => ({
            type: mode.value === 'training' ? 'training' : 'exam',
            training_id: mode.value === 'training' ? selectedTraining.value : undefined,
            examination_id: mode.value === 'examination' ? selectedExam.value : undefined,
            member_ids: memberIds,
            oep_assignment_ids: oepAssignmentIds,
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
            subtitle="Scan a PROCTAD member or other examination personnel QR code to verify identity and record attendance."
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Scanner panel -->
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="flex items-center gap-2">
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
                    <Tooltip
                        wrap
                        text="Examination attendance branches three ways: regular members confirm against the whole exam (no venue needed); REC/LEC/CE-for-Investigation roles need a venue only when scanned away from their home school; Other Examination Personnel always need a venue, since their attendance is tracked per school. Training attendance is a single flat member-only check-in with no venue involved."
                    >
                        <AppIcon name="information-circle" class="h-4 w-4 cursor-help text-slate-400 hover:text-slate-600" />
                    </Tooltip>
                </div>

                <template v-if="mode === 'examination'">
                    <SelectInput
                        v-model="selectedExam"
                        label="Examination"
                        placeholder="Verify identity only"
                        class="mt-4"
                        :options="[{ value: '', label: 'Verify identity only' }, ...events.examinations]"
                    />
                    <div v-if="selectedExam && venues.length" class="mt-4 flex items-end gap-2">
                        <SelectInput
                            v-model="selectedVenue"
                            label="Venue"
                            placeholder="Required for other examination personnel attendance"
                            class="flex-1"
                            :options="venues"
                        />
                        <Tooltip
                            wrap
                            text="A venue is required to record Other Examination Personnel attendance (tracked per school), and for REC/LEC/CE-for-Investigation members scanned at a school other than their home venue. Regular Proctor/Examiner roles at their own venue don't need one."
                        >
                            <AppIcon name="information-circle" class="mb-2.5 h-4 w-4 shrink-0 cursor-help text-slate-400 hover:text-slate-600" />
                        </Tooltip>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-400">
                        Members: attendance records against the whole examination. Other examination personnel: select a venue first.
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
                    <p class="mt-1.5 text-xs text-slate-400">
                        Training attendance is a flat member check-in — no venue, no certificate-type branching.
                    </p>
                </template>

                <div class="mt-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Camera Scan</h2>
                        <p class="mt-1 text-xs text-slate-400">The camera keeps scanning continuously — no need to restart it between people.</p>
                    </div>
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-50"
                        :title="muted ? 'Unmute scan sound' : 'Mute scan sound'"
                        @click="muted = !muted"
                    >
                        <AppIcon name="bell" class="h-4 w-4" :class="muted ? 'opacity-40' : 'text-brand-600'" />
                        {{ muted ? 'Sound off' : 'Sound on' }}
                    </button>
                </div>
                <div id="qr-reader" class="mt-4 overflow-hidden rounded-lg bg-slate-900/5" :class="scanning ? 'min-h-64' : ''" />
                <div class="mt-4 flex gap-3">
                    <BaseButton v-if="!scanning" variant="primary" size="sm" @click="startCamera">
                        <AppIcon name="qr-code" class="h-4 w-4" />
                        Start Camera
                    </BaseButton>
                    <BaseButton v-else variant="outline" size="sm" @click="stopCamera">Stop Camera</BaseButton>
                </div>
                <p v-if="cameraError" class="mt-3 text-sm text-accent-600" role="alert">{{ cameraError }}</p>

                <div v-if="pendingScans.length" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold text-amber-800">
                            <BaseBadge variant="warning" size="xs">{{ pendingScans.length }}</BaseBadge>
                            scan{{ pendingScans.length === 1 ? '' : 's' }} waiting to sync
                        </p>
                        <BaseButton variant="link" @click="retryPendingScans">Retry all</BaseButton>
                    </div>
                    <ul class="mt-2 space-y-1">
                        <li v-for="entry in pendingScans" :key="entry.id" class="flex items-center justify-between gap-2 text-xs text-amber-700">
                            <span class="font-mono">{{ entry.code }}</span>
                            <span class="flex items-center gap-2">
                                <span v-if="entry.status === 'failed'" class="text-accent-600">Failed, retry manually</span>
                                <span v-else>{{ entry.status === 'retrying' ? 'Retrying…' : 'Pending' }}</span>
                                <BaseButton variant="link" @click="manualRetryPendingScan(entry)">Retry</BaseButton>
                                <BaseButton variant="link-accent" @click="removePendingScan(entry.id)">Dismiss</BaseButton>
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="text-sm font-semibold text-slate-700">Manual Entry</h3>
                    <form class="relative mt-3 flex items-end gap-3" @submit.prevent="lookup(manualCode)">
                        <div class="relative flex-1">
                            <TextInput
                                v-model="manualCode"
                                label="Search by name or enter an ID"
                                placeholder="Type a name to search, or paste an exact PROCTAD/OEP ID"
                                autocomplete="off"
                            />
                            <div
                                v-if="manualCode.trim().length >= 2 && (rosterLoading || nameMatches.length || attendanceSummary)"
                                class="absolute inset-x-0 top-full z-10 mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                            >
                                <p v-if="rosterLoading" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-400">
                                    <LoadingSpinner class="h-3.5 w-3.5" />
                                    Loading roster…
                                </p>
                                <template v-else-if="nameMatches.length">
                                    <button
                                        v-for="entry in nameMatches"
                                        :key="entry.key"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-brand-50"
                                        @click="selectNameMatch(entry)"
                                    >
                                        <span class="truncate">{{ entry.label }}</span>
                                        <BaseBadge v-if="entry.present" variant="success" size="xs" class="shrink-0">Present</BaseBadge>
                                    </button>
                                </template>
                                <p v-else class="px-3 py-2 text-sm text-slate-400">
                                    No name matches in the current roster — press Enter to look up as an exact ID instead.
                                </p>
                            </div>
                        </div>
                        <BaseButton type="submit" variant="secondary" size="sm">Look up</BaseButton>
                    </form>
                    <p v-if="!attendanceSummary && !rosterLoading" class="mt-1.5 text-xs text-slate-400">
                        Select an examination or training above to search by name — or paste an exact ID here anytime.
                    </p>

                    <button
                        v-if="attendanceSummary"
                        type="button"
                        class="mt-3 text-xs font-semibold text-brand-700 hover:underline"
                        @click="showManualFallback = !showManualFallback"
                    >
                        {{ showManualFallback ? 'Hide' : 'Show' }} bulk manual attendance (QR won't scan for someone?)
                    </button>

                    <div v-if="showManualFallback && attendanceSummary" class="mt-3 rounded-lg border border-slate-200 p-3">
                        <TextInput v-model="manualSearch" label="Search remaining roster" placeholder="Name, PROCTAD ID, or OEP ID" />
                        <p v-if="mode === 'examination' && !selectedVenue" class="mt-1.5 text-xs text-slate-400">
                            Select a venue above to include other examination personnel and REC/LEC covered-school
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
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800">{{ entry.name }}</p>
                                <p class="font-mono text-xs text-brand-700">{{ entry.code }}</p>
                                <p v-if="entry.venue" class="mt-0.5 truncate text-xs text-slate-400">
                                    {{ entry.venue }}<template v-if="entry.room"> · {{ entry.room }}</template><template v-if="entry.designation"> ({{ entry.designation }})</template>
                                </p>
                            </div>
                            <span class="shrink-0 text-xs text-slate-400">{{ entry.confirmed_at }}</span>
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
                            <template v-if="attendance.outcome === 'confirmed' || attendance.outcome === 'already_confirmed'">
                                <p v-if="attendance.venue" class="mt-1 text-xs font-normal opacity-90">
                                    <AppIcon name="building-office" class="mr-1 inline h-3.5 w-3.5 align-text-bottom" />
                                    {{ attendance.venue }}
                                    <template v-if="attendance.room"> · {{ attendance.room }}</template><template v-if="attendance.designation"> ({{ attendance.designation }})</template>
                                </p>
                                <p v-else class="mt-1 text-xs font-normal italic opacity-75">
                                    Venue/room not yet assigned for this examination.
                                </p>
                            </template>
                        </div>

                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs font-semibold text-brand-700">{{ result.proctad_id }}</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ result.name }}</p>
                                    <p class="mt-0.5 text-sm text-slate-600">{{ result.agency }}</p>
                                    <p class="text-sm font-medium text-slate-700">{{ result.field_office }}</p>
                                    <p v-if="result.venue" class="mt-1.5 text-sm text-slate-600">
                                        <AppIcon name="building-office" class="mr-1 inline h-4 w-4 align-text-bottom text-slate-400" />
                                        {{ result.venue }}
                                        <template v-if="result.room"> · {{ result.room }}</template><template v-if="result.designation"> ({{ result.designation }})</template>
                                    </p>
                                    <p v-else-if="attendance && (attendance.outcome === 'confirmed' || attendance.outcome === 'already_confirmed')" class="mt-1.5 text-xs italic text-slate-400">
                                        Venue/room not yet assigned.
                                    </p>
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

                    <div v-else-if="oepResult" class="mt-4 space-y-4">
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
                                Select a venue above to record other examination personnel attendance.
                            </template>
                            <template v-else>
                                This person is not assigned to the selected venue.
                            </template>
                            <p v-if="attendance.venue" class="mt-1 text-xs font-normal opacity-90">
                                <AppIcon name="building-office" class="mr-1 inline h-3.5 w-3.5 align-text-bottom" />
                                {{ attendance.venue }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs font-semibold text-brand-700">{{ oepResult.oep_id }}</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ oepResult.name }}</p>
                                    <p class="mt-0.5 text-sm text-slate-600">{{ oepResult.personnel_type_label }}</p>
                                    <p class="text-sm font-medium text-slate-700">{{ oepResult.field_office ?? 'Region-wide' }}</p>
                                    <p v-if="oepResult.venue" class="mt-1.5 text-sm text-slate-600">
                                        <AppIcon name="building-office" class="mr-1 inline h-4 w-4 align-text-bottom text-slate-400" />
                                        {{ oepResult.venue }}
                                    </p>
                                </div>
                                <BaseBadge :variant="oepResult.is_active ? 'success' : 'neutral'">
                                    {{ oepResult.is_active ? 'Active' : 'Inactive' }}
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
