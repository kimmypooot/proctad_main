<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';

defineProps({
    /** Laravel paginator links: [{ url, label, active }] */
    links: { type: Array, required: true },
});

const clean = (label) => label.replace(/&laquo;|&raquo;/g, '').trim();
</script>

<template>
    <nav v-if="links.length > 3" aria-label="Pagination" class="flex items-center justify-center gap-1">
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="!link.url"
                class="inline-flex min-h-[2.5rem] min-w-[2.5rem] items-center justify-center rounded-lg px-3 py-2 text-sm text-slate-300"
            >
                <AppIcon v-if="index === 0" name="chevron-left" class="h-4 w-4" />
                <AppIcon v-else-if="index === links.length - 1" name="chevron-right" class="h-4 w-4" />
                <span v-else>{{ clean(link.label) }}</span>
            </span>
            <Link
                v-else
                :href="link.url"
                class="inline-flex min-h-[2.5rem] min-w-[2.5rem] items-center justify-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200"
                :class="link.active
                    ? 'bg-brand-600 text-white'
                    : 'text-slate-600 hover:bg-brand-50 hover:text-brand-700'"
                :aria-current="link.active ? 'page' : undefined"
            >
                <AppIcon v-if="index === 0" name="chevron-left" class="h-4 w-4" />
                <AppIcon v-else-if="index === links.length - 1" name="chevron-right" class="h-4 w-4" />
                <span v-else>{{ clean(link.label) }}</span>
            </Link>
        </template>
    </nav>
</template>
