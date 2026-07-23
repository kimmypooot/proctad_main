<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BarChart from '@/Components/Charts/BarChart.vue';
import LineAreaChart from '@/Components/Charts/LineAreaChart.vue';
import StatusBreakdownBar from '@/Components/Charts/StatusBreakdownBar.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ProgressRing from '@/Components/ProgressRing.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';

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
    analytics: { type: Object, default: null },
    pendingApprovals: { type: Object, default: null },
});

const page = usePage();
const firstName = computed(() => page.props.auth.user?.first_name ?? 'there');

// The two regional directors share every dashboard rule; they differ only in
// which REC seat they hold (see UserRole::isManagement on the server).
const isManagement = computed(() => ['director_iv', 'director_iii'].includes(props.role));
const isApprover = computed(() => isManagement.value || props.role === 'field_director');
const isAdmin = computed(() => ['super_admin', 'esd_admin', 'fo_admin'].includes(props.role));
const isMember = computed(() => props.role === 'member');

const approvalCopy = computed(() =>
    isManagement.value
        ? {
              title: 'No pending Certificate of Appreciation approvals',
              description: 'Approval requests initiated by Field Office Admins will appear here for your action.',
          }
        : {
              title: 'No pending approvals',
              description: 'Requests for Certificates of Appearance and Designation Orders from your Field Office will appear here.',
          },
);

