<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ProgressRing from '@/Components/ProgressRing.vue';
import StatCard from '@/Components/StatCard.vue';

// Cycled by index, not tied to any stat's meaning — purely to break up the
// grid visually instead of four identical brand-blue chips in a row.
const statAccents = ['brand', 'emerald', 'amber', 'accent'];
const actionAccents = ['brand', 'emerald', 'amber', 'accent'];

const props = defineProps({
    role: { type: String, required: true },
    roleLabel: { type: String, required: true },
    fieldOffice: { type: Object, default: null },
    stats: { type: Array, required: true },
    memberSummary: { type: Object, default: null },
});

const page = usePage();
const firstName = computed(() => page.props.auth.user?.first_name ?? 'there');

const isApprover = computed(() => ['management', 'field_director'].includes(props.role));
const isAdmin = computed(() => ['super_admin', 'esd_admin', 'fo_admin'].includes(props.role));
const isMember = computed(() => props.role === 'member');

const approvalCopy = computed(() =>
    props.role === 'management'
        ? {
              title: 'No pending Certificate of Appreciation approvals',
              description: 'Approval requests initiated by Testing Center Admins will appear here for your action.',
          }
        : {
              title: 'No pending approvals',
              description: 'Requests for Certificates of Appearance and Designation Orders from your Testing Center will appear here.',
          },
);

const quickActions = computed(() => {
    if (props.role === 'fo_admin') {
        return [
            { label: 'Add PROCTAD Member', icon: 'user-plus', href: '/members/create' },
            { label: 'Scan QR Code', icon: 'qr-code', href: '/scanner' },
            { label: 'Request Certificate Issuance', icon: 'paper-airplane', href: '/certificates' },
            { label: 'Update Signatories', icon: 'identification', href: '/signatories' },
        ];
    }
    return [
        { label: 'PROCTAD Registry', icon: 'users', href: '/members' },
        { label: 'Generate Report', icon: 'chart-bar', href: '/reports' },
        { label: 'Scan QR Code', icon: 'qr-code', href: '/scanner' },
        { label: 'Manage User Accounts', icon: 'user-group', href: '/users' },
    ];
});

const eligibilityPercent = computed(() => {
    if (!props.memberSummary?.requirements_total) return 0;
    return Math.round((props.memberSummary.requirements_complied / props.memberSummary.requirements_total) * 100);
});

// Static class map — Tailwind's content scanner needs literal class strings.
const actionChipClasses = {
    brand: 'bg-brand-50 text-brand-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    accent: 'bg-accent-50 text-accent-600',
};
const actionChip = (accent) => actionChipClasses[accent] ?? actionChipClasses.brand;
</script>

