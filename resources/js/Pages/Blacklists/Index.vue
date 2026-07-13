<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BasePagination from '@/Components/BasePagination.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    blacklists: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    fieldOffices: { type: Array, default: null },
    can: { type: Object, required: true },
});

const activeCount = computed(() => props.blacklists.data.filter((b) => b.status === 'active').length);

/* --- Server-side filters (list is paginated) --- */
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const fieldOfficeId = ref(props.filters.field_office_id ?? '');
const loading = ref(false);

let debounce = null;

const applyFilters = () => {
    router.get('/blacklists', {
        search: search.value || undefined,
        status: status.value || undefined,
        field_office_id: fieldOfficeId.value || undefined,
    }, {
        preserveState: true,
        replace: true,
        only: ['blacklists', 'filters'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});
watch([status, fieldOfficeId], applyFilters);

/* --- Blacklist a member --- */
const showCreate = ref(false);
const memberSearch = ref('');
const searchResults = ref([]);
const searching = ref(false);
const searchError = ref(null);
const selectedMember = ref(null);
let searchTimer = null;

const createForm = useForm({ member_id: '', reason: '' });

watch(memberSearch, (value) => {
    clearTimeout(searchTimer);
    searchError.value = null;

    if (value.trim().length < 2) {
        searchResults.value = [];
        return;
    }

    searchTimer = setTimeout(async () => {
        searching.value = true;
        try {
            const params = new URLSearchParams({ q: value.trim() });
            const response = await fetch(`/blacklists/members/search?${params}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Search failed');
            const data = await response.json();
            searchResults.value = data.results;
        } catch {
            searchError.value = 'Something went wrong searching — please try again.';
        } finally {
            searching.value = false;
        }
    }, 300);
});

const selectMember = (member) => {
    selectedMember.value = member;
    createForm.member_id = member.id;
    searchResults.value = [];
    memberSearch.value = '';
};

const clearSelectedMember = () => {
    selectedMember.value = null;
    createForm.member_id = '';
};

const openCreate = () => {
    showCreate.value = true;
    createForm.reset();
    createForm.clearErrors();
    memberSearch.value = '';
    searchResults.value = [];
    searchError.value = null;
    selectedMember.value = null;
};

const submitCreate = () => createForm.post('/blacklists', {
    preserveScroll: true,
    onSuccess: () => (showCreate.value = false),
});

/* --- Lift a blacklist entry --- */
const lifting = ref(null);
const liftForm = useForm({ lift_reason: '' });

const openLift = (blacklist) => {
    lifting.value = blacklist;
    liftForm.reset();
    liftForm.clearErrors();
};

const submitLift = () => liftForm.post(`/blacklists/${lifting.value.id}/lift`, {
    preserveScroll: true,
    onSuccess: () => (lifting.value = null),
});
</script>

<template>
    <Head title="Blacklisted Test Administrators" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Blacklisted Test Administrators"
            subtitle="PROCTAD members barred from examination assignments, with the reason and who acted on it."
        >
            <template v-if="can.create" #actions>
                <BaseButton variant="primary" size="sm" @click="openCreate">
                    <AppIcon name="exclamation-triangle" class="h-4 w-4" />
                    Blacklist a Member
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <StatCard compact label="Currently Blacklisted (this page)" :value="activeCount" icon="exclamation-triangle" accent="accent" />
            <StatCard compact label="Total Records (this page)" :value="blacklists.data.length" icon="clipboard-check" />
        </div>

        <!-- Filters -->
        <div class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
            <TextInput v-model="search" label="Search" placeholder="Name or PROCTAD ID" />
            <SelectInput
                v-model="status"
                label="Status"
                placeholder="All statuses"
                :options="[{ value: '', label: 'All statuses' }, { value: 'active', label: 'Blacklisted' }, { value: 'lifted', label: 'Lifted' }]"
            />
            <SelectInput
                v-if="fieldOffices"
                v-model="fieldOfficeId"
                label="Testing Center"
                placeholder="All field offices"
                :options="[{ value: '', label: 'All field offices' }, ...fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))]"
            />
        </div>

        <!-- Results -->
        <div v-if="loading || blacklists.data.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Member</th>
                        <th class="hidden px-3 py-2 md:table-cell">Testing Center</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="hidden px-3 py-2 lg:table-cell">Blacklisted</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="w-24 px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <TableSkeleton v-if="loading" :columns="6" />
                    <tr v-for="entry in loading ? [] : blacklists.data" :key="entry.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2">
                            <p class="font-medium text-slate-900">{{ entry.member.name }}</p>
                            <p class="font-mono text-xs text-brand-700">{{ entry.member.proctad_id }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 md:table-cell">{{ entry.field_office ?? '—' }}</td>
                        <td class="max-w-xs px-3 py-2">
                            <p class="truncate text-slate-700" :title="entry.reason">{{ entry.reason }}</p>
                            <p v-if="entry.status === 'lifted' && entry.lift_reason" class="mt-1 truncate text-xs text-slate-400" :title="entry.lift_reason">
                                Lifted: {{ entry.lift_reason }}
                            </p>
                        </td>
                        <td class="hidden px-3 py-2 text-slate-600 lg:table-cell">
                            <p>{{ entry.blacklisted_at }}</p>
                            <p class="text-xs text-slate-400">by {{ entry.blacklisted_by }}</p>
                        </td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="entry.status_variant">{{ entry.status_label }}</BaseBadge>
                            <p v-if="entry.status === 'lifted'" class="mt-1 text-xs text-slate-400">
                                {{ entry.lifted_at }} by {{ entry.lifted_by }}
                            </p>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <BaseButton
                                v-if="entry.status === 'active' && entry.can_lift"
                                variant="link"
                                size="sm"
                                @click="openLift(entry)"
                            >
                                Lift
                            </BaseButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="exclamation-triangle"
                title="No blacklist records found"
                description="No blacklisted test administrators match your search or filters."
            />
        </div>

        <div class="mt-6">
            <BasePagination :links="blacklists.links" />
        </div>

        <!-- Blacklist a member modal -->
        <BaseModal :show="showCreate" title="Blacklist a Member" max-width="lg" @close="showCreate = false">
            <form id="blacklist-create-form" class="space-y-4" novalidate @submit.prevent="submitCreate">
                <div>
                    <div v-if="selectedMember">
                        <p class="mb-1.5 text-sm font-medium text-slate-700">
                            Member <span class="text-accent-600" aria-hidden="true">*</span>
                        </p>
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2.5">
                            <span class="text-sm font-medium text-brand-900">{{ selectedMember.label }}</span>
                            <button type="button" class="text-xs font-semibold text-brand-700 hover:underline" @click="clearSelectedMember">
                                Change
                            </button>
                        </div>
                    </div>

                    <div v-else class="relative">
                        <TextInput v-model="memberSearch" label="Member" required placeholder="Type a name to search…" />

                        <div
                            v-if="memberSearch.trim().length >= 2"
                            class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-200"
                        >
                            <p v-if="searching" class="px-3 py-3 text-center text-sm text-slate-400">Searching…</p>
                            <template v-else-if="searchResults.length">
                                <button
                                    v-for="member in searchResults"
                                    :key="member.id"
                                    type="button"
                                    class="flex w-full items-center px-3 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-brand-50"
                                    @click="selectMember(member)"
                                >
                                    {{ member.label }}
                                </button>
                            </template>
                            <p v-else class="px-3 py-3 text-center text-sm text-slate-400">
                                No eligible members match "{{ memberSearch }}".
                            </p>
                        </div>
                        <p v-if="searchError" class="mt-1.5 text-sm text-accent-600" role="alert">{{ searchError }}</p>
                    </div>

                    <p v-if="createForm.errors.member_id" class="mt-1.5 text-sm text-accent-600" role="alert">
                        {{ createForm.errors.member_id }}
                    </p>
                </div>

                <TextArea
                    v-model="createForm.reason"
                    label="Reason for blacklisting"
                    required
                    placeholder="Explain why this member is being blacklisted."
                    :error="createForm.errors.reason"
                />
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showCreate = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="blacklist-create-form"
                    variant="accent"
                    size="sm"
                    :loading="createForm.processing"
                    :disabled="createForm.processing || !createForm.member_id"
                >
                    Blacklist
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Lift blacklist modal -->
        <BaseModal :show="!!lifting" title="Lift Blacklist" @close="lifting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Lifting the blacklist on <strong>{{ lifting?.member.name }}</strong> restores their eligibility for
                examination assignments. Please record a reason.
            </p>
            <TextArea
                v-model="liftForm.lift_reason"
                label="Reason for lifting"
                required
                class="mt-4"
                placeholder="Explain why this blacklist is being lifted."
                :error="liftForm.errors.lift_reason"
            />
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="lifting = null">Cancel</BaseButton>
                <BaseButton
                    variant="primary"
                    size="sm"
                    :loading="liftForm.processing"
                    :disabled="liftForm.processing"
                    @click="submitLift"
                >
                    Lift Blacklist
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
