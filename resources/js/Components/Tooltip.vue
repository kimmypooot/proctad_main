<script setup>
import { ref } from 'vue';

// The sibling <Teleport> below makes this a multi-root template, so Vue can't
// automatically forward fallthrough attrs/listeners (class, @click, ...) to the
// trigger span — bind $attrs explicitly instead.
defineOptions({ inheritAttrs: false });

const props = defineProps({
    text: { type: String, required: true },
    position: { type: String, default: 'bottom', validator: (v) => ['top', 'bottom'].includes(v) },
});

// Positioned via Teleport + fixed coordinates (rather than an absolutely
// positioned sibling) so the tooltip bubble never contributes to the
// scrollable overflow of an ancestor `overflow-x-auto` table wrapper.
const triggerRef = ref(null);
const visible = ref(false);
const coords = ref({ top: 0, left: 0 });

const updatePosition = () => {
    const rect = triggerRef.value?.getBoundingClientRect();
    if (!rect) return;
    coords.value = {
        top: props.position === 'bottom' ? rect.bottom + 8 : rect.top - 8,
        left: rect.left + rect.width / 2,
    };
};

const show = () => {
    updatePosition();
    visible.value = true;
};
const hide = () => {
    visible.value = false;
};
</script>

<template>
    <span
        ref="triggerRef"
        class="inline-flex"
        v-bind="$attrs"
        @mouseenter="show"
        @mouseleave="hide"
        @focusin="show"
        @focusout="hide"
    >
        <slot />
    </span>
    <Teleport to="body">
        <span
            v-if="visible"
            role="tooltip"
            class="pointer-events-none fixed z-50 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg"
            :class="position === 'top' && '-translate-y-full'"
            :style="{ top: `${coords.top}px`, left: `${coords.left}px` }"
        >
            {{ text }}
        </span>
    </Teleport>
</template>
