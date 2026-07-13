<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import AppLogo from '@/Components/AppLogo.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import ToastContainer from '@/Components/ToastContainer.vue';
import Tooltip from '@/Components/Tooltip.vue';
import { useToasts } from '@/Composables/useToasts';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { push: pushToast } = useToasts();
watch(() => page.props.flash, (flash) => {
    if (flash?.success) pushToast('success', flash.success);
    if (flash?.error) pushToast('error', flash.error);
}, { immediate: true });
const sidebarOpen = ref(false);
const sidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
const notificationsOpen = ref(false);
const userMenuOpen = ref(false);
const notifications = computed(() => page.props.notifications ?? { unread_count: 0, items: [] });

// Single source of truth for the desktop/mobile split — avoids re-reading
// window.innerWidth at click time and lets us react to viewport changes.
const desktopQuery = window.matchMedia('(min-width: 1024px)');
const isDesktop = ref(desktopQuery.matches);
const handleDesktopChange = (event) => {
    isDesktop.value = event.matches;
    if (event.matches) sidebarOpen.value = false;
};
desktopQuery.addEventListener('change', handleDesktopChange);
onBeforeUnmount(() => desktopQuery.removeEventListener('change', handleDesktopChange));

const toggleSidebar = () => {
    if (isDesktop.value) {
        sidebarCollapsed.value = !sidebarCollapsed.value;
        localStorage.setItem('sidebar_collapsed', sidebarCollapsed.value);
    } else {
        sidebarOpen.value = !sidebarOpen.value;
    }
};

const closeMobileSidebar = () => {
    sidebarOpen.value = false;
};

watch(sidebarOpen, (open) => {
    document.body.classList.toggle('overflow-hidden', open);
});

onBeforeUnmount(() => {
    document.body.classList.remove('overflow-hidden');
});

