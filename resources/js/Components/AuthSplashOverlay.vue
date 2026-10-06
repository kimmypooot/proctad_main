<template>
    <Teleport to="body">
        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="visible" class="fixed inset-0 z-[9999] flex items-center justify-center overflow-hidden"
                style="background: linear-gradient(135deg, #f0eef9 0%, #e8eafa 50%, #fdeef0 100%);">
                <AmbientBlobs />

                <div class="relative bg-white/80 backdrop-blur-xl rounded-2xl shadow-lg border border-white/40 p-10 text-center max-w-sm w-full mx-4">
                    <BrandRings />
                    <slot />
                    <PulsingDots />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
// Shared full-screen "signing you in / out" splash used by Login and the
// dashboard sign-out flow — the backdrop, rings, and dots would otherwise
// be copy-pasted into each place that needs one. Callers own the
// transitioning message text via the default slot.
import AmbientBlobs from '@/Components/AmbientBlobs.vue';
import BrandRings from '@/Components/BrandRings.vue';
import PulsingDots from '@/Components/PulsingDots.vue';

defineProps({
    visible: { type: Boolean, default: false },
});
</script>
