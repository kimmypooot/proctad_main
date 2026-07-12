<script setup>
defineProps({
    /** 'dark' for light backgrounds, 'light' for dark backgrounds */
    variant: { type: String, default: 'dark' },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <span class="inline-flex items-center gap-3">
        <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full p-1"
            :class="variant === 'light' ? 'bg-white' : ''"
        >
            <img :src="'/images/brand/proctad-logo.png'" alt="ProCTAD" class="h-full w-full object-contain">
        </span>
        <span v-if="!compact" class="flex flex-col leading-tight">
            <span class="text-base font-bold tracking-tight" :class="variant === 'dark' ? 'text-brand-600' : 'text-white'">
                ProCTAD
            </span>
            <span class="text-[0.65rem] font-medium" :class="variant === 'dark' ? 'text-slate-500' : 'text-white/70'">
                CSC Regional Office VIII
            </span>
        </span>
    </span>
</template>