const openNotification = (notification) => {
    notificationsOpen.value = false;

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

const markAllNotificationsRead = () => {
    router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
};

const roleLabels = {
    super_admin: 'Super Administrator',
    esd_admin: 'Administrator (ESD)',
    management: 'Management',
    field_director: 'Field Director / Caretaker',
    fo_admin: 'Testing Center Staff',
    member: 'PROCTAD Member',
};

const navByRole = {
    member: [
        {
            section: 'Overview',
            items: [
                { label: 'Dashboard', icon: 'home', href: '/dashboard' },
            ],
        },
        {
            section: 'Profile',
            items: [
                { label: 'My Profile', icon: 'user-circle', href: '/my/profile' },
                { label: 'My QR Code', icon: 'qr-code', href: '/my/qr-code' },
            ],
        },
        {
            section: 'Records',
            items: [
                { label: 'Service History', icon: 'clock', href: '/my/service-history' },
                { label: 'Certificates', icon: 'document-check', href: '/my/certificates' },
                { label: 'Trainings', icon: 'academic-cap', href: '/my/trainings' },
            ],
        },
    ],
    fo_admin: [
        {
            section: 'Overview',
            items: [
                { label: 'Dashboard', icon: 'home', href: '/dashboard' },
            ],
        },
        {
            section: 'Management',
            items: [
                { label: 'PROCTAD Members', icon: 'users', href: '/members' },
                { label: 'Other Examination Personnel', icon: 'user-group', href: '/other-examination-personnel' },
                { label: 'Blacklisted Test Admins', icon: 'exclamation-triangle', href: '/blacklists' },
            ],
        },
        {
            section: 'Examinations',
            items: [
                { label: 'Examinations', icon: 'calendar', href: '/examinations' },
                { label: 'Certificates', icon: 'paper-airplane', href: '/certificates' },
                { label: 'Trainings', icon: 'academic-cap', href: '/trainings' },
            ],
        },
        {
            section: 'Configuration',
            items: [
                { label: 'Schools', icon: 'building-office', href: '/schools' },
                { label: 'Signatories', icon: 'identification', href: '/signatories' },
            ],
        },
        {
            section: 'Tools',
            items: [
                { label: 'QR Scanner', icon: 'qr-code', href: '/scanner' },
            ],
        },
        {
            section: 'Reports',
            items: [
                { label: 'Reports & Statistics', icon: 'chart-bar', href: '/reports' },
                { label: 'Evaluation Monitoring', icon: 'clipboard-check', href: '/evaluation-monitoring' },
            ],
        },
    ],
    field_director: [
        {
            section: 'Overview',
            items: [
                { label: 'Dashboard', icon: 'home', href: '/dashboard' },
            ],
        },
        {
            section: 'Approvals',
            items: [
                { label: 'Pending Approvals', icon: 'clipboard-check', href: '/approvals' },
            ],
        },
        {
            section: 'Records',
            items: [
                { label: 'Members', icon: 'users', href: '/members' },
                { label: 'Certificates', icon: 'document-check', href: '/certificates' },
                { label: 'Service Records', icon: 'document-text', href: '/examinations' },
                { label: 'Blacklisted Test Admins', icon: 'exclamation-triangle', href: '/blacklists' },
            ],
        },
        {
            section: 'Monitoring',
            items: [
                { label: 'Audit Trail', icon: 'eye', href: '/audit-logs' },
            ],
        },
        {
            section: 'Reports',
            items: [
                { label: 'Reports & Statistics', icon: 'chart-bar', href: '/reports' },
                { label: 'Evaluation Monitoring', icon: 'clipboard-check', href: '/evaluation-monitoring' },
            ],
        },
    ],
    management: [
        {
            section: 'Overview',
            items: [
                { label: 'Dashboard', icon: 'home', href: '/dashboard' },
            ],
        },
        {
            section: 'Approvals',
            items: [
                { label: 'Appreciation Approvals', icon: 'clipboard-check', href: '/approvals' },
            ],
        },
        {
            section: 'Reports',
            items: [
                { label: 'Reports & Statistics', icon: 'chart-bar', href: '/reports' },
                { label: 'Evaluation Monitoring', icon: 'clipboard-check', href: '/evaluation-monitoring' },
            ],
        },
        {
            section: 'Monitoring',
            items: [
                { label: 'Audit Trail', icon: 'eye', href: '/audit-logs' },
            ],
        },
    ],
    esd_admin: [
        {
            section: 'Overview',
            items: [
                { label: 'Dashboard', icon: 'home', href: '/dashboard' },
            ],
        },
        {
            section: 'Approvals',
            items: [
                { label: 'Approvals', icon: 'clipboard-check', href: '/approvals' },
            ],
        },
        {
            section: 'Registry',
            items: [
                { label: 'PROCTAD Registry', icon: 'users', href: '/members' },
                { label: 'Other Examination Personnel', icon: 'user-group', href: '/other-examination-personnel' },
                { label: 'Blacklisted Test Admins', icon: 'exclamation-triangle', href: '/blacklists' },
            ],
        },
        {
            section: 'Examinations',
            items: [
                { label: 'Examinations', icon: 'calendar', href: '/examinations' },
                { label: 'Certificates', icon: 'document-check', href: '/certificates' },
                { label: 'Exam Types', icon: 'clipboard-check', href: '/exam-types' },
                { label: 'Trainings', icon: 'academic-cap', href: '/trainings' },
            ],
        },
        {
            section: 'Configuration',
            items: [
                { label: 'Schools', icon: 'building-office', href: '/schools' },
                { label: 'Signatories', icon: 'identification', href: '/signatories' },
                { label: 'Testing Centers', icon: 'map-pin', href: '/field-offices' },
                { label: 'Letterheads', icon: 'document-text', href: '/letterheads' },
                { label: 'Fee Management', icon: 'scale', href: '/fee-schedules' },
            ],
        },
        {
            section: 'Communications',
            items: [
                { label: 'Email Templates', icon: 'envelope', href: '/email-templates' },
            ],
        },
        {
            section: 'Administration',
            items: [
                { label: 'User Accounts', icon: 'user-group', href: '/users' },
                { label: 'System Settings', icon: 'cog-6-tooth', href: '/settings' },
                { label: 'QR Scanner', icon: 'qr-code', href: '/scanner' },
            ],
        },
        {
            section: 'Reports',
            items: [
                { label: 'Reports & Statistics', icon: 'chart-bar', href: '/reports' },
                { label: 'Evaluation Monitoring', icon: 'clipboard-check', href: '/evaluation-monitoring' },
            ],
        },
    ],
};

navByRole.super_admin = [
    {
        section: 'Overview',
        items: [
            { label: 'Dashboard', icon: 'home', href: '/dashboard' },
        ],
    },
    {
        section: 'Approvals',
        items: [
            { label: 'Approvals', icon: 'clipboard-check', href: '/approvals' },
        ],
    },
    {
        section: 'Registry',
        items: [
            { label: 'PROCTAD Registry', icon: 'users', href: '/members' },
            { label: 'Other Examination Personnel', icon: 'user-group', href: '/other-examination-personnel' },
            { label: 'Blacklisted Test Admins', icon: 'exclamation-triangle', href: '/blacklists' },
        ],
    },
    {
        section: 'Examinations',
        items: [
            { label: 'Examinations', icon: 'calendar', href: '/examinations' },
            { label: 'Certificates', icon: 'document-check', href: '/certificates' },
            { label: 'Exam Types', icon: 'clipboard-check', href: '/exam-types' },
            { label: 'Trainings', icon: 'academic-cap', href: '/trainings' },
        ],
    },
    {
        section: 'Configuration',
        items: [
            { label: 'Schools', icon: 'building-office', href: '/schools' },
            { label: 'Signatories', icon: 'identification', href: '/signatories' },
            { label: 'Testing Centers', icon: 'map-pin', href: '/field-offices' },
            { label: 'Letterheads', icon: 'document-text', href: '/letterheads' },
            { label: 'Fee Management', icon: 'scale', href: '/fee-schedules' },
        ],
    },
    {
        section: 'Communications',
        items: [
            { label: 'Email Templates', icon: 'envelope', href: '/email-templates' },
        ],
    },
    {
        section: 'Administration',
        items: [
            { label: 'User Accounts', icon: 'user-group', href: '/users' },
            { label: 'QR Scanner', icon: 'qr-code', href: '/scanner' },
        ],
    },
    {
        section: 'Monitoring',
        items: [
            { label: 'Audit Logs', icon: 'eye', href: '/audit-logs' },
        ],
    },
    {
        section: 'Reports',
        items: [
            { label: 'Reports & Statistics', icon: 'chart-bar', href: '/reports' },
            { label: 'Evaluation Monitoring', icon: 'clipboard-check', href: '/evaluation-monitoring' },
            { label: 'Duplicate Members', icon: 'user-group', href: '/reports/duplicate-members' },
        ],
    },
    {
        section: 'System',
        items: [
            { label: 'System Settings', icon: 'cog-6-tooth', href: '/settings' },
        ],
    },
];

const navItems = computed(() => navByRole[user.value?.role] ?? navByRole.member);
const roleLabel = computed(() => roleLabels[user.value?.role] ?? 'User');

const initials = computed(() => {
    const name = (user.value?.name ?? '').trim();
    if (!name) return 'U';
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
});

const confirmingLogout = ref(false);
const loggingOut = ref(false);
const logout = () => {
    loggingOut.value = true;
    router.post('/logout', {}, {
        onFinish: () => {
            loggingOut.value = false;
            confirmingLogout.value = false;
        },
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
        >
            Skip to main content
        </a>

        <!-- Mobile sidebar backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease"
            leave-active-class="transition-opacity duration-200 ease"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
                aria-hidden="true"
                @click="closeMobileSidebar"
            />
        </Transition>

        <!-- Sidebar: off-canvas drawer (<lg), collapsible rail/full (lg+) -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col overflow-hidden bg-brand-900 transition-[transform,width] duration-200 ease-in-out"
            :class="[
                sidebarOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-full',
                'lg:translate-x-0',
                sidebarCollapsed && 'lg:w-16',
            ]"
        >
            <div
                class="flex h-16 shrink-0 items-center justify-between px-5"
                :class="sidebarCollapsed && 'lg:justify-center lg:px-0'"
            >
                <Link href="/dashboard" class="inline-flex rounded-md" aria-label="ProCTAD — Dashboard" title="Dashboard">
                    <AppLogo v-if="isDesktop && sidebarCollapsed" variant="light" compact />
                    <AppLogo v-else variant="light" />
                </Link>
                <button
                    type="button"
                    class="rounded-lg p-1 text-brand-200 hover:text-white lg:hidden"
                    aria-label="Close sidebar"
                    @click="closeMobileSidebar"
                >
                    <AppIcon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            <nav
                class="sidebar-nav mt-4 flex-1 overflow-y-auto overflow-x-hidden px-3 pb-6"
                :class="sidebarCollapsed && 'lg:px-2'"
                aria-label="Dashboard navigation"
            >
                <template v-for="group in navItems" :key="group.section">
                    <div class="mb-5">
                        <p
                            class="mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-widest text-brand-300/70"
                            :class="sidebarCollapsed && 'lg:hidden'"
                        >
                            {{ group.section }}
                        </p>
                        <div class="space-y-1">
                            <template v-for="item in group.items" :key="item.label">
                                <Link
                                    v-if="item.href !== '#'"
                                    :href="item.href"
                                    :title="item.label"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                                    :class="[
                                        sidebarCollapsed && 'lg:justify-center lg:px-2',
                                        $page.url.startsWith(item.href)
                                            ? 'bg-brand-700 text-white'
                                            : 'text-brand-100 hover:bg-brand-800 hover:text-white',
                                    ]"
                                    @click="closeMobileSidebar"
                                >
                                    <AppIcon :name="item.icon" class="h-5 w-5 shrink-0" />
                                    <span :class="sidebarCollapsed && 'lg:hidden'">{{ item.label }}</span>
                                </Link>
                                <span
                                    v-else
                                    class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-brand-300/60"
                                    :class="sidebarCollapsed && 'lg:justify-center lg:px-2'"
                                    :title="`${item.label} — coming soon`"
                                >
                                    <AppIcon :name="item.icon" class="h-5 w-5 shrink-0" />
                                    <span :class="sidebarCollapsed && 'lg:hidden'">{{ item.label }}</span>
                                    <span
                                        class="ml-auto text-[10px] font-semibold uppercase tracking-wide text-brand-300/50"
                                        :class="sidebarCollapsed && 'lg:hidden'"
                                    >Soon</span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>
            </nav>

            <div class="border-t border-brand-800 p-4" :class="sidebarCollapsed && 'lg:px-2'">
                <button
                    type="button"
                    title="Log out"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-brand-100 transition-colors hover:bg-brand-800 hover:text-white"
                    :class="sidebarCollapsed && 'lg:justify-center lg:px-2'"
                    @click="confirmingLogout = true"
                >
                    <AppIcon name="arrow-right-on-rectangle" class="h-4 w-4 shrink-0" />
                    <span :class="sidebarCollapsed && 'lg:hidden'">Log out</span>
                </button>
            </div>
        </aside>

        <!-- Main column -->
        <div
            class="flex min-h-screen flex-col transition-[padding] duration-200 ease-in-out"
            :class="sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64'"
        >
            <!-- Topbar -->
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                <Tooltip v-if="isDesktop" :text="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Toggle sidebar"
                        @click="toggleSidebar"
                    >
                        <AppIcon name="menu" class="h-5 w-5 transition-transform duration-200" :class="(sidebarOpen || sidebarCollapsed) && 'rotate-90'" />
                    </button>
                </Tooltip>
                <button
                    v-else
                    type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    :aria-label="sidebarOpen ? 'Close menu' : 'Open menu'"
                    @click="toggleSidebar"
                >
                    <AppIcon name="menu" class="h-5 w-5 transition-transform duration-200" :class="(sidebarOpen || sidebarCollapsed) && 'rotate-90'" />
                </button>

                <div class="flex items-center gap-4">
                    <div class="relative">
                        <Tooltip text="Notifications">
                        <button
                            type="button"
                            class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Notifications"
                            @click="notificationsOpen = !notificationsOpen"
                        >
                            <AppIcon name="bell" class="h-5 w-5" />
                            <span
                                v-if="notifications.unread_count > 0"
                                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
                            >
                                {{ notifications.unread_count > 9 ? '9+' : notifications.unread_count }}
                            </span>
                        </button>
                        </Tooltip>

                        <div
                            v-if="notificationsOpen"
                            class="fixed inset-0 z-30"
                            @click="notificationsOpen = false"
                        />

                        <div
                            v-if="notificationsOpen"
                            class="absolute right-0 z-40 mt-2 w-80 rounded-lg border border-slate-200 bg-white shadow-lg"
                        >
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                                <p class="text-sm font-semibold text-slate-700">Notifications</p>
                                <button
                                    v-if="notifications.unread_count > 0"
                                    type="button"
                                    class="text-xs font-medium text-brand-700 hover:underline"
                                    @click="markAllNotificationsRead"
                                >
                                    Mark all read
                                </button>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <p v-if="!notifications.items.length" class="px-4 py-6 text-center text-sm text-slate-400">
                                    No notifications yet.
                                </p>
                                <button
                                    v-for="item in notifications.items"
                                    :key="item.id"
                                    type="button"
                                    class="block w-full border-b border-slate-50 px-4 py-3 text-left transition-colors last:border-0 hover:bg-slate-50"
                                    :class="!item.read_at && 'bg-brand-50/60'"
                                    @click="openNotification(item)"
                                >
                                    <p class="text-sm font-medium text-slate-800">{{ item.title }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ item.body }}</p>
                                    <p class="mt-1 text-[11px] text-slate-400">{{ item.created_at }}</p>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg py-1.5 pl-1.5 pr-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100"
                            aria-label="Account menu"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-xs font-semibold text-white">
                                <img v-if="user?.avatar_url" :src="user.avatar_url" :alt="user?.name" class="h-full w-full object-cover">
                                <template v-else>{{ initials }}</template>
                            </span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-sm font-bold leading-none text-slate-800">{{ user?.name }}</span>
                                <span class="mt-0.5 block text-xs leading-none text-slate-400">{{ roleLabel }}</span>
                            </span>
                            <AppIcon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="userMenuOpen && 'rotate-180'" />
                        </button>

                        <div
                            v-if="userMenuOpen"
                            class="fixed inset-0 z-30"
                            @click="userMenuOpen = false"
                        />

                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 z-40 mt-1 w-44 rounded-xl border border-slate-200 bg-white p-1 shadow-lg"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-accent-600 transition-colors hover:bg-accent-50"
                                @click="userMenuOpen = false; confirmingLogout = true"
                            >
                                <AppIcon name="arrow-right-on-rectangle" class="h-4 w-4 shrink-0" />
                                Log out
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Logout confirmation -->
            <BaseModal :show="confirmingLogout" max-width="sm" @close="confirmingLogout = false">
                <div class="text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-accent-50">
                        <AppIcon name="arrow-right-on-rectangle" class="h-6 w-6 text-accent-500" />
                    </span>
                    <h2 class="mt-4 text-base font-semibold text-slate-900">Log out</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Are you sure you want to log out of your ProCTAD account?
                    </p>
                </div>
                <template #footer>
                    <BaseButton class="flex-1" variant="outline" size="sm" @click="confirmingLogout = false">Cancel</BaseButton>
                    <BaseButton class="flex-1" variant="accent" size="sm" :loading="loggingOut" :disabled="loggingOut" @click="logout">
                        Log out
                    </BaseButton>
                </template>
            </BaseModal>

            <ToastContainer />

            <main id="main-content" class="flex-1 p-4 sm:p-6">
                <div class="mx-auto w-full max-w-screen-2xl">
                    <slot />
                </div>
            </main>

            <footer class="px-4 py-4 text-center text-xs text-slate-400 sm:px-6">
                &copy; {{ new Date().getFullYear() }} Civil Service Commission Regional Office VIII — PROCTAD
            </footer>
        </div>
    </div>
</template>

<style scoped>
.sidebar-nav::-webkit-scrollbar {
    width: 5px;
}
.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 3px;
}
.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}
.sidebar-nav {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}
</style>
