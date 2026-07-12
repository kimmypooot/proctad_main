<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    value: { type: Number, required: true },
    label: { type: String, required: true },
    suffix: { type: String, default: '' },
    duration: { type: Number, default: 1200 },
});

const display = ref(0);
const root = ref(null);
let observer = null;

const animate = () => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) {
        display.value = props.value;
        return;
    }

    const start = performance.now();
    const tick = (now) => {
        const progress = Math.min((now - start) / props.duration, 1);
        // ease-out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        display.value = Math.round(eased * props.value);
        if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
};

onMounted(() => {
    if (!('IntersectionObserver' in window)) {
        display.value = props.value;
        return;
    }
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting) {
                animate();
                observer.disconnect();
            }
        },
        { threshold: 0.4 },
    );
    observer.observe(root.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="root" class="text-center">
        <p class="text-3xl font-bold tracking-tight text-white sm:text-4xl" aria-hidden="true">
            {{ display.toLocaleString() }}{{ suffix }}
        </p>
        <p class="sr-only">{{ value.toLocaleString() }}{{ suffix }}</p>
        <p class="mt-2 text-sm font-medium text-brand-100">{{ label }}</p>
    </div>
</template>
