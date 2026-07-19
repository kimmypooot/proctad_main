<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import NavBar from '@/Components/NavBar.vue';
import AppFooter from '@/Components/AppFooter.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useReveal } from '@/Composables/useReveal';

useReveal();

const page = usePage();
const flashVisible = ref(true);
const flash = computed(() => page.props.flash ?? {});

watch(() => page.props.flash, () => (flashVisible.value = true));

/* Signed-in staff pass straight through maintenance mode, so without this they
   see a working public site and conclude the switch is broken. */
const maintenanceMode = computed(() => page.props.maintenanceMode ?? false);
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

        <div v-if="maintenanceMode" class="border-b border-amber-200 bg-amber-50 px-4 py-2.5 sm:px-6 lg:px-8">
            <div class="mx-auto flex w-full max-w-7xl items-center gap-2 text-sm text-amber-900">
                <AppIcon name="exclamation-triangle" class="h-4 w-4 shrink-0" />
                <p class="min-w-0">
                    <span class="font-semibold">Maintenance mode is on.</span>
                    You can see this page because you are signed in — visitors get a maintenance notice instead.
                    <Link href="/settings" class="font-semibold underline hover:text-amber-950">Manage in Settings</Link>
                </p>
            </div>
        </div>

        <!-- aria-live so a response registering is announced, not just shown. -->
        <div
            v-if="flashVisible && (flash.success || flash.error)"
            class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 lg:px-8"
            role="status"
            aria-live="polite"
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

        <!-- Feedback button -->
        <a
            href="/contact"
            class="fixed bottom-6 right-6 z-30 flex h-12 w-12 items-center justify-center rounded-full bg-accent-500 text-white shadow-lg transition-all duration-200 hover:bg-accent-600 hover:scale-110 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:h-14 sm:w-14"
            aria-label="Report a concern or give feedback"
        >
            <AppIcon name="envelope" class="h-5 w-5 sm:h-6 sm:w-6" />
        </a>
    </div>
</template>
