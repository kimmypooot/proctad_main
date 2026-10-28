<script setup>
import { onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthSplashOverlay from '@/Components/AuthSplashOverlay.vue';

// Where the IMIS portal sends staff. The splash is up from the first paint,
// and the one-time token is posted back while it stays: the server then asks
// IMIS who it was issued for and either signs the person in (a redirect to
// the dashboard unmounts this page) or sends them to the login page with the
// reason. The token never needs to stay in the address bar after this.
const props = defineProps({
    token: { type: String, required: true },
});

onMounted(() => {
    router.post('/sso', { sso_token: props.token }, { replace: true });
});
</script>

<template>
    <Head title="Signing you in" />

    <!-- The IMIS logo, as on IMIS's own "Opening <system>" card it continues. -->
    <AuthSplashOverlay :visible="true" instant logo="/images/imis/imis-logo.png" lead="Opening" title="ProCTAD" subtitle="Signing you in from IMIS" />
</template>
