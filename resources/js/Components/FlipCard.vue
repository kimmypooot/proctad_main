<script setup>
import { ref } from 'vue';

const flipped = ref(false);
const toggle = () => { flipped.value = !flipped.value; };

defineExpose({ flipped, toggle });
</script>

<template>
    <div
        class="flip-card relative cursor-pointer select-none"
        :class="{ 'is-flipped': flipped }"
        role="button"
        tabindex="0"
        :aria-label="flipped ? 'Showing back of ID card. Activate to flip to front.' : 'Showing front of ID card. Activate to flip to back.'"
        @click="toggle"
        @keydown.enter="toggle"
        @keydown.space.prevent="toggle"
    >
        <div class="flip-card-inner relative">
            <div class="flip-card-face">
                <slot name="front" />
            </div>
            <div class="flip-card-face flip-card-back">
                <slot name="back" />
            </div>
        </div>

        <p class="mt-2 text-center text-xs font-medium text-slate-500">
            Tap card to flip &middot; showing {{ flipped ? 'back' : 'front' }}
        </p>
    </div>
</template>

<style scoped>
.flip-card {
    perspective: 1800px;
}
.flip-card-inner {
    transform-style: preserve-3d;
    transition: transform 0.6s cubic-bezier(0.4, 0.2, 0.2, 1);
}
.flip-card.is-flipped .flip-card-inner {
    transform: rotateY(180deg);
}
.flip-card-face {
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}
.flip-card-back {
    position: absolute;
    inset: 0;
    transform: rotateY(180deg);
}
</style>
