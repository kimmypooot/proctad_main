<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';

defineProps({
    /** Array of { label, href? } — last item is the current page */
    items: { type: Array, required: true },
});
</script>

<template>
    <nav aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm">
            <li class="flex items-center gap-1.5">
                <Link href="/" class="flex items-center gap-1 text-slate-400 transition-colors hover:text-white">
                    <AppIcon name="home" class="h-4 w-4" />
                    <span class="sr-only">Home</span>
                </Link>
            </li>
            <li v-for="(item, index) in items" :key="index" class="flex items-center gap-1.5">
                <AppIcon name="chevron-right" class="h-3.5 w-3.5 text-slate-500" />
                <Link
                    v-if="item.href && index !== items.length - 1"
                    :href="item.href"
                    class="text-slate-400 transition-colors hover:text-white"
                >
                    {{ item.label }}
                </Link>
                <span v-else class="font-medium text-white" aria-current="page">
                    {{ item.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
