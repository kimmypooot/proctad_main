<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { useToasts } from '@/Composables/useToasts';

/**
 * Issues the public /scan/{token} links used at the venue. A link lets anyone
 * holding it confirm attendance without logging in, so the panel keeps the
 * expiry and the revoke button in plain sight rather than tucked in a menu.
 */
const props = defineProps({
    sessions: { type: Array, default: () => [] },
    // Exactly one of these identifies the event the link is issued for.
    examinationId: { type: Number, default: null },
    trainingId: { type: Number, default: null },
    venues: { type: Array, default: () => [] },
    can: { type: Boolean, default: false },
});

// The training modal loads its data over fetch rather than Inertia props, so
// it needs telling to refetch after a link is issued or revoked. The
// examination page gets fresh props from the redirect and can ignore this.
const emit = defineEmits(['changed']);

const { push: pushToast } = useToasts();

/* --- Issue --- */
const showIssue = ref(false);

// Defaults to the end of today, which is what an examination-day link wants.
const endOfToday = () => {
    const date = new Date();
    date.setHours(23, 59, 0, 0);
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const form = useForm({
    examination_id: props.examinationId,
    training_id: props.trainingId,
    examination_school_id: '',
    label: '',
    expires_at: endOfToday(),
});

const openIssue = () => {
    form.reset();
    form.clearErrors();
    form.expires_at = endOfToday();
    showIssue.value = true;
};

const issue = () => form.post('/scanner-sessions', {
    preserveScroll: true,
    onSuccess: () => {
        showIssue.value = false;
        emit('changed');
    },
});

/* --- Revoke --- */
const revoking = ref(null);
const revokeForm = useForm({});
const confirmRevoke = () => revokeForm.post(`/scanner-sessions/${revoking.value.id}/revoke`, {
    preserveScroll: true,
    onSuccess: () => {
        revoking.value = null;
        emit('changed');
    },
});

const copy = async (url) => {
    try {
        await navigator.clipboard.writeText(url);
        pushToast('success', 'Scanner link copied.');
    } catch {
        // Clipboard is blocked outside a secure context — the URL is on screen
        // and selectable, so this is a downgrade, not a failure.
        pushToast('info', 'Copy blocked by the browser — select the link and copy it manually.');
    }
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Scanner Links</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Temporary links that open the QR scanner on any phone without signing in. Anyone with the link can
                    confirm attendance for this event, so keep it inside the venue and revoke it when the day
                    ends.
                </p>
            </div>
            <BaseButton v-if="can" variant="primary" size="sm" @click="openIssue" icon="qr-code">
                Issue Link
            </BaseButton>
        </div>

        <EmptyState
            v-if="!sessions.length"
            class="mt-6"
            icon="qr-code"
            title="No active scanner links"
            description="Issue one on examination day so venue staff can scan without an account."
        />

        <div v-else class="mt-6 grid gap-4 sm:grid-cols-2">
            <div
                v-for="session in sessions"
                :key="session.id"
                class="flex gap-4 rounded-lg border border-slate-200 p-4"
            >
                <img :src="session.qr" alt="" class="h-24 w-24 shrink-0 rounded" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        {{ session.label || 'Scanner link' }}
                    </p>
                    <p v-if="session.venue" class="truncate text-xs text-slate-500">{{ session.venue }}</p>
                    <p class="mt-2 break-all font-mono text-[0.7rem] text-slate-500">{{ session.url }}</p>
                    <p class="mt-2 text-xs text-slate-400">
                        Expires {{ session.expires_at }} · {{ session.scan_count }} scan{{ session.scan_count === 1 ? '' : 's' }}
                        <template v-if="session.last_used_at"> · last used {{ session.last_used_at }}</template>
                    </p>
                    <div class="mt-3 flex gap-3">
                        <button class="text-xs font-semibold text-brand-700 hover:underline" @click="copy(session.url)">
                            Copy link
                        </button>
                        <button
                            v-if="can"
                            class="text-xs font-semibold text-accent-600 hover:underline"
                            @click="revoking = session"
                        >
                            Revoke
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <BaseModal :show="showIssue" title="Issue a scanner link" @close="showIssue = false">
            <div class="space-y-4">
                <TextInput
                    v-model="form.label"
                    label="Label"
                    placeholder="e.g. Rizal Central School — main gate"
                    :error="form.errors.label"
                />
                <SelectInput
                    v-if="venues.length"
                    v-model="form.examination_school_id"
                    label="Venue"
                    placeholder="Select a venue"
                    :options="venues"
                    :error="form.errors.examination_school_id"
                />
                <TextInput
                    v-model="form.expires_at"
                    type="datetime-local"
                    label="Expires"
                    :error="form.errors.expires_at"
                />
                <p class="text-xs text-slate-400">
                    Links last a maximum of one week. A link scans everyone at its venue — assigned members, other
                    examination personnel, and members covering that school for monitoring.
                </p>
            </div>

            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showIssue = false">Cancel</BaseButton>
                <BaseButton variant="primary" size="sm" :loading="form.processing" @click="issue">Issue Link</BaseButton>
            </template>
        </BaseModal>

        <BaseModal :show="revoking !== null" title="Revoke this scanner link?" @close="revoking = null">
            <p class="text-sm text-slate-600">
                Anyone still holding the link will be locked out immediately. Attendance already recorded through it is
                not affected.
            </p>

            <template #footer>
                <BaseButton variant="outline" size="sm" @click="revoking = null">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="revokeForm.processing" @click="confirmRevoke">
                    Revoke
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>
