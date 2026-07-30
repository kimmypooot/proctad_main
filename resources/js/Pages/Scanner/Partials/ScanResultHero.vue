<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { outcomeOf, scanState } from './scanOutcome';

/**
 * The scan verdict, sized to be read at arm's length by someone holding a
 * phone at a gate. On the public scanner this replaces the staff page's small
 * "Result" card and sits at the very top: the operator's only question is
 * "wave this person through or not?", and that answer should be legible
 * without leaning in or scrolling.
 */
const props = defineProps({
    result: { type: Object, default: null },
    oepResult: { type: Object, default: null },
    attendance: { type: Object, default: null },
    notFound: { type: Boolean, default: false },
    outOfReach: { type: Boolean, default: false },
    code: { type: String, default: '' },
    /** Seconds left to take this check-in back; 0 hides the control. */
    undoSeconds: { type: Number, default: 0 },
    undoBusy: { type: Boolean, default: false },
});

defineEmits(['undo']);

const outcome = computed(() => outcomeOf(props));

const person = computed(() => props.result ?? props.oepResult ?? null);

const state = computed(() => scanState(outcome.value));

/**
 * Scanning the same person twice returns an identical payload, so without a
 * forced re-render the second scan looks like nothing happened. Bumping a key
 * replays the entrance animation, making every scan visibly land.
 */
const pulse = ref(0);
watch(
    () => [props.result, props.oepResult, props.attendance, props.notFound, props.outOfReach, props.code],
    () => pulse.value++,
);
</script>

<template>
    <div
        :key="pulse"
        class="animate-scale-in rounded-2xl border-2 p-5 shadow-sm sm:p-6"
        :class="state.ring"
        role="status"
        aria-live="polite"
    >
        <div class="flex items-start gap-4">
            <!--
                The photo, where there is one: a name and an ID number confirm
                the code, not the person holding it, and checking a face is the
                entire reason someone is standing at the gate. The verdict icon
                moves to a corner badge so the colour signal survives.
            -->
            <div v-if="person?.photo_url" class="relative shrink-0">
                <img
                    :src="person.photo_url"
                    :alt="`ID photo of ${person.name}`"
                    class="h-20 w-20 rounded-xl object-cover ring-2 ring-white shadow-sm sm:h-24 sm:w-24"
                >
                <span class="absolute -bottom-1.5 -right-1.5 flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-sm">
                    <AppIcon :name="state.icon" class="h-5 w-5" :class="state.iconClass" />
                </span>
            </div>
            <AppIcon v-else :name="state.icon" class="h-10 w-10 shrink-0 sm:h-12 sm:w-12" :class="state.iconClass" />

            <div class="min-w-0 flex-1">
                <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                    :class="state.chip"
                >
                    {{ state.heading }}
                </span>

                <template v-if="person">
                    <p class="mt-3 text-2xl leading-tight font-bold text-slate-900 sm:text-3xl">
                        {{ person.name }}
                    </p>
                    <p class="mt-1 font-mono text-sm font-semibold text-slate-500">
                        {{ person.proctad_id ?? person.oep_id }}
                    </p>
                </template>

                <!-- The record is real; this link simply does not cover it.
                     Deliberately says nothing about who it belongs to — the
                     scanner never had the right to show them. -->
                <template v-else-if="outcome === 'out_of_reach'">
                    <p class="mt-3 font-mono text-lg font-bold break-all text-slate-900">{{ code }}</p>
                    <p class="mt-1 text-sm text-slate-600">
                        This code belongs to a record outside this scanner's field office. Nothing was recorded — ask
                        for a link issued by the office running this session.
                    </p>
                </template>

                <template v-else-if="outcome === 'not_found'">
                    <p class="mt-3 font-mono text-lg font-bold break-all text-slate-900">{{ code }}</p>
                    <p class="mt-1 text-sm text-slate-600">
                        This code does not match anyone on this examination's roster.
                    </p>
                </template>

                <p v-else class="mt-3 text-sm leading-relaxed text-slate-500">
                    Point the camera at a PROCTAD ID QR code, or use manual entry below.
                </p>
            </div>
        </div>

        <!-- Assignment detail: where this person is meant to be. The whole
             reason someone is standing at the gate holding a phone. -->
        <div v-if="person && (attendance?.venue || person.venue || attendance?.role_label)" class="mt-4 grid gap-2 border-t border-black/5 pt-4 sm:grid-cols-2">
            <div v-if="attendance?.venue || person.venue" class="flex items-start gap-2">
                <AppIcon name="building-office" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800">{{ attendance?.venue ?? person.venue }}</p>
                    <p v-if="attendance?.room ?? person.room" class="text-xs text-slate-500">
                        {{ attendance?.room ?? person.room }}
                        <template v-if="attendance?.designation ?? person.designation">
                            · {{ attendance?.designation ?? person.designation }}
                        </template>
                    </p>
                </div>
            </div>

            <div v-if="attendance?.role_label" class="flex items-start gap-2">
                <AppIcon name="clipboard-check" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800">{{ attendance.role_label }}</p>
                    <p v-if="attendance.confirmed_at" class="text-xs text-slate-500">{{ attendance.confirmed_at }}</p>
                </div>
            </div>
        </div>

        <p v-if="outcome === 'members_only'" class="mt-4 rounded-lg bg-white/70 px-3 py-2 text-sm text-amber-900">
            Identity confirmed, but training attendance is recorded for PROCTAD members only — nothing was saved for
            this person. Record them on the training's paper sheet.
        </p>

        <p v-if="outcome === 'wrong_venue'" class="mt-4 rounded-lg bg-white/70 px-3 py-2 text-sm text-amber-900">
            Deployed to {{ attendance.venue }}, not this venue — nothing was recorded. Send them to their own venue, or
            have an administrator reassign them first.
        </p>

        <p v-if="outcome === 'venue_required'" class="mt-4 rounded-lg bg-white/70 px-3 py-2 text-sm text-amber-900">
            This assignment covers several schools. Ask a Field Office administrator for a scanner link pinned to
            this venue.
        </p>

        <!--
            The viewfinder panel clears after a few seconds; this stays for the
            whole window, so an operator who looks up from the camera a moment
            late can still take the check-in back.
        -->
        <div v-if="undoSeconds > 0" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-black/5 pt-4">
            <p class="text-xs text-slate-600">
                Wrong person? This can still be undone.
            </p>
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-800 transition-colors hover:bg-slate-50 disabled:opacity-60"
                :disabled="undoBusy"
                @click="$emit('undo')"
            >
                <AppIcon name="arrow-path" class="h-4 w-4" />
                {{ undoBusy ? 'Undoing…' : `Undo (${undoSeconds}s)` }}
            </button>
        </div>
    </div>
</template>
