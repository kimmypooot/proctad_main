<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ViewMemberModal from './Partials/ViewMemberModal.vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BasePagination from '@/Components/BasePagination.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useToasts } from '@/Composables/useToasts';
import { messageFor, SessionExpiredError } from '@/Composables/useJsonFetch';

const props = defineProps({
    members: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    fieldOffices: { type: Array, default: null },
    statuses: { type: Array, required: true },
    can: { type: Object, required: true },
});

const { push: pushToast } = useToasts();

const viewingMemberId = ref(null);
const showMemberModal = ref(false);

const viewMember = (id) => {
    viewingMemberId.value = id;
    showMemberModal.value = true;
};

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const fieldOfficeId = ref(props.filters.field_office_id ?? '');
const loading = ref(false);

let debounce = null;

const applyFilters = () => {
    router.get('/members', {
        search: search.value || undefined,
        status: status.value || undefined,
        field_office_id: fieldOfficeId.value || undefined,
    }, {
        preserveState: true,
        replace: true,
        only: ['members', 'filters'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});
watch([status, fieldOfficeId], applyFilters);

// Bulk ID card download — selection resets whenever the underlying rows change
// (new filters/page) so a stale selection can't silently apply to a new list.
const selectedIds = ref([]);
watch(() => props.members.data, () => {
    selectedIds.value = [];
});

const allOnPageSelected = computed(() => (
    props.members.data.length > 0 && selectedIds.value.length === props.members.data.length
));

const toggleSelectAll = () => {
    selectedIds.value = allOnPageSelected.value ? [] : props.members.data.map((member) => member.id);
};

const toggleSelected = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(index, 1);
    }
};

const downloadingIds = ref(false);

const readCookie = (name) => {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
};

const downloadSelectedIdCards = async () => {
    if (!selectedIds.value.length || downloadingIds.value) return;

    downloadingIds.value = true;
    try {
        const response = await fetch('/members/id-cards/download-bulk', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/pdf',
                'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({ ids: selectedIds.value }),
        });

        if (response.redirected) throw new SessionExpiredError();

        if (!response.ok) {
            // Surface the server's reason (e.g. the 200-per-batch cap) instead of
            // a generic failure, so the user knows to select fewer members.
            const message = response.headers.get('Content-Type')?.includes('application/json')
                ? (await response.json()).message
                : null;
            throw new Error(message || 'Download failed');
        }

        // Guard against a redirect-to-HTML being saved as a .pdf: fetch() follows
        // redirects transparently, so response.ok alone does not prove this is a PDF.
        if (!response.headers.get('Content-Type')?.includes('application/pdf')) {
            throw new Error('Download failed');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'proctad-ids-bulk.pdf';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch (error) {
        pushToast('error', messageFor(
            error,
            error.message && error.message !== 'Download failed'
                ? error.message
                : 'Could not download ID cards. Please try again.',
        ));
    } finally {
        downloadingIds.value = false;
    }
};
</script>

<template>
    <Head title="PROCTAD Members" />

    <DashboardLayout>
        <DashboardPageHeader title="PROCTAD Members" subtitle="Master registry of accredited test administrators.">
        </DashboardPageHeader>

        <!-- Filters -->
        <div class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
            <TextInput
                v-model="search"
                label="Search"
                placeholder="PROCTAD ID, name, or agency"
            />
            <SelectInput
                v-model="status"
                label="Status"
                placeholder="All statuses"
                :options="[{ value: '', label: 'All statuses' }, ...statuses]"
            />
            <SelectInput
                v-if="fieldOffices"
                v-model="fieldOfficeId"
                label="Testing Center"
                placeholder="All field offices"
                :options="[{ value: '', label: 'All field offices' }, ...fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))]"
            />
        </div>

        <!-- Bulk actions -->
        <div
            v-if="selectedIds.length"
            class="mt-4 flex items-center justify-between rounded-xl border border-brand-200 bg-brand-50 px-4 py-2.5"
        >
            <p class="text-sm font-medium text-brand-800">{{ selectedIds.length }} member{{ selectedIds.length === 1 ? '' : 's' }} selected</p>
            <div class="flex items-center gap-2">
                <BaseButton variant="ghost" size="sm" @click="selectedIds = []">Clear</BaseButton>
                <BaseButton
                    variant="primary"
                    size="sm"
                    :loading="downloadingIds"
                    :disabled="downloadingIds"
                    @click="downloadSelectedIdCards"
                >
                    <AppIcon name="identification" class="h-4 w-4" />
                    Download ID Cards
                </BaseButton>
            </div>
        </div>

        <!-- Results -->
        <div v-if="loading || members.data.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-10 px-3 py-2">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600 focus:ring-2 focus:ring-brand-100"
                                :checked="allOnPageSelected"
                                aria-label="Select all members on this page"
                                @change="toggleSelectAll"
                            >
                        </th>
                        <th class="px-3 py-2">PROCTAD ID</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="hidden px-3 py-2 md:table-cell">Agency</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Testing Center</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Last Exam Served</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <TableSkeleton v-if="loading" :columns="7" />
                    <tr
                        v-for="member in loading ? [] : members.data"
                        :key="member.id"
                        class="transition-colors hover:bg-brand-50/40"
                        :class="selectedIds.includes(member.id) && 'bg-brand-50/60'"
                    >
                        <td class="px-3 py-2">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600 focus:ring-2 focus:ring-brand-100"
                                :checked="selectedIds.includes(member.id)"
                                :aria-label="`Select ${member.name}`"
                                @change="toggleSelected(member.id)"
                            >
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs font-semibold text-brand-700">
                            <button @click="viewMember(member.id)" class="hover:underline">
                                {{ member.proctad_id }}
                            </button>
                        </td>
                        <td class="px-3 py-2 font-medium text-slate-900">
                            <button @click="viewMember(member.id)" class="hover:underline text-left">
                                {{ member.name }}
                            </button>
                            <p class="text-xs font-normal text-slate-400 sm:hidden">{{ member.field_office?.name ?? '—' }}</p>
                        </td>
                        <td class="hidden max-w-[14rem] truncate px-3 py-2 text-slate-600 md:table-cell" :title="member.agency">{{ member.agency }}</td>
                        <td class="hidden max-w-[10rem] truncate px-3 py-2 text-slate-600 sm:table-cell" :title="member.field_office?.name ?? ''">{{ member.field_office?.name ?? '—' }}</td>
                        <td class="hidden px-3 py-2 xl:table-cell">
                            <template v-if="member.last_served">
                                <p class="text-slate-700">{{ member.last_served.title }}</p>
                                <p class="text-xs text-slate-400">{{ member.last_served.date }}</p>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="member.status_variant">{{ member.status_label }}</BaseBadge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="users"
                title="No members found"
                description="No PROCTAD members match your search or filters."
            />
        </div>

        <div class="mt-6">
            <BasePagination :links="members.links" />
        </div>
        <ViewMemberModal
            :show="showMemberModal"
            :member-id="viewingMemberId"
            @close="showMemberModal = false"
        />
    </DashboardLayout>
</template>
