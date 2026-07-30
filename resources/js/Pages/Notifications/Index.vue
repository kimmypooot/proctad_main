<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BasePagination from '@/Components/BasePagination.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    notifications: { type: Object, required: true },
    filter: { type: String, default: 'all' },
    counts: { type: Object, required: true },
});

/*
 * Day headings ("Today", "Yesterday", "March 4, 2026") come resolved from the
 * server so they follow the app's timezone rather than the browser's — a member
 * abroad must not see yesterday's notifications filed under today.
 */
const groups = computed(() => {
    const order = [];
    const byDay = new Map();

    for (const notification of props.notifications.data) {
        if (!byDay.has(notification.date_group)) {
            byDay.set(notification.date_group, []);
            order.push(notification.date_group);
        }
        byDay.get(notification.date_group).push(notification);
    }

    return order.map((label) => ({ label, items: byDay.get(label) }));
});

/** Tone → icon chip. Written out rather than interpolated so Tailwind keeps the classes. */
const toneClasses = {
    brand: 'bg-brand-50 text-brand-600',
    accent: 'bg-accent-50 text-accent-600',
    amber: 'bg-amber-50 text-amber-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    slate: 'bg-slate-100 text-slate-500',
};

const setFilter = (value) => router.get('/notifications', value === 'all' ? {} : { filter: value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

const open = (notification) => {
    if (notification.read_at) {
        if (notification.url) router.visit(notification.url);
        return;
    }

    router.post(`/notifications/${notification.id}/read`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (notification.url) router.visit(notification.url);
        },
    });
};

const markingAll = ref(false);
const markAllRead = () => router.post('/notifications/read-all', {}, {
    preserveScroll: true,
    preserveState: true,
    onStart: () => (markingAll.value = true),
    onFinish: () => (markingAll.value = false),
});
</script>

<template>
    <Head title="Notifications" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Notifications"
            subtitle="Everything the system has sent you, newest first."
        >
            <template #actions>
                <BaseButton
                    v-if="counts.unread"
                    variant="outline"
                    size="sm"
                    icon="check"
                    :loading="markingAll"
                    @click="markAllRead"
                >
                    Mark all read
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="option in [
                    { value: 'all', label: 'All', count: counts.all },
                    { value: 'unread', label: 'Unread', count: counts.unread },
                ]"
                :key="option.value"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"
                :class="filter === option.value ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="setFilter(option.value)"
            >
                {{ option.label }} ({{ option.count }})
            </button>
        </div>

        <div v-if="notifications.data.length" class="mt-6 space-y-6">
            <section v-for="group in groups" :key="group.label">
                <h2 class="px-1 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ group.label }}</h2>

                <ul class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <li v-for="notification in group.items" :key="notification.id" class="border-b border-slate-100 last:border-0">
                        <!--
                            The whole row is the target: these are read-and-go
                            items, and a small "view" link would be a needlessly
                            precise tap on a phone.
                        -->
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors hover:bg-brand-50/40 focus:bg-brand-50/40 focus:outline-none sm:gap-4 sm:px-5"
                            :class="!notification.read_at && 'bg-brand-50/50'"
                            @click="open(notification)"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full sm:h-10 sm:w-10"
                                :class="toneClasses[notification.tone] ?? toneClasses.slate"
                            >
                                <AppIcon :name="notification.icon" class="h-4.5 w-4.5 sm:h-5 sm:w-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-slate-900" :class="notification.read_at ? 'font-medium' : 'font-semibold'">
                                        {{ notification.title }}
                                    </p>
                                    <!-- Unread marker: a dot, so the row's own text stays the content. -->
                                    <span
                                        v-if="!notification.read_at"
                                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-600"
                                        aria-hidden="true"
                                    />
                                </div>
                                <p class="mt-0.5 text-sm leading-relaxed text-slate-600">{{ notification.body }}</p>
                                <p class="mt-1.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-400">
                                    <span :title="notification.created_at_full">{{ notification.created_at }}</span>
                                    <span v-if="!notification.read_at" class="font-semibold text-brand-600">Unread</span>
                                    <span v-if="notification.url" class="inline-flex items-center gap-0.5">
                                        <AppIcon name="arrow-right" class="h-3 w-3" />Open
                                    </span>
                                </p>
                            </div>
                        </button>
                    </li>
                </ul>
            </section>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="bell"
                :title="filter === 'unread' ? 'Nothing unread' : 'No notifications yet'"
                :description="filter === 'unread'
                    ? 'You are all caught up. Read notifications are still listed under All.'
                    : 'Approvals, declined assignments and certificate decisions addressed to you will appear here.'"
            >
                <template v-if="filter === 'unread' && counts.all" #action>
                    <BaseButton variant="outline" size="sm" @click="setFilter('all')">View all notifications</BaseButton>
                </template>
            </EmptyState>
        </div>

        <div class="mt-6">
            <BasePagination :links="notifications.links" />
        </div>
    </DashboardLayout>
</template>