<template>
    <Head title="Dashboard" />

    <DashboardLayout>
        <DashboardPageHeader :title="`Welcome back, ${firstName}`" subtitle="Here's an overview of your PROCTAD workspace.">
            <template #actions>
                <BaseBadge variant="brand">{{ roleLabel }}</BaseBadge>
                <BaseBadge v-if="fieldOffice" variant="neutral">
                    <AppIcon name="building-office" class="h-3.5 w-3.5" />
                    {{ fieldOffice.name }}
                </BaseBadge>
            </template>
        </DashboardPageHeader>

        <!-- Stat cards -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="(stat, i) in stats"
                :key="stat.label"
                :label="stat.label"
                :value="stat.value.toLocaleString()"
                :icon="stat.icon"
                :hint="stat.hint"
                :accent="statAccents[i % statAccents.length]"
            />
        </div>

        <!-- Role-specific panel -->
        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
            <!-- Approvers: pending approvals queue -->
            <section v-if="isApprover" aria-labelledby="approvals-heading">
                <h2 id="approvals-heading" class="text-lg font-semibold text-slate-900">
                    Pending Approvals
                </h2>
                <div class="mt-4">
                    <EmptyState
                        icon="clipboard-check"
                        :title="approvalCopy.title"
                        :description="approvalCopy.description"
                    />
                </div>
            </section>

            <!-- Admins: quick actions -->
            <section v-else-if="isAdmin" aria-labelledby="actions-heading">
                <h2 id="actions-heading" class="text-lg font-semibold text-slate-900">
                    Quick Actions
                </h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <component
                        :is="action.href ? Link : 'div'"
                        v-for="(action, i) in quickActions"
                        :key="action.label"
                        :href="action.href"
                        class="group flex items-center gap-3 rounded-xl border bg-white p-4 transition-all duration-200"
                        :class="action.href
                            ? 'border-slate-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md'
                            : 'cursor-not-allowed border-dashed border-slate-300 opacity-70'"
                        :title="action.href ? action.label : `${action.label} — coming soon`"
                    >
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-transform duration-200 group-hover:scale-105"
                            :class="actionChip(actionAccents[i % actionAccents.length])"
                        >
                            <AppIcon :name="action.icon" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">{{ action.label }}</p>
                            <p v-if="!action.href" class="text-xs text-slate-400">Coming soon</p>
                        </div>
                    </component>
                </div>
            </section>

            <!-- Members: personal PROCTAD summary -->
            <section v-else-if="isMember" aria-labelledby="member-heading">
                <h2 id="member-heading" class="text-lg font-semibold text-slate-900">
                    My PROCTAD
                </h2>
                <div class="mt-4">
                    <EmptyState
                        v-if="!memberSummary"
                        icon="identification"
                        title="No PROCTAD record linked to your account"
                        description="Your Testing Center has not yet registered you in the PROCTAD registry, or your registry record uses a different email address. Please contact your Testing Center."
                    />
                    <div v-else class="grid gap-4 lg:grid-cols-3">
                        <!-- Identity -->
                        <Link href="/my/profile" class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-white ring-4 ring-brand-50">
                                <img v-if="memberSummary.photo_url" :src="memberSummary.photo_url" :alt="memberSummary.name" class="h-full w-full object-cover">
                                <AppIcon v-else name="user-circle" class="h-8 w-8" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ memberSummary.name }}</p>
                                <p class="font-mono text-xs text-slate-400">{{ memberSummary.proctad_id }}</p>
                                <BaseBadge class="mt-1.5" size="xs" :variant="memberSummary.status_variant">{{ memberSummary.status_label }}</BaseBadge>
                            </div>
                        </Link>

                        <!-- Eligibility progress -->
                        <Link href="/my/profile" class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md">
                            <ProgressRing :percent="eligibilityPercent" />
                            <div class="min-w-0">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Eligibility Requirements</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ memberSummary.requirements_complied }} / {{ memberSummary.requirements_total }} complied
                                </p>
                            </div>
                        </Link>

                        <!-- Latest activity -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Latest Activity</p>
                            <template v-if="memberSummary.latest_certificate || memberSummary.latest_service">
                                <Link
                                    v-if="memberSummary.latest_certificate"
                                    href="/my/certificates"
                                    class="mt-1.5 flex items-center gap-2 text-sm text-slate-700 hover:text-brand-700 hover:underline"
                                >
                                    {{ memberSummary.latest_certificate.type_label }}
                                    <BaseBadge size="xs" :variant="memberSummary.latest_certificate.status_variant">
                                        {{ memberSummary.latest_certificate.status_label }}
                                    </BaseBadge>
                                </Link>
                                <Link
                                    v-if="memberSummary.latest_service"
                                    href="/my/service-history"
                                    class="mt-1.5 block text-xs text-slate-500 hover:text-brand-700 hover:underline"
                                >
                                    Served: {{ memberSummary.latest_service.title }} — {{ memberSummary.latest_service.date }}
                                </Link>
                            </template>
                            <p v-else class="mt-1.5 text-sm text-slate-400">No activity yet.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
