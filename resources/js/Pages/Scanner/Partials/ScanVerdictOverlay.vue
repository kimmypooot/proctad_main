<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { scanState } from './scanOutcome';

/**
 * The verdict, thrown across the viewfinder itself.
 *
 * ScanResultHero above is the lasting record of the last scan, but once the
 * camera is running the operator's eyes are on the video — and on a phone the
 * hero is off the top of the screen by then. So the answer comes to where they
 * are already looking, and clears itself so the next person can be framed.
 *
 * Solid colour rather than a tint: this is read at a glance, over a moving
 * camera feed, in a corridor. It has to be unmistakable.
 */
const props = defineProps({
    outcome: { type: String, required: true },
    person: { type: Object, default: null },
    code: { type: String, default: '' },
    /** Seconds left to take this check-in back; 0 hides the control. */
    undoSeconds: { type: Number, default: 0 },
    undoBusy: { type: Boolean, default: false },
});

defineEmits(['dismiss', 'undo']);

const state = computed(() => scanState(props.outcome));
</script>

<template>
    <!--
        Tapping the panel clears it early; it also clears itself, so that is a
        convenience rather than the only way out and does not need to be a
        focusable control of its own. aria-live carries the verdict to a screen
        reader without pulling focus away from the camera. Undo below is a real
        button — it is the one thing here that acts.
    -->
    <div
        class="absolute inset-0 z-10 flex animate-scale-in flex-col items-center justify-center gap-2 rounded-xl px-5 text-center text-white backdrop-blur-[2px]"
        :class="state.overlay"
        role="status"
        aria-live="assertive"
        @click="$emit('dismiss')"
    >
        <!-- Over the viewfinder the face matters more than the glyph: the
             operator is comparing it to someone standing in front of them. -->
        <div v-if="person?.photo_url" class="relative">
            <img
                :src="person.photo_url"
                :alt="`ID photo of ${person.name}`"
                class="h-24 w-24 rounded-2xl object-cover ring-4 ring-white/80 shadow-lg sm:h-28 sm:w-28"
            >
            <span class="absolute -bottom-2 -right-2 flex h-9 w-9 items-center justify-center rounded-full bg-white shadow-md">
                <AppIcon :name="state.icon" class="h-5 w-5" :class="state.iconClass" />
            </span>
        </div>
        <AppIcon v-else :name="state.icon" class="h-14 w-14 drop-shadow-sm sm:h-16 sm:w-16" />

        <p class="text-lg leading-tight font-extrabold uppercase tracking-wide sm:text-xl">
            {{ state.heading }}
        </p>

        <template v-if="person">
            <p class="max-w-full truncate text-2xl leading-tight font-bold sm:text-3xl">{{ person.name }}</p>
            <p class="font-mono text-sm font-semibold text-white/90">
                {{ person.proctad_id ?? person.oep_id }}
            </p>
        </template>

        <p v-else-if="code" class="max-w-full truncate font-mono text-base font-bold text-white/90">{{ code }}</p>

        <!-- Scanned the person behind the one at the desk? This is where that is
             noticed, so this is where it has to be fixable. @click.stop so
             undoing does not also count as the tap that clears the panel. -->
        <button
            v-if="undoSeconds > 0"
            type="button"
            class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-xl bg-white/95 px-5 text-sm font-bold text-slate-900 shadow-sm transition-colors hover:bg-white disabled:opacity-60"
            :disabled="undoBusy"
            @click.stop="$emit('undo')"
        >
            <AppIcon name="arrow-path" class="h-4 w-4" />
            {{ undoBusy ? 'Undoing…' : `Undo (${undoSeconds}s)` }}
        </button>

        <span class="mt-1 text-xs font-semibold uppercase tracking-widest text-white/85">
            Tap to clear
        </span>
    </div>
</template>
