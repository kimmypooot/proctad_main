<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

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
    code: { type: String, default: '' },
});

const outcome = computed(() => {
    if (props.notFound) return 'not_found';
    if (props.attendance?.outcome) return props.attendance.outcome;
    if (props.result || props.oepResult) return 'identity';
    return 'idle';
});

const person = computed(() => props.result ?? props.oepResult ?? null);

/**
 * Colour carries the verdict before any text is read, so each state gets a
 * distinct hue rather than a shade of the same one. Paired with an icon and
 * wording — colour alone would fail anyone with a colour-vision deficiency,
 * and these are read fast in bad lighting.
 */
const states = {
    confirmed: {
        ring: 'border-emerald-300 bg-gradient-to-br from-emerald-50 to-emerald-100/60',
        chip: 'bg-emerald-600 text-white',
        icon: 'check-circle',
        iconClass: 'text-emerald-600',
        heading: 'Attendance recorded',
    },
    already_confirmed: {
        ring: 'border-sky-300 bg-gradient-to-br from-sky-50 to-sky-100/60',
        chip: 'bg-sky-600 text-white',
        icon: 'check-badge',
        iconClass: 'text-sky-600',
        heading: 'Already checked in',
    },
    venue_required: {
        ring: 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100/60',
        chip: 'bg-amber-500 text-white',
        icon: 'exclamation-triangle',
        iconClass: 'text-amber-600',
        heading: 'Venue needed',
    },
    wrong_venue: {
        ring: 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100/60',
        chip: 'bg-amber-500 text-white',
        icon: 'exclamation-triangle',
        iconClass: 'text-amber-600',
        heading: 'Wrong venue',
    },
    not_assigned: {
        ring: 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100/60',
        chip: 'bg-amber-500 text-white',
        icon: 'exclamation-triangle',
        iconClass: 'text-amber-600',
        heading: 'Not on this roster',
    },
    not_found: {
        ring: 'border-accent-300 bg-gradient-to-br from-accent-50 to-accent-100/60',
        chip: 'bg-accent-600 text-white',
        icon: 'x-circle',
        iconClass: 'text-accent-600',
        heading: 'No record found',
    },
    identity: {
        ring: 'border-brand-200 bg-gradient-to-br from-brand-50 to-brand-100/60',
        chip: 'bg-brand-600 text-white',
        icon: 'identification',
        iconClass: 'text-brand-600',
        heading: 'Identity verified',
    },
    idle: {
        ring: 'border-dashed border-slate-300 bg-white',
        chip: 'bg-slate-200 text-slate-600',
        icon: 'qr-code',
        iconClass: 'text-slate-300',
        heading: 'Ready to scan',
    },
};

const state = computed(() => states[outcome.value] ?? states.idle);

/**
 * Scanning the same person twice returns an identical payload, so without a
 * forced re-render the second scan looks like nothing happened. Bumping a key
 * replays the entrance animation, making every scan visibly land.
 */
const pulse = ref(0);
watch(
    () => [props.result, props.oepResult, props.attendance, props.notFound, props.code],
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
            <AppIcon :name="state.icon" class="h-10 w-10 shrink-0 sm:h-12 sm:w-12" :class="state.iconClass" />

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
                <AppIcon name="building-office" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
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
                <AppIcon name="clipboard-check" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800">{{ attendance.role_label }}</p>
                    <p v-if="attendance.confirmed_at" class="text-xs text-slate-500">{{ attendance.confirmed_at }}</p>
                </div>
            </div>
        </div>

        <p v-if="outcome === 'wrong_venue'" class="mt-4 rounded-lg bg-white/70 px-3 py-2 text-sm text-amber-900">
            Deployed to {{ attendance.venue }}, not this venue — nothing was recorded. Send them to their own venue, or
            have an administrator reassign them first.
        </p>

        <p v-if="outcome === 'venue_required'" class="mt-4 rounded-lg bg-white/70 px-3 py-2 text-sm text-amber-900">
            This assignment covers several schools. Ask a Testing Center administrator for a scanner link pinned to
            this venue.
        </p>
    </div>
</template>
