<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import NavBar from '@/Components/NavBar.vue';
import AppFooter from '@/Components/AppFooter.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import { useReveal } from '@/Composables/useReveal';

useReveal();

const page = usePage();
const flashVisible = ref(true);
const flash = computed(() => page.props.flash ?? {});

watch(() => page.props.flash, () => (flashVisible.value = true));
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
        >
            Skip to main content
        </a>

        <NavBar />

        <div
            v-if="flashVisible && (flash.success || flash.error)"
            class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 lg:px-8"
        >
            <BaseAlert
                :variant="flash.success ? 'success' : 'error'"
                dismissible
                @dismiss="flashVisible = false"
            >
                {{ flash.success ?? flash.error }}
            </BaseAlert>
        </div>

        <main id="main-content" class="flex-1">
            <slot />
        </main>

        <AppFooter />
    </div>
</template>
