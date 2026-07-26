<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BasePagination from '@/Components/BasePagination.vue';
import BaseTable from '@/Components/BaseTable.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, required: true },
    fieldOffices: { type: Array, required: true },
    assignableFieldOffices: { type: Array, required: true },
    unlinkedMemberCount: { type: Number, required: true },
    can: { type: Object, required: true },
});

const currentUserId = computed(() => usePage().props.auth.user.id);

/*
 * Region-wide reach comes from the role, never from leaving this blank — the
 * placeholder used to read "Region-wide / none", which invited the opposite
 * reading. Blank on a Field Office role is the harmful case: their jurisdiction
 * is derived entirely from the office, so they end up seeing nothing.
 */
const fieldOfficeHint = 'Required for Field Office roles — without one they see no records. '
    + 'Regional roles are region-wide through the role itself and may have none.';

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const fieldOfficeId = ref(props.filters.field_office_id ?? '');
const linked = ref(props.filters.linked ?? '');
const loading = ref(false);

let debounce = null;
const applyFilters = () => router.get('/users', {
    search: search.value || undefined,
    role: role.value || undefined,
    field_office_id: fieldOfficeId.value || undefined,
    linked: linked.value || undefined,
}, {
    preserveState: true,
    replace: true,
    only: ['users', 'filters'],
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
});

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});
watch([role, fieldOfficeId, linked], applyFilters);

const showUnlinkedOnly = () => {
    linked.value = 'unlinked';
    applyFilters();
};

/* --- Create --- */
const showCreate = ref(false);
const createForm = useForm({
    first_name: '', middle_name: '', last_name: '', suffix: '',
    email: '', username: '', role: '', field_office_id: '',
});

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreate.value = true;
};

const submitCreate = () => createForm.post('/users', {
    preserveScroll: true,
    onSuccess: () => (showCreate.value = false),
});

/* --- Edit name / role / field office / active --- */
const editing = ref(null);
const editForm = useForm({
    first_name: '', middle_name: '', last_name: '', suffix: '',
    role: '', field_office_id: '', is_active: true,
});

const openEdit = (user) => {
    editing.value = user;
    editForm.clearErrors();
    editForm.first_name = user.first_name ?? '';
    editForm.middle_name = user.middle_name ?? '';
    editForm.last_name = user.last_name ?? '';
    editForm.suffix = user.suffix ?? '';
    editForm.role = user.role;
    editForm.field_office_id = user.field_office?.id ?? '';
    editForm.is_active = user.is_active;
};

