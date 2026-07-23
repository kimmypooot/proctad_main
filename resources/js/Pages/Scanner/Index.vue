<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import ScannerShell from '@/Layouts/ScannerShell.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';
import ViewMemberModal from '@/Pages/Members/Partials/ViewMemberModal.vue';
import ViewOepModal from '@/Pages/Scanner/Partials/ViewOepModal.vue';
import ScanResultHero from '@/Pages/Scanner/Partials/ScanResultHero.vue';
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
    // Set only when the page was opened through a public /scan/{token} link.
    // The event and venue are then fixed by the token, so the pickers and the
    // staff-only member drill-down come off.
    publicSession: { type: Object, default: null },
});

const isPublic = computed(() => props.publicSession !== null);
const scanUrl = computed(() => props.publicSession?.scan_url ?? '/scanner');
const markAttendanceUrl = computed(
    () => props.publicSession?.mark_attendance_url ?? '/scanner/mark-attendance',
);

/*
 * --- Exam-day cover ---
 *
 * Declare a seat vacant when its holder has not reported, then call an
 * Alternate Examiner from this venue's standby pool into it. The two endpoints
 * hang off the same base as the scanner itself, so the public link stays pinned
 * to its own examination and venue server-side.
 *
 * Deliberately outside the offline scan queue: a scan is an observation and
 * replays safely late, but declaring someone absent is a judgement about a
 * moment, and one syncing an hour later could strip a seat from somebody who
 * has since arrived. These need connectivity, and say so.
 */
const coverUrl = (action) => `${scanUrl.value}/${action}`;

const cover = computed(() => props.attendanceSummary?.cover ?? null);
const coveringSeat = ref(null);
const chosenAlternate = ref('');
const coverBusy = ref(false);

const markAbsent = (seat) => {
    if (!window.confirm(`Record that ${seat.name} did not report as ${seat.role_label}? An alternate can then be called in.`)) return;

    coverBusy.value = true;
    router.post(coverUrl('mark-absent'), { assignment_id: seat.id }, {
        preserveScroll: true,
        onFinish: () => (coverBusy.value = false),
    });
};

const openCover = (seat) => {
    coveringSeat.value = seat;
    chosenAlternate.value = '';
};

const confirmCover = () => {
    if (!chosenAlternate.value) return;

    coverBusy.value = true;
    router.post(coverUrl('activate-alternate'), {
        assignment_id: coveringSeat.value.id,
        alternate_assignment_id: chosenAlternate.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (coveringSeat.value = null),
        onFinish: () => (coverBusy.value = false),
    });
};

const manualCode = ref(props.code);
// The field keeps the last scanned code, so without a focus gate the
// suggestion list re-opens on every render and covers the controls beneath it.
const manualFocused = ref(false);
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

const memberModalId = ref(null);
const oepModalId = ref(null);

const { push: pushToast } = useToasts();
// Scoped to this scanner's destination — a queue shared with the staff scanner
// would replay its scans against whichever page happened to be open.
const { queue: pendingScans, enqueue: enqueuePendingScan, remove: removePendingScan, retryAll } = useScanQueue(
    props.publicSession?.token ?? 'staff',
);

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

// A public session's context lives in the token — the server ignores these
// anyway, so don't put an editable-looking event id in the URL bar.
const contextParams = () => (isPublic.value ? {} : {
    examination_id: mode.value === 'examination' ? (selectedExam.value || undefined) : undefined,
    training_id: mode.value === 'training' ? (selectedTraining.value || undefined) : undefined,
    examination_school_id: mode.value === 'examination' ? (selectedVenue.value || undefined) : undefined,
});

// Refresh the live attendance summary when the selected context changes,
// without requiring a scan first.
const rosterLoading = ref(false);
watch([selectedExam, selectedTraining, selectedVenue], () => {
    router.get(scanUrl.value, contextParams(), {
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
    } else if (outcome === 'wrong_venue') {
        pushToast('warning', 'Deployed to a different venue — nothing recorded.');
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
    let succeeded = false;

    router.get(scanUrl.value, { code, ...context }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            succeeded = true;
            handleScanOutcome(page.props);
        },
        // A failed replay is expected while offline and is retried on the next
        // tick — handled, so don't let it escape as an unhandled error.
        onNetworkError: () => false,
        // Settle from onFinish, which runs for every completed visit. Rejecting
        // only from onError would leave this promise pending forever on a
        // network failure — and retryAll awaits it, so the 5s retry loop would
        // pile up hung replays instead of retrying.
        onFinish: () => (succeeded ? resolve() : reject(new Error(`Scan replay failed for ${code}`))),
    });
});

