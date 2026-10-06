<template>
    <div class="flex justify-center gap-1.5 mt-6">
        <span v-for="i in 3" :key="i" class="w-2 h-2 rounded-full transition-all duration-300"
            :style="{ backgroundColor: active === i - 1 ? '#EC1C2D' : '#2A338F',
                      transform: active === i - 1 ? 'scale(1.25)' : 'scale(1)' }"></span>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

// Runs its own cycle so callers don't each need their own setInterval/
// clearInterval bookkeeping just to animate three dots.
const active = ref(0);
let timer = null;

onMounted(() => {
    timer = setInterval(() => { active.value = (active.value + 1) % 3; }, 400);
});
onBeforeUnmount(() => clearInterval(timer));
</script>