const submitEdit = () => editForm.put(`/users/${editing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
});

/*
 * --- Password reset ---
 *
 * Confirmed first: this emails a real person a reset link and, on some setups,
 * invalidates the password they are currently using. It sat one stray click
 * away from the Edit button, with no way back.
 */
const resettingUser = ref(null);
const resetForm = useForm({});

const confirmReset = () => resetForm.post(`/users/${resettingUser.value.id}/send-password-reset`, {
    preserveScroll: true,
    onSuccess: () => (resettingUser.value = null),
});
</script>

<template>
    <Head title="User Accounts" />

    <DashboardLayout>
        <DashboardPageHeader
            title="User Accounts"
            subtitle="Staff accounts. New accounts receive an emailed link to set their own password."
        >
            <template v-if="can.create" #actions>
                <BaseButton variant="primary" size="sm" icon="user-plus" @click="openCreate">
                    Add User
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <!-- Unlinked member accounts worklist -->
        <div v-if="unlinkedMemberCount > 0" class="mt-6">
            <button type="button" class="block w-full text-left" @click="showUnlinkedOnly">
                <StatCard
                    compact
                    label="Awaiting PROCTAD Registration"
                    :value="unlinkedMemberCount"
                    icon="exclamation-triangle"
                    accent="amber"
                    hint="Self-registered member accounts with no linked PROCTAD record yet — click to filter."
                />
            </button>
        </div>

        <!-- Filters -->
        <BaseCard padding="sm" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <TextInput v-model="search" label="Search" placeholder="Name, email, or username" />
            <SelectInput
                v-model="role"
                label="Role"
                placeholder="All roles"
                :options="[{ value: '', label: 'All roles' }, ...roles]"
            />
            <SelectInput
                v-model="fieldOfficeId"
                label="Field Office"
                placeholder="All field offices"
                :options="[{ value: '', label: 'All field offices' }, ...fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))]"
            />
            <SelectInput
                v-model="linked"
                label="PROCTAD Record"
                placeholder="All member accounts"
                :options="[
                    { value: '', label: 'All member accounts' },
                    { value: 'unlinked', label: 'Awaiting registration' },
                ]"
            />
        </BaseCard>

        <!-- Mobile (below md): a card per user so the edit/reset/register actions stay on screen. -->
        <div v-if="users.data.length" class="mt-6 space-y-3 md:hidden" :class="{ 'opacity-50': loading }">
            <BaseCard
                v-for="user in users.data"
                :key="`m-${user.id}`"
                padding="sm"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-900" :title="user.name">{{ user.name }}</p>
                        <p class="truncate text-xs text-slate-500" :title="user.email">{{ user.email }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <BaseBadge :variant="user.is_active ? 'success' : 'neutral'">
                            {{ user.is_active ? 'Active' : 'Deactivated' }}
                        </BaseBadge>
                        <BaseBadge v-if="user.must_change_password" variant="warning">Password pending</BaseBadge>
                        <BaseBadge v-if="user.has_member_record === false" variant="warning">No PROCTAD record</BaseBadge>
                    </div>
                </div>
                <dl class="mt-2 space-y-1 text-xs text-slate-500">
                    <div class="flex gap-1"><dt class="font-medium text-slate-400">Role:</dt><dd class="text-slate-600">{{ user.role_label }}</dd></div>
                    <div v-if="user.field_office?.name" class="flex gap-1"><dt class="font-medium text-slate-400">Field Office:</dt><dd class="text-slate-600">{{ user.field_office.name }}</dd></div>
                    <div class="flex gap-1"><dt class="font-medium text-slate-400">Last login:</dt><dd class="text-slate-600">{{ user.last_login_at ?? 'Never' }}</dd></div>
                </dl>
                <div class="mt-3 flex flex-wrap gap-1 border-t border-slate-100 pt-3">
                    <IconButton
                        v-if="user.has_member_record === false"
                        icon="user-plus"
                        label="Register as PROCTAD Member"
                        :href="`/members`"
                    />
                    <IconButton icon="pencil" label="Edit" @click="openEdit(user)" />
                    <IconButton icon="key" label="Reset Password" :disabled="resetForm.processing" @click="resettingUser = user" />
                </div>
            </BaseCard>
        </div>

        <!-- Results -->
        <BaseTable
            v-if="loading || users.data.length"
            class="mt-6 hidden md:block"
            :loading="loading"
            :skeleton-columns="6"
            :columns="[
                { label: 'Name' },
                { label: 'Role' },
                { label: 'Field Office', class: 'hidden xl:table-cell' },
                { label: 'Last Login', class: 'hidden md:table-cell' },
                { label: 'Status' },
                { label: 'Actions', align: 'center' },
            ]"
        >
                    <tr v-for="user in users.data" :key="user.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="max-w-[12rem] px-3 py-2 sm:max-w-[14rem]">
                            <p class="truncate font-medium text-slate-900" :title="user.name">{{ user.name }}</p>
                            <p class="truncate text-xs text-slate-500" :title="user.email">{{ user.email }}</p>
                            <p class="truncate text-xs text-slate-400 xl:hidden">{{ user.field_office?.name ?? '—' }}</p>
                        </td>
                        <td class="max-w-[8rem] truncate px-3 py-2 text-slate-600">{{ user.role_label }}</td>
                        <td class="hidden max-w-[10rem] truncate px-3 py-2 text-slate-600 xl:table-cell" :title="user.field_office?.name ?? ''">{{ user.field_office?.name ?? '—' }}</td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 md:table-cell">{{ user.last_login_at ?? 'Never' }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-col gap-1 items-start">
                                <BaseBadge :variant="user.is_active ? 'success' : 'neutral'">
                                    {{ user.is_active ? 'Active' : 'Deactivated' }}
                                </BaseBadge>
                                <BaseBadge v-if="user.must_change_password" variant="warning">Password pending</BaseBadge>
                                <BaseBadge v-if="user.has_member_record === false" variant="warning">No PROCTAD record</BaseBadge>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div class="inline-flex gap-1">
                                <IconButton
                                    v-if="user.has_member_record === false"
                                    icon="user-plus"
                                    label="Register as PROCTAD Member"
                                    :href="`/members`"
                                />
                                <IconButton icon="pencil" label="Edit" @click="openEdit(user)" />
                                <IconButton
                                    icon="key"
                                    label="Reset Password"
                                    :disabled="resetForm.processing"
                                    @click="resettingUser = user"
                                />
                            </div>
                        </td>
                    </tr>
        </BaseTable>

        <div v-else class="mt-6">
            <EmptyState icon="user-group" title="No users found" description="No accounts match your search or filters." />
        </div>

        <div class="mt-6">
            <BasePagination :links="users.links" />
        </div>

        <!-- Reset password confirmation -->
        <BaseModal :show="!!resettingUser" title="Send password reset" @close="resettingUser = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Email a password reset link to <strong>{{ resettingUser?.name }}</strong>
                at <span class="font-medium text-slate-800">{{ resettingUser?.email }}</span>?
            </p>
            <p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                They will be asked to choose a new password the next time they sign in.
                Only do this if they have asked for it — an unexpected reset email is
                indistinguishable from a phishing attempt.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="resettingUser = null">Cancel</BaseButton>
                <BaseButton size="sm" icon="envelope" :loading="resetForm.processing" @click="confirmReset">
                    Send reset link
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Create modal -->
        <BaseModal :show="showCreate" title="Add User" @close="showCreate = false">
            <form id="user-create-form" class="space-y-4" novalidate @submit.prevent="submitCreate">
                <div class="grid gap-4 sm:grid-cols-2">
                    <TextInput v-model="createForm.first_name" label="First Name" required :error="createForm.errors.first_name" />
                    <TextInput v-model="createForm.last_name" label="Last Name" required :error="createForm.errors.last_name" />
                    <TextInput v-model="createForm.middle_name" label="Middle Name" optional :error="createForm.errors.middle_name" />
                    <TextInput v-model="createForm.suffix" label="Suffix" optional :error="createForm.errors.suffix" />
                </div>
                <TextInput v-model="createForm.email" label="Email Address" type="email" required :error="createForm.errors.email" />
                <TextInput v-model="createForm.username" label="Username" optional :error="createForm.errors.username" />
                <SelectInput v-model="createForm.role" label="Role" required :options="roles" :error="createForm.errors.role" />
                <SelectInput
                    v-model="createForm.field_office_id"
                    label="Field Office"
                    optional
                    placeholder="None"
                    :options="assignableFieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))"
                    :error="createForm.errors.field_office_id"
                    :hint="fieldOfficeHint"
                />
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showCreate = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="user-create-form"
                    variant="primary"
                    size="sm"
                    :loading="createForm.processing"
                    :disabled="createForm.processing"
                >
                    Add User
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Edit modal -->
        <BaseModal :show="!!editing" title="Edit User" @close="editing = null">
            <form id="user-edit-form" class="space-y-4" novalidate @submit.prevent="submitEdit">
                <!-- Email and username identify the account for login and password
                     resets, so they stay read-only here; correcting a name must not
                     be a chance to change who can sign in. -->
                <p class="text-sm text-slate-400">{{ editing?.email }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <TextInput v-model="editForm.first_name" label="First Name" required :error="editForm.errors.first_name" />
                    <TextInput v-model="editForm.last_name" label="Last Name" required :error="editForm.errors.last_name" />
                    <TextInput v-model="editForm.middle_name" label="Middle Name" optional :error="editForm.errors.middle_name" />
                    <TextInput v-model="editForm.suffix" label="Suffix" optional :error="editForm.errors.suffix" />
                </div>
                <SelectInput v-model="editForm.role" label="Role" required :options="roles" :error="editForm.errors.role" />
                <SelectInput
                    v-model="editForm.field_office_id"
                    label="Field Office"
                    optional
                    placeholder="None"
                    :options="assignableFieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))"
                    :error="editForm.errors.field_office_id"
                    :hint="fieldOfficeHint"
                />
                <CheckboxInput v-model="editForm.is_active" :disabled="editing?.id === currentUserId">
                    Active
                    <span v-if="editing?.id === currentUserId" class="text-xs text-slate-400">(you cannot deactivate your own account)</span>
                </CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="editing = null">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="user-edit-form"
                    variant="primary"
                    size="sm"
                    :loading="editForm.processing"
                    :disabled="editForm.processing"
                >
                    Save Changes
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