const quickActions = computed(() => {
    if (props.role === 'fo_admin') {
        return [
            { label: 'Member Registry', icon: 'users', href: '/members' },
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
        { label: 'Examinations', icon: 'calendar', href: '/examinations' },
        { label: 'Evaluation Monitoring', icon: 'document-check', href: '/evaluation-monitoring' },
        { label: 'Certificate Approvals', icon: 'clipboard-check', href: '/approvals' },
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

/* --- Admin analytics --- */
const secondaryStatAccents = ['brand', 'emerald', 'amber', 'accent'];

const feedFieldOfficeId = ref(props.analytics?.selectedFieldOfficeId ?? '');
const feedLoading = ref(false);

const applyFeedFilter = () => {
    router.get('/dashboard', {
        field_office_id: feedFieldOfficeId.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['analytics'],
        onStart: () => (feedLoading.value = true),
        onFinish: () => (feedLoading.value = false),
    });
};
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
                :href="stat.href"
                :accent="statAccents[i % statAccents.length]"
            />
        </div>

        <!-- Admin analytics -->
        <template v-if="analytics">
            <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    v-for="(stat, i) in analytics.secondaryStats"
                    :key="stat.label"
                    compact
                    :label="stat.label"
                    :value="stat.value.toLocaleString()"
                    :icon="stat.icon"
                    :href="stat.href"
                    :accent="secondaryStatAccents[i % secondaryStatAccents.length]"
                />
                <StatCard
                    v-if="analytics.evaluationCompliance"
                    compact
                    label="Evaluation Compliance"
                    :value="`${analytics.evaluationCompliance.percentage}%`"
                    icon="document-check"
                    href="/evaluation-monitoring"
                    :accent="secondaryStatAccents[analytics.secondaryStats.length % secondaryStatAccents.length]"
                />
            </div>

            <div class="mt-4 grid gap-4 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <LineAreaChart
                        title="New Registrations — Last 6 Months"
                        :labels="analytics.registrationTrend.map((p) => p.label)"
                        :values="analytics.registrationTrend.map((p) => p.value)"
                    />
                </div>
                <StatusBreakdownBar title="Member Status Breakdown" :segments="analytics.statusBreakdown" />
            </div>

            <div class="mt-4 grid gap-4 xl:grid-cols-3">
                <!-- Upcoming examinations with assignment-confirmation progress -->
                <div class="xl:col-span-2 rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900">Upcoming Examinations</h3>
                    <div v-if="!analytics.upcomingExaminations.length" class="mt-4">
                        <EmptyState icon="calendar" title="No upcoming examinations" description="Scheduled exams will appear here." />
                    </div>
                    <ul v-else class="mt-3 divide-y divide-slate-100">
                        <li v-for="exam in analytics.upcomingExaminations" :key="exam.id" class="flex items-center justify-between gap-4 py-2.5">
                            <div class="min-w-0">
                                <Link href="/examinations" class="truncate text-sm font-medium text-slate-900 hover:text-brand-700 hover:underline">
                                    {{ exam.title }}
                                </Link>
                                <p class="text-xs text-slate-500">{{ exam.exam_date }}</p>
                            </div>
                            <BaseBadge :variant="exam.assignments_confirmed === exam.assignments_total && exam.assignments_total > 0 ? 'success' : 'warning'">
                                {{ exam.assignments_confirmed }} / {{ exam.assignments_total }} confirmed
                            </BaseBadge>
                        </li>
                    </ul>
                </div>
                <StatusBreakdownBar
                    title="Performance Ratings"
                    :segments="analytics.performanceRatingBreakdown"
                    empty-label="No rated assignments yet."
                />
            </div>

            <div v-if="analytics.registrationsByFieldOffice" class="mt-4">
                <BarChart title="Registrations by Field Office (All-Time)" :items="analytics.registrationsByFieldOffice" />
            </div>

            <!-- Recent registrations -->
            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-900">Recent Registrations</h3>
                    <div v-if="analytics.fieldOffices" class="w-56">
                        <SelectInput
                            v-model="feedFieldOfficeId"
                            label="Field Office"
                            placeholder="All Field Offices"
                            :options="[{ value: '', label: 'All Field Offices (Overall)' }, ...analytics.fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))]"
                            @update:model-value="applyFeedFilter"
                        />
                    </div>
                </div>
                <p v-if="analytics.regionTotalThisMonth !== null" class="mt-1 text-xs text-slate-400">
                    Region-wide, {{ analytics.regionTotalThisMonth }} member(s) registered this month.
                </p>

                <div v-if="!feedLoading && !analytics.recentRegistrations.length" class="mt-4">
                    <EmptyState icon="users" title="No registrations yet" description="New member registrations will appear here." />
                </div>
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Member</th>
                                <th class="hidden py-2 pr-3 font-semibold sm:table-cell">Field Office</th>
                                <th class="py-2 pr-3 font-semibold">Status</th>
                                <th class="py-2 pl-3 text-right font-semibold">Registered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <TableSkeleton v-if="feedLoading" :columns="4" />
                            <tr v-for="member in feedLoading ? [] : analytics.recentRegistrations" :key="member.id">
                                <td class="py-2.5 pr-3">
                                    <Link href="/members" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">
                                        {{ member.name }}
                                    </Link>
                                    <p class="font-mono text-xs text-brand-700">{{ member.proctad_id }}</p>
                                </td>
                                <td class="hidden py-2.5 pr-3 text-slate-500 sm:table-cell">{{ member.field_office }}</td>
                                <td class="py-2.5 pr-3">
                                    <BaseBadge :variant="member.status_variant">{{ member.status_label }}</BaseBadge>
                                </td>
                                <td class="py-2.5 pl-3 text-right text-xs text-slate-400">{{ member.registered_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Role-specific panel -->
        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
            <!-- Approvers: pending approvals queue -->
            <section v-if="isApprover" aria-labelledby="approvals-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="approvals-heading" class="text-lg font-semibold text-slate-900">
                        Pending Approvals
                    </h2>
                    <BaseBadge v-if="pendingApprovals?.total" variant="warning">{{ pendingApprovals.total }} waiting</BaseBadge>
                </div>

                <div class="mt-4">
                    <EmptyState
                        v-if="!pendingApprovals?.total"
                        icon="clipboard-check"
                        :title="approvalCopy.title"
                        :description="approvalCopy.description"
                    />
                    <ul v-else class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
                        <li v-for="item in pendingApprovals.items" :key="item.id" class="flex items-center justify-between gap-4 px-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ item.member_name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ item.type_label }}
                                    <span v-if="item.field_office"> &middot; {{ item.field_office }}</span>
                                </p>
                            </div>
                            <p class="shrink-0 text-xs text-slate-400">{{ item.requested_at }}</p>
                        </li>
                    </ul>
                    <div class="mt-3 text-right">
                        <Link href="/approvals" class="text-sm font-medium text-brand-700 hover:underline">
                            View all {{ pendingApprovals.total }} pending &rarr;
                        </Link>
                    </div>
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
                        description="Your Field Office has not yet registered you in the PROCTAD registry, or your registry record uses a different email address. Please contact your Field Office."
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