const isOnline = ref(typeof navigator === 'undefined' || navigator.onLine !== false);
const syncing = ref(false);

/**
 * `syncing` also guards against overlap: this runs on a 5s interval, and a slow
 * replay could otherwise have the next tick start a second pass over the same
 * entries.
 */
const retryPendingScans = async () => {
    if (! pendingScans.length || syncing.value || ! isOnline.value) return;

    const before = pendingScans.length;
    syncing.value = true;

    try {
        await retryAll(replayScan);
    } finally {
        syncing.value = false;
    }

    // Only announced here, not from a watcher on the queue length — dismissing
    // entries by hand empties the queue too, and that is not a successful sync.
    if (! pendingScans.length) {
        pushToast('success', `All ${before} queued scan${before === 1 ? '' : 's'} synced.`);
    }
};

/** The explicit "Sync now" action — always answers, even when it can't sync. */
const syncNow = () => {
    if (! isOnline.value) {
        pushToast('info', 'Still offline — queued scans will sync by themselves once the connection returns.');
        return;
    }

    if (! pendingScans.length) {
        pushToast('success', 'Nothing waiting — every scan is already saved.');
        return;
    }

    retryPendingScans();
};

const syncStatus = computed(() => {
    if (! isOnline.value) {
        return {
            tone: 'wrap-amber',
            dot: 'bg-amber-500',
            label: pendingScans.length
                ? `Offline — ${pendingScans.length} scan${pendingScans.length === 1 ? '' : 's'} saved on this phone`
                : 'Offline — scans will be saved on this phone',
        };
    }

    if (syncing.value) {
        return { tone: 'wrap-brand', dot: 'bg-brand-500 animate-pulse', label: 'Syncing…' };
    }

    if (pendingScans.length) {
        return {
            tone: 'wrap-amber',
            dot: 'bg-amber-500',
            label: `${pendingScans.length} scan${pendingScans.length === 1 ? '' : 's'} waiting to sync`,
        };
    }

    return { tone: 'wrap-emerald', dot: 'bg-emerald-500', label: 'All scans saved' };
});

