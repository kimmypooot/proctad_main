<script setup>
// Shared full-screen "signing you in / out" splash used by Login, the IMIS
// sign-in and the dashboard sign-out flow. It is the IMIS transition splash
// (ImisSplash), so every system under IMIS shows the same screen.
import ImisSplash from '@/Components/ImisSplash.vue';

defineProps({
    visible: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    note: { type: String, default: '' },
    instant: { type: Boolean, default: false },
    // Left unset, the splash's own logo (the CSC seal).
    logo: { type: String, default: undefined },
    lead: { type: String, default: '' },
});
</script>

<template>
    <ImisSplash :visible="visible" :title="title" :subtitle="subtitle" :note="note" :instant="instant" :logo="logo" :lead="lead" />
</template>
