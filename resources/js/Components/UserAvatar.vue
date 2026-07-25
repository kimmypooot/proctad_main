<script setup>
/**
 * Renders a user photo that gracefully degrades to a fallback when the image
 * is absent OR fails to load (deleted file, 404, expired Google photo URL).
 *
 * A plain `v-if="src"` only covers the *absent* case — a broken but truthy URL
 * still renders the browser's broken-image glyph. This tracks the img `error`
 * event and flips to the fallback so the initials (or a slotted icon) always show.
 *
 * The outer sizing/ring/background lives at the call site; this component only
 * owns the inner image-or-fallback swap. The default slot is the initials text;
 * pass your own slot content (e.g. an <AppIcon>) to override the fallback.
 */
import { computed, ref, watch } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    // Used to derive initials and as the img alt text.
    name: { type: String, default: '' },
    // Explicit initials override (e.g. first + last initial) when the display
    // name isn't the right basis.
    initials: { type: String, default: null },
});

const failed = ref(false);

// A new src is a fresh chance to load — clear any prior failure.
watch(() => props.src, () => { failed.value = false; });

const showImage = computed(() => Boolean(props.src) && !failed.value);

const computedInitials = computed(() => {
    if (props.initials !== null) return props.initials;
    const name = (props.name ?? '').trim();
    if (!name) return 'U';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
});
</script>

<template>
    <img
        v-if="showImage"
        :src="src"
        :alt="name"
        class="h-full w-full object-cover"
        @error="failed = true"
    >
    <slot v-else>{{ computedInitials }}</slot>
</template>