const syncTones = {
    'wrap-emerald': 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'wrap-amber': 'border-amber-200 bg-amber-50 text-amber-900',
    'wrap-brand': 'border-brand-200 bg-brand-50 text-brand-800',
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

    router.get(scanUrl.value, { code, ...context }, {
        preserveState: true,
        preserveScroll: true,
        only: ['result', 'oepResult', 'notFound', 'attendance', 'attendanceSummary'],
        onSuccess: (page) => handleScanOutcome(page.props),
        // onNetworkError, not onError: onError carries server-side validation
        // errors, and never fires when the request fails to reach the server at
        // all — which is precisely the case the offline queue exists for.
        // Returning false stops Inertia rethrowing: the failure is handled — the
        // scan is queued and the operator has been told — so letting it escape
        // only produces an unhandled error on every scan taken offline.
        onNetworkError: () => {
            enqueuePendingScan(code, context);
            pushToast('warning', 'No connection — scan saved on this phone and will sync automatically.');

            return false;
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

const handleOnline = () => {
    isOnline.value = true;
    retryPendingScans();
};
const handleOffline = () => (isOnline.value = false);

onMounted(() => {
    retryPendingScans();
    retryInterval = setInterval(retryPendingScans, 5000);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
});

onBeforeUnmount(() => {
    stopCamera();
    clearInterval(retryInterval);
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
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
        .post(markAttendanceUrl.value, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                manualSelected.value = [];
                manualSearch.value = '';
                showManualFallback.value = false;
            },
        });
};

/* --- Room attendance map ---
 * Per-room fill status for the selected venue. A room is "filled" once all its
 * seats (Supervising Examiner, Room Examiner, Proctor) have reported. One
 * Supervising Examiner spans a group of rooms server-side, so they show present
 * in every room of their group at once. */
const SEAT_ABBREV = {
    'Supervising Examiner': 'SE',
    'Room Examiner': 'RE',
    Proctor: 'P',
};

const roomStatus = (room) => {
    if (room.present_count === room.required_count) return 'filled';
    if (room.present_count > 0) return 'partial';
    return 'awaiting';
};

const roomStatusMeta = {
    filled: { label: 'Filled', badge: 'bg-emerald-100 text-emerald-800', wrap: 'border-emerald-200 bg-emerald-50/50' },
    partial: { label: 'Partial', badge: 'bg-amber-100 text-amber-800', wrap: 'border-amber-200 bg-amber-50/40' },
    awaiting: { label: 'Awaiting', badge: 'bg-slate-100 text-slate-500', wrap: 'border-slate-200 bg-white' },
};

const seatMeta = (seat) => {
    if (seat.present) return { icon: 'check-circle', class: 'bg-emerald-50 text-emerald-700' };
    if (seat.assigned) return { icon: 'clock', class: 'bg-amber-50 text-amber-700' };
    return { icon: null, class: 'bg-slate-50 text-slate-400' };
};

// The live-attendance panel splits into two tabs — the running tally + recent
// scans, and the per-room fill map — so only one renders at a time. The Map tab
// exists only once the venue has rooms.
const activeTab = ref('attendance');
const hasRoomMap = computed(() => Boolean(props.attendanceSummary?.roomMap?.total));
</script>

<template>
    <Head title="QR Scanner" />

    <component :is="isPublic ? ScannerShell : DashboardLayout" :session="publicSession">
        <DashboardPageHeader
            v-if="!isPublic"
            title="QR Scanner"
            subtitle="Scan a PROCTAD member or other examination personnel QR code to verify identity and record attendance."
        />

        <!-- Public: sync state is always on screen and sticks to the top while
             scrolling. An operator has no other way to tell "everything went
             through" apart from "the queue panel isn't rendering". -->
        <div
            v-if="isPublic"
            class="sticky top-0 z-20 -mx-4 mb-5 border-y px-4 py-3 sm:-mx-6 sm:px-6"
            :class="syncTones[syncStatus.tone]"
            role="status"
            aria-live="polite"
        >
            <div class="flex items-center gap-3">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="syncStatus.dot" />
                <p class="min-w-0 flex-1 truncate text-sm font-semibold">{{ syncStatus.label }}</p>
                <button
                    v-if="pendingScans.length"
                    type="button"
                    class="inline-flex min-h-9 shrink-0 items-center rounded-lg bg-white/80 px-4 py-2 text-xs font-bold ring-1 ring-black/5 disabled:opacity-50"
                    :disabled="syncing"
                    @click="syncNow"
                >
                    {{ syncing ? 'Syncing…' : 'Sync now' }}
                </button>
            </div>
        </div>

        <!-- Public: the verdict leads. On a phone the staff layout buries it
             below the camera, the roster and the manual-entry form. -->
        <ScanResultHero
            v-if="isPublic"
            :result="result"
            :oep-result="oepResult"
            :attendance="attendance"
            :not-found="notFound"
            :code="code"
        />

        <div class="grid gap-6" :class="isPublic ? 'mt-6' : 'mt-6 lg:grid-cols-2'">
            <!-- Scanner panel -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                <!-- Public link: the event and venue are fixed by the token and
                     shown in the shell header, so no pickers here. -->
                <div v-if="!isPublic" class="flex items-center gap-2">
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

                <template v-if="isPublic" />
                <template v-else-if="mode === 'examination'">
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

                <div class="flex items-center justify-between" :class="isPublic ? '' : 'mt-6'">
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
                <div
                    id="qr-reader"
                    class="mt-4 overflow-hidden rounded-xl bg-slate-900/5"
                    :class="[scanning ? 'min-h-64' : '', isPublic && scanning ? 'ring-2 ring-brand-500/40' : '']"
                />
                <!-- Public: one full-width primary action. It is tapped by
                     someone holding the phone one-handed at a gate. -->
                <div class="mt-4 flex gap-3">
                    <BaseButton
                        v-if="!scanning"
                        variant="primary"
                        :size="isPublic ? 'lg' : 'sm'"
                        :block="isPublic"
                        @click="startCamera"
                    >
                        <AppIcon name="qr-code" class="h-5 w-5" />
                        Start Camera
                    </BaseButton>
                    <BaseButton
                        v-else
                        variant="outline"
                        :size="isPublic ? 'lg' : 'sm'"
                        :block="isPublic"
                        @click="stopCamera"
                    >
                        Stop Camera
                    </BaseButton>
                </div>
                <p v-if="cameraError" class="mt-3 text-sm text-accent-600" role="alert">{{ cameraError }}</p>

                <div v-if="pendingScans.length" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold text-amber-800">
                            <BaseBadge variant="warning" size="xs">{{ pendingScans.length }}</BaseBadge>
                            scan{{ pendingScans.length === 1 ? '' : 's' }} waiting to sync
                        </p>
                        <BaseButton variant="link" :disabled="syncing" @click="syncNow">
                            {{ isPublic ? 'Sync now' : 'Retry all' }}
                        </BaseButton>
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
                        <!-- focusin/focusout (which bubble, unlike focus/blur) on
                             the whole group, so tapping a suggestion doesn't
                             close the list before the tap registers. -->
                        <div
                            class="relative flex-1"
                            @focusin="manualFocused = true"
                            @focusout="manualFocused = false"
                        >
                            <TextInput
                                v-model="manualCode"
                                label="Search by name or enter an ID"
                                placeholder="Type a name to search, or paste an exact PROCTAD/OEP ID"
                                autocomplete="off"
                            />
                            <div
                                v-if="manualFocused && manualCode.trim().length >= 2 && (rosterLoading || nameMatches.length || attendanceSummary)"
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
                                        class="flex w-full items-center justify-between gap-2 px-3 text-left text-sm text-slate-700 hover:bg-brand-50"
                                        :class="isPublic ? 'py-3' : 'py-2'"
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
                        <BaseButton type="submit" variant="secondary" :size="isPublic ? 'md' : 'sm'">Look up</BaseButton>
                    </form>
                    <p v-if="!attendanceSummary && !rosterLoading && !isPublic" class="mt-1.5 text-xs text-slate-400">
                        Select an examination or training above to search by name — or paste an exact ID here anytime.
                    </p>

                    <button
                        v-if="attendanceSummary"
                        type="button"
                        class="font-semibold text-brand-700 hover:underline"
                        :class="isPublic ? 'mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm hover:bg-brand-100 hover:no-underline' : 'mt-3 text-xs'"
                        @click="showManualFallback = !showManualFallback"
                    >
                        <AppIcon v-if="isPublic" name="user-group" class="h-4 w-4" />
                        <template v-if="isPublic">
                            {{ showManualFallback ? 'Hide manual check-in' : "Someone's QR won't scan?" }}
                        </template>
                        <template v-else>
                            {{ showManualFallback ? 'Hide' : 'Show' }} bulk manual attendance (QR won't scan for someone?)
                        </template>
                    </button>

                    <div v-if="showManualFallback && attendanceSummary" class="mt-3 rounded-lg border border-slate-200 p-3">
                        <TextInput v-model="manualSearch" label="Search remaining roster" placeholder="Name, PROCTAD ID, or OEP ID" />
                        <p v-if="mode === 'examination' && !selectedVenue && !isPublic" class="mt-1.5 text-xs text-slate-400">
                            Select a venue above to include other examination personnel and REC/LEC covered-school
                            attendance in this roster.
                        </p>
                        <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-slate-100">
                            <label
                                v-for="person in manualRoster"
                                :key="person.value"
                                class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-3 text-sm last:border-b-0 hover:bg-slate-50"
                                :class="isPublic ? 'py-3.5' : 'py-2'"
                            >
                                <input
                                    type="checkbox"
                                    :checked="manualSelected.includes(person.value)"
                                    class="rounded border-slate-300 text-brand-600 accent-brand-600"
                                    :class="isPublic ? 'h-5 w-5' : 'h-4 w-4'"
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
                                :size="isPublic ? 'md' : 'sm'"
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
                <div v-if="attendanceSummary" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                    <!-- Two views of the same venue behind tabs, so neither has
                         to scroll past the other on a phone. A full-width
                         segmented control gives each a big, thumb-friendly
                         target. The Map tab appears only once the venue has rooms. -->
                    <div v-if="hasRoomMap" class="flex gap-1 rounded-xl bg-slate-100 p-1">
                        <button
                            type="button"
                            class="min-h-10 flex-1 rounded-lg px-3 text-sm font-semibold transition-colors"
                            :class="activeTab === 'attendance' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="activeTab = 'attendance'"
                        >
                            Live Attendance
                        </button>
                        <button
                            type="button"
                            class="flex min-h-10 flex-1 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-semibold transition-colors"
                            :class="activeTab === 'map' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="activeTab = 'map'"
                        >
                            Attendance Map
                            <span
                                class="rounded-full px-1.5 text-[0.7rem] font-bold"
                                :class="activeTab === 'map' ? 'bg-brand-100 text-brand-700' : 'bg-slate-200 text-slate-500'"
                            >
                                {{ attendanceSummary.roomMap.filled }}/{{ attendanceSummary.roomMap.total }}
                            </span>
                        </button>
                    </div>
                    <div v-else class="flex items-baseline justify-between">
                        <h2 class="text-base font-semibold text-slate-900">Live Attendance</h2>
                        <p v-if="isPublic" class="text-xs font-semibold text-slate-400">
                            {{ Math.round((attendanceSummary.present / Math.max(attendanceSummary.total, 1)) * 100) }}% checked in
                        </p>
                    </div>

                    <!-- Live Attendance tab -->
                    <div v-show="!hasRoomMap || activeTab === 'attendance'">

                    <!-- Public: a progress bar plus three numbers reads faster on
                         a phone than three stat cards stacked down the screen. -->
                    <template v-if="isPublic">
                        <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                :style="{ width: `${Math.round((attendanceSummary.present / Math.max(attendanceSummary.total, 1)) * 100)}%` }"
                            />
                        </div>
                        <div class="mt-3 flex divide-x divide-slate-200 rounded-xl border border-slate-200 bg-slate-50/60 text-center">
                            <div class="flex-1 py-2.5">
                                <p class="text-xl font-bold text-emerald-600">{{ attendanceSummary.present }}</p>
                                <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-slate-500">Present</p>
                            </div>
                            <div class="flex-1 py-2.5">
                                <!--
                                    "Awaiting", not "Absent": these people have
                                    simply not been scanned yet. Real absence is
                                    the deliberate call made in the cover panel
                                    below, and labelling doors-open as "absent"
                                    would make every venue look abandoned.
                                -->
                                <p class="text-xl font-bold text-slate-400">{{ attendanceSummary.awaiting }}</p>
                                <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-slate-500">Awaiting</p>
                            </div>
                            <div class="flex-1 py-2.5">
                                <p class="text-xl font-bold text-slate-700">{{ attendanceSummary.total }}</p>
                                <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-slate-500">Total</p>
                            </div>
                        </div>
                    </template>

                    <div v-else class="mt-4 grid grid-cols-3 gap-3">
                        <StatCard compact label="Total" :value="attendanceSummary.total" icon="users" />
                        <StatCard compact label="Present" :value="attendanceSummary.present" icon="check-circle" />
                        <StatCard compact label="Awaiting" :value="attendanceSummary.awaiting" icon="clock" />
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

                    <!--
                        Exam-day cover. Venue-scoped, because the standby pool
                        is: an alternate covers the venue they are standing in.
                    -->
                    <template v-if="cover">
                        <h3 class="mt-6 text-xs font-semibold uppercase tracking-wide text-slate-400">Exam-Day Cover</h3>

                        <p v-if="!cover.seats.length && !cover.deployed.length" class="mt-2 text-sm text-slate-400">
                            Everyone assigned to this venue has reported in.
                        </p>

                        <ul class="mt-2 divide-y divide-slate-100">
                            <li v-for="seat in cover.seats" :key="seat.id" class="py-2.5 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-800">{{ seat.name }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ seat.role_label }}<template v-if="seat.room"> · {{ seat.room }}</template>
                                        </p>
                                        <p v-if="seat.covered_by" class="mt-0.5 text-xs text-brand-700">
                                            covered by {{ seat.covered_by }}
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 gap-1.5">
                                        <BaseButton
                                            v-if="!seat.absent"
                                            variant="outline"
                                            :size="isPublic ? 'md' : 'sm'"
                                            :disabled="coverBusy || !isOnline"
                                            @click="markAbsent(seat)"
                                        >
                                            Absent
                                        </BaseButton>
                                        <BaseButton
                                            v-else-if="!seat.covered_by"
                                            variant="secondary"
                                            :size="isPublic ? 'md' : 'sm'"
                                            :disabled="coverBusy || !isOnline || !cover.alternates.length"
                                            @click="openCover(seat)"
                                        >
                                            Call alternate
                                        </BaseButton>
                                        <BaseBadge v-else variant="success">Covered</BaseBadge>
                                    </div>
                                </div>

                                <!-- Inline picker, so the operator never leaves the scanner. -->
                                <div v-if="coveringSeat && coveringSeat.id === seat.id" class="mt-2 rounded-lg border border-brand-200 bg-brand-50/60 p-3">
                                    <SelectInput
                                        v-model="chosenAlternate"
                                        label="Alternate Examiner on standby"
                                        :options="cover.alternates.map((a) => ({ value: a.id, label: `${a.name} (${a.code})` }))"
                                    />
                                    <p class="mt-2 text-xs text-slate-500">
                                        They take over {{ seat.role_label }}<template v-if="seat.room"> in {{ seat.room }}</template>
                                        and are recorded present, so their certificate and evaluation show the role
                                        they actually served.
                                    </p>
                                    <div class="mt-2 flex justify-end gap-2">
                                        <BaseButton variant="outline" size="sm" @click="coveringSeat = null">Cancel</BaseButton>
                                        <BaseButton size="sm" :disabled="!chosenAlternate || coverBusy" @click="confirmCover">
                                            Confirm
                                        </BaseButton>
                                    </div>
                                </div>
                            </li>

                            <li v-for="entry in cover.deployed" :key="`deployed-${entry.id}`" class="py-2.5 text-sm">
                                <p class="font-medium text-slate-800">{{ entry.name }}</p>
                                <p class="text-xs text-brand-700">
                                    serving as {{ entry.role_label }} in place of {{ entry.covering_for }}
                                </p>
                            </li>
                        </ul>

                        <p v-if="cover.seats.length && !isOnline" class="mt-2 text-xs text-amber-700">
                            Cover needs a connection — unlike scans, it is not queued offline.
                        </p>
                        <p v-else-if="cover.seats.length && !cover.alternates.length" class="mt-2 text-xs text-slate-400">
                            No Alternate Examiner is on standby at this venue.
                        </p>
                    </template>
                    </div>
                    <!-- /Live Attendance tab -->

                    <!-- Attendance Map tab: per-room fill status. v-if keeps its
                         roomMap references safe when the venue has no rooms. -->
                    <div v-if="hasRoomMap" v-show="activeTab === 'map'" class="mt-4">
                        <div class="flex items-baseline justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Room Attendance</h3>
                            <span class="text-xs font-semibold text-slate-500">
                                {{ attendanceSummary.roomMap.filled }}/{{ attendanceSummary.roomMap.total }} rooms filled
                            </span>
                        </div>
                        <!-- On a phone the map is its tab's whole content, so let
                             it flow with the page rather than trap a scroll area
                             inside another; cap it only on larger split layouts. -->
                        <ul class="mt-3 space-y-2 sm:max-h-[32rem] sm:overflow-y-auto">
                            <li
                                v-for="room in attendanceSummary.roomMap.rooms"
                                :key="room.id"
                                class="rounded-lg border p-2.5"
                                :class="roomStatusMeta[roomStatus(room)].wrap"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="min-w-0 truncate text-sm font-semibold text-slate-800">
                                        {{ room.room_number }}<span v-if="room.designation" class="font-normal text-slate-400"> · {{ room.designation }}</span>
                                    </p>
                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-[0.7rem] font-bold"
                                        :class="roomStatusMeta[roomStatus(room)].badge"
                                    >
                                        {{ room.present_count }}/{{ room.required_count }} {{ roomStatusMeta[roomStatus(room)].label }}
                                    </span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="seat in room.seats"
                                        :key="seat.role_label"
                                        class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.7rem] font-medium"
                                        :class="seatMeta(seat).class"
                                        :title="seat.role_label"
                                    >
                                        <AppIcon v-if="seatMeta(seat).icon" :name="seatMeta(seat).icon" class="h-3 w-3" />
                                        <span class="font-bold">{{ SEAT_ABBREV[seat.role_label] }}</span>
                                        <template v-if="seat.name"> · {{ seat.name }}</template>
                                        <template v-else> · unassigned</template>
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Public mode reads its verdict from ScanResultHero at the top
                     of the page instead of this panel. -->
                <div v-if="!isPublic" class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-base font-semibold text-slate-900">Result</h2>

                    <div v-if="result" class="mt-4 space-y-4">
                        <!-- Attendance outcome -->
                        <div
                            v-if="attendance"
                            class="rounded-lg border px-4 py-3 text-sm font-medium"
                            :class="{
                                'border-emerald-200 bg-emerald-50 text-emerald-800': attendance.outcome === 'confirmed',
                                'border-sky-200 bg-sky-50 text-sky-800': attendance.outcome === 'already_confirmed',
                                'border-amber-200 bg-amber-50 text-amber-800': attendance.outcome === 'not_assigned' || attendance.outcome === 'venue_required' || attendance.outcome === 'wrong_venue',
                            }"
                            role="status"
                        >
                            <template v-if="attendance.outcome === 'confirmed'">
                                ✓ Attendance recorded as {{ attendance.role_label }} — {{ attendance.confirmed_at }}
                            </template>
                            <template v-else-if="attendance.outcome === 'already_confirmed'">
                                Attendance was already confirmed ({{ attendance.role_label }}, {{ attendance.confirmed_at }})
                            </template>
                            <template v-else-if="attendance.outcome === 'wrong_venue'">
                                Deployed to {{ attendance.venue }} as {{ attendance.role_label }}, not the selected venue —
                                nothing was recorded.
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
                                <BaseBadge v-if="result.status_label" :variant="result.status_variant">
                                    {{ result.status_label }}
                                </BaseBadge>
                            </div>
                            <div v-if="!isPublic" class="mt-4">
                                <button
                                    class="text-sm font-semibold text-brand-700 hover:underline"
                                    @click="memberModalId = result.id"
                                >
                                    Open member record →
                                </button>
                            </div>
                        </div>

                        <!-- Test Administrator service history — shown only while
                             scanning during an actual examination. -->
                        <div v-if="result.service_history" class="rounded-lg border border-slate-200 bg-white p-5">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-900">Service History</h3>
                                <span class="shrink-0 text-xs text-slate-400">{{ result.service_history.total_served }} exam(s) served</span>
                            </div>
                            <div v-if="result.service_history.designations.length" class="mt-2 flex flex-wrap gap-1.5">
                                <BaseBadge v-for="d in result.service_history.designations" :key="d.label" variant="neutral" size="xs">
                                    {{ d.label }}: {{ d.count }}
                                </BaseBadge>
                            </div>
                            <div v-if="result.service_history.records.length" class="mt-3 max-h-56 divide-y divide-slate-100 overflow-y-auto">
                                <div v-for="record in result.service_history.records" :key="record.id" class="py-2 text-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate font-medium text-slate-800">{{ record.exam_title }}</p>
                                        <BaseBadge :variant="record.status_variant" size="xs">{{ record.status_label }}</BaseBadge>
                                    </div>
                                    <p class="text-xs text-slate-400">
                                        {{ record.exam_date }} — {{ record.role_label }}
                                        <template v-if="record.testing_center">
                                            · {{ record.testing_center }}<template v-if="record.room"> ({{ record.room }})</template>
                                        </template>
                                    </p>
                                </div>
                            </div>
                            <p v-else class="mt-3 text-sm text-slate-400">No prior Test Administrator assignments on record.</p>
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
                            <div v-if="!isPublic" class="mt-4">
                                <button
                                    class="text-sm font-semibold text-brand-700 hover:underline"
                                    @click="oepModalId = oepResult.id"
                                >
                                    View record →
                                </button>
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
    </component>

    <!-- Staff-only: both modals load the full record, well beyond what a
         shared scanner link should expose. -->
    <ViewMemberModal
        v-if="!isPublic"
        :show="memberModalId !== null"
        :member-id="memberModalId"
        @close="memberModalId = null"
    />

    <ViewOepModal
        v-if="!isPublic"
        :show="oepModalId !== null"
        :oep-id="oepModalId"
        @close="oepModalId = null"
    />
</template>
