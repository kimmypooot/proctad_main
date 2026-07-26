<script setup>
import { computed, nextTick, ref, useId } from 'vue';

/**
 * Accessible dropdown/menu shell extracted from DashboardLayout's hand-built
 * notification and account menus, which had a click-catcher backdrop but no
 * focus management, no Escape handling, and no keyboard menu semantics.
 *
 * The caller supplies the trigger (via #trigger, spreading `triggerAttrs` for
 * the ARIA wiring) and the panel body (default slot). This component owns:
 *   - open/close state + a full-screen click-catcher to close on outside click
 *   - Escape to close, returning focus to the trigger
 *   - Arrow/Home/End roving focus across the panel's focusable items
 *   - role="menu" on the panel; callers mark their rows role="menuitem"
 */
const props = defineProps({
    /** Horizontal edge the panel aligns to. */
    align: { type: String, default: 'right', validator: (v) => ['left', 'right'].includes(v) },
    /** Panel-specific classes (width, margin-top, rounding, padding) — the rest is shared. */
    panelClass: { type: String, default: 'mt-2 w-64 rounded-xl' },
    /** Focus the first item on open (true for menus; false for a scrollable panel like notifications). */
    autoFocus: { type: Boolean, default: true },
});

const open = ref(false);
const panelId = `dropdown-${useId()}`;
const triggerRef = ref(null);
const panelRef = ref(null);

const FOCUSABLE = '[role="menuitem"], a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
const items = () => (panelRef.value ? Array.from(panelRef.value.querySelectorAll(FOCUSABLE)) : []);

const focusAt = (index) => {
    const list = items();
    if (!list.length) return;
    const wrapped = (index + list.length) % list.length;
    list[wrapped].focus();
};

const openMenu = async () => {
    open.value = true;
    await nextTick();
    if (props.autoFocus) focusAt(0);
    else panelRef.value?.focus();
};

const close = ({ restoreFocus = false } = {}) => {
    open.value = false;
    if (restoreFocus) triggerRef.value?.focus?.();
};

const toggle = () => (open.value ? close() : openMenu());

const onKeydown = (event) => {
    const list = items();
    const current = list.indexOf(document.activeElement);
    switch (event.key) {
        case 'Escape':
            event.preventDefault();
            close({ restoreFocus: true });
            break;
        case 'ArrowDown':
            event.preventDefault();
            focusAt(current + 1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            focusAt(current - 1);
            break;
        case 'Home':
            event.preventDefault();
            focusAt(0);
            break;
        case 'End':
            event.preventDefault();
            focusAt(list.length - 1);
            break;
    }
};

const triggerAttrs = computed(() => ({
    'aria-haspopup': 'menu',
    'aria-expanded': open.value ? 'true' : 'false',
    'aria-controls': panelId,
}));

// Expose the setter so the caller's #trigger can capture the real button node
// for focus restoration, without demanding a specific element type.
const setTrigger = (el) => (triggerRef.value = el);
</script>

<template>
    <div class="relative">
        <slot name="trigger" :toggle="toggle" :open="open" :trigger-attrs="triggerAttrs" :set-trigger="setTrigger" />

        <div v-if="open" class="fixed inset-0 z-30" @click="close()" />

        <div
            v-if="open"
            :id="panelId"
            ref="panelRef"
            role="menu"
            tabindex="-1"
            class="absolute z-40 border border-slate-200 bg-white shadow-lg focus:outline-none"
            :class="[panelClass, align === 'right' ? 'right-0' : 'left-0']"
            @keydown="onKeydown"
        >
            <slot :close="() => close({ restoreFocus: true })" />
        </div>
    </div>
</template>
