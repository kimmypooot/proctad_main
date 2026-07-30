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
import StepTabs from '@/Components/StepTabs.vue';
import TextInput from '@/Components/TextInput.vue';
import CreateMemberModal from '@/Pages/Members/Partials/CreateMemberModal.vue';
import ViewMemberModal from '@/Pages/Members/Partials/ViewMemberModal.vue';

const props = defineProps({
    users: { type: Object, required: true },
    tab: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, required: true },
    creatableRoles: { type: Array, required: true },
    fieldOffices: { type: Array, required: true },
    assignableFieldOffices: { type: Array, required: true },
    testingCenters: { type: Array, required: true },
    counts: { type: Object, required: true },
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

/*
 * --- Tabs ---
 *
 * Two populations, two sets of columns. Staff have a role and a field office;
 * test administrators have a PROCTAD record and a testing center, and their
 * field office is deliberately null (they work for their own agency, not the
 * Commission), which is why one combined table showed them an empty column and
 * dropped them from every office filter.
 */
const tab = ref(props.tab);
const isMembers = computed(() => tab.value === 'members');

const tabs = computed(() => [
    { key: 'staff', label: `Staff Accounts (${props.counts.staff})` },
    { key: 'members', label: `Test Administrators (${props.counts.members})` },
]);

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const fieldOfficeId = ref(props.filters.field_office_id ?? '');
const testingCenterId = ref(props.filters.testing_center_id ?? '');
const linked = ref(props.filters.linked ?? '');
const loading = ref(false);

let debounce = null;
const applyFilters = () => router.get('/users', {
    tab: isMembers.value ? 'members' : undefined,
    search: search.value || undefined,
    // Each tab sends only the filters it shows. Carrying the other tab's
    // filters across would apply a constraint with no visible control.
    role: isMembers.value ? undefined : role.value || undefined,
    field_office_id: isMembers.value ? undefined : fieldOfficeId.value || undefined,
    testing_center_id: isMembers.value ? testingCenterId.value || undefined : undefined,
    linked: isMembers.value ? linked.value || undefined : undefined,
}, {
    preserveState: true,
    replace: true,
    only: ['users', 'filters', 'tab'],
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
});

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});
watch([role, fieldOfficeId, testingCenterId, linked], applyFilters);

/* Switching tabs clears the search too: a name typed to find a staff member is
 * rarely the one you want among the administrators, and a carried-over term
 * makes the new tab look empty. */
const switchTab = () => {
    search.value = '';
    role.value = '';
    fieldOfficeId.value = '';
    testingCenterId.value = '';
    linked.value = '';
    applyFilters();
};

const toggleUnlinked = () => {
    linked.value = linked.value === 'unlinked' ? '' : 'unlinked';
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
const editingMember = computed(() => editing.value?.role === 'member');
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

/*
 * --- PROCTAD record ---
 *
 * The registry side of an account, opened over this page rather than by sending
 * the admin to /members?view= or ?register=. Both modals load everything they
 * show from their own JSON endpoints (/members/{id}/details, /members/create),
 * so they work wherever they are mounted — and reviewing or registering one row
 * should not cost the list, its tab, its filters and its page position.
 *
 * Both are gated server-side as well: the details endpoint authorizes `view` on
 * the member and /members/create authorizes `create` on Member, so an admin who
 * cannot reach the record gets the modal's error state, not the record.
 */
// The id outlives the `show` flag on purpose (the same pairing Members/Index
// uses): clearing it on close would blank the modal's contents mid-transition.
const viewingMemberId = ref(null);
const showMemberModal = ref(false);
const registeringAccountId = ref(null);
const showRegisterModal = ref(false);

const openMemberRecord = (user) => {
    viewingMemberId.value = user.member.id;
    showMemberModal.value = true;
};

const openMemberRegistration = (user) => {
    registeringAccountId.value = user.id;
    showRegisterModal.value = true;
};

/*
 * Registering links the new record to the account on this row, so the row's own
 * badges and actions are now out of date. store() redirects back here, which
 * re-renders the list with the record attached — nothing further to reload.
 */
const onMemberRegistered = () => {
    showRegisterModal.value = false;
};
</script>

<template>
    <Head title="User Accounts" />

    <DashboardLayout>
        <DashboardPageHeader
            title="User Accounts"
            :subtitle="isMembers
                ? 'Sign-in accounts for test administrators. Their registry details live on the Members page.'
                : 'Commission staff accounts. New accounts receive an emailed link to set their own password.'"
        >
            <template v-if="can.create && !isMembers" #actions>
                <BaseButton variant="primary" size="sm" icon="user-plus" @click="openCreate">
                    Add User
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div class="mt-6">
            <StepTabs
                v-model="tab"
                :steps="tabs"
                aria-label="Account type"
                @update:model-value="switchTab"
            />
        </div>

        <!-- Filters -->
        <BaseCard padding="sm" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <TextInput v-model="search" label="Search" placeholder="Name, email, or username" />
            <template v-if="!isMembers">
                <SelectInput
                    v-model="role"
                    label="Role"
                    placeholder="All roles"
                    :options="[{ value: '', label: 'All roles' }, ...roles.filter((r) => r.value !== 'member')]"
                />
                <SelectInput
                    v-model="fieldOfficeId"
                    label="Field Office"
                    placeholder="All field offices"
                    :options="[{ value: '', label: 'All field offices' }, ...fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))]"
                />
            </template>
            <SelectInput
                v-else
                v-model="testingCenterId"
                label="Testing Center"
                placeholder="All testing centers"
                :options="[{ value: '', label: 'All testing centers' }, ...testingCenters.map((tc) => ({ value: tc.id, label: tc.name }))]"
            />
        </BaseCard>

        <!--
            Registration creates the account and the registry record together, so
            this can only be legacy data. Shown as a chip rather than a banner:
            it is a queue that should drain to zero and then disappear, not an
            ongoing part of the workflow.
        -->
        <div v-if="isMembers && counts.unlinked > 0" class="mt-3">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                :class="linked === 'unlinked'
                    ? 'border-amber-300 bg-amber-100 text-amber-900'
                    : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'"
                :aria-pressed="linked === 'unlinked'"
                @click="toggleUnlinked"
            >
                {{ counts.unlinked }} with no linked PROCTAD record
                <span v-if="linked === 'unlinked'" class="text-amber-600">✕</span>
            </button>
            <!--
                Deliberately says what to do, because signing in again will not fix
                it: GoogleAuthController matches an existing account by google_id or
                email and logs it straight in, so it never reaches the registration
                form that would create the record. Deactivating is the only remedy
                this page offers.
            -->
            <p v-if="linked === 'unlinked'" class="mt-2 max-w-2xl text-xs leading-relaxed text-slate-500">
                These accounts can sign in, but have no record in the registry — their
                dashboard is empty and they cannot be assigned to an examination.
                Registration creates both together, so these predate the current
                sign-up flow. Signing in again will not create the missing record;
                deactivate the account instead.
            </p>
        </div>

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
                        <BaseBadge v-if="!isMembers && user.member" variant="accent">Test administrator</BaseBadge>
                        <BaseBadge v-else-if="isMembers && user.role !== 'member'" variant="accent">{{ user.role_label }}</BaseBadge>
                    </div>
                </div>
                <dl class="mt-2 space-y-1 text-xs text-slate-500">
                    <template v-if="isMembers">
                        <div class="flex gap-1"><dt class="font-medium text-slate-400">PROCTAD ID:</dt><dd class="text-slate-600">{{ user.member?.proctad_id ?? '—' }}</dd></div>
                        <div class="flex gap-1"><dt class="font-medium text-slate-400">Testing Center:</dt><dd class="text-slate-600">{{ user.member?.testing_center?.name ?? '—' }}</dd></div>
                        <div v-if="user.member" class="flex gap-1"><dt class="font-medium text-slate-400">Member status:</dt><dd class="text-slate-600">{{ user.member.status_label }}</dd></div>
                    </template>
                    <template v-else>
                        <div class="flex gap-1"><dt class="font-medium text-slate-400">Role:</dt><dd class="text-slate-600">{{ user.role_label }}</dd></div>
                        <div v-if="user.field_office?.name" class="flex gap-1"><dt class="font-medium text-slate-400">Field Office:</dt><dd class="text-slate-600">{{ user.field_office.name }}</dd></div>
                    </template>
                    <div class="flex gap-1"><dt class="font-medium text-slate-400">Last login:</dt><dd class="text-slate-600">{{ user.last_login_at ?? 'Never' }}</dd></div>
                </dl>
                <div class="mt-3 flex flex-wrap gap-1 border-t border-slate-100 pt-3">
                    <IconButton
                        v-if="user.member"
                        icon="identification"
                        label="View PROCTAD record"
                        @click="openMemberRecord(user)"
                    />
                    <IconButton
                        v-else-if="can.registerMember"
                        icon="user-plus"
                        label="Register as test administrator"
                        @click="openMemberRegistration(user)"
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
            :columns="isMembers
                ? [
                    { label: 'Name' },
                    { label: 'PROCTAD ID' },
                    { label: 'Testing Center', class: 'hidden xl:table-cell' },
                    { label: 'Last Login', class: 'hidden md:table-cell' },
                    { label: 'Status' },
                    { label: 'Actions', align: 'center' },
                ]
                : [
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
                            <p class="truncate text-xs text-slate-400 xl:hidden">
                                {{ isMembers ? user.member?.testing_center?.name ?? '—' : user.field_office?.name ?? '—' }}
                            </p>
                        </td>
                        <template v-if="isMembers">
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-600">{{ user.member?.proctad_id ?? '—' }}</td>
                            <td class="hidden max-w-[10rem] truncate px-3 py-2 text-slate-600 xl:table-cell" :title="user.member?.testing_center?.name ?? ''">
                                {{ user.member?.testing_center?.name ?? '—' }}
                            </td>
                        </template>
                        <template v-else>
                            <td class="max-w-[8rem] truncate px-3 py-2 text-slate-600">{{ user.role_label }}</td>
                            <td class="hidden max-w-[10rem] truncate px-3 py-2 text-slate-600 xl:table-cell" :title="user.field_office?.name ?? ''">{{ user.field_office?.name ?? '—' }}</td>
                        </template>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 md:table-cell">{{ user.last_login_at ?? 'Never' }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-col gap-1 items-start">
                                <BaseBadge :variant="user.is_active ? 'success' : 'neutral'">
                                    {{ user.is_active ? 'Active' : 'Deactivated' }}
                                </BaseBadge>
                                <!-- The member's own standing in the registry, which is
                                     not the same fact as whether the account can sign in. -->
                                <BaseBadge v-if="user.member && user.member.status !== 'active'" variant="warning">
                                    {{ user.member.status_label }}
                                </BaseBadge>
                                <BaseBadge v-if="user.must_change_password" variant="warning">Password pending</BaseBadge>
                                <BaseBadge v-if="user.has_member_record === false" variant="warning">No PROCTAD record</BaseBadge>
                                <!-- Dual-hat accounts (Workspace switcher): each tab
                                     names the hat the other tab is showing. -->
                                <BaseBadge v-if="!isMembers && user.member" variant="accent">Test administrator</BaseBadge>
                                <BaseBadge v-else-if="isMembers && user.role !== 'member'" variant="accent">{{ user.role_label }}</BaseBadge>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div class="inline-flex gap-1">
                                <!--
                                    Linked accounts open their detail modal over
                                    this page, rather than a search that only
                                    narrows the Members list and leaves one more
                                    click to find.
                                -->
                                <IconButton
                                    v-if="user.member"
                                    icon="identification"
                                    label="View PROCTAD record"
                                    @click="openMemberRecord(user)"
                                />
                                <!--
                                    Anyone without a record, on either tab, can be
                                    registered against the login they already hold —
                                    the create form seeds itself from the account and
                                    locks the email, so submitting links it rather
                                    than minting a second.

                                    On the staff tab this is the *only* way a CSC
                                    employee who also proctors gets a PROCTAD record
                                    and the workspace switcher with it: registration
                                    turns away any email that already has a login.
                                    On the members tab it drains the "awaiting
                                    registration" queue.
                                -->
                                <IconButton
                                    v-else-if="can.registerMember"
                                    icon="user-plus"
                                    label="Register as test administrator"
                                    @click="openMemberRegistration(user)"
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
            <EmptyState
                icon="user-group"
                :title="isMembers ? 'No test administrators found' : 'No staff accounts found'"
                :description="isMembers
                    ? 'Test administrators appear here once they register through the sign-up page.'
                    : 'No accounts match your search or filters.'"
            />
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
                <SelectInput
                    v-model="createForm.role"
                    label="Role"
                    required
                    :options="creatableRoles"
                    :error="createForm.errors.role"
                    hint="Test administrators are not created here — they register themselves, which creates their PROCTAD record at the same time."
                />
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
                <!--
                    Role and field office describe a Commission posting. On a test
                    administrator they are shown behind a note rather than hidden:
                    changing the role is how someone who joins the Commission is
                    promoted out of the member population, and this form is the
                    only route for it — but nothing about their testing center or
                    agency is edited here.
                -->
                <!-- The pointer to the Members page applies to anyone holding an
                     accreditation, staff included; only the sentence about the
                     role is specific to a member-role account. -->
                <p v-if="editing?.member || editingMember" class="rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-500">
                    Testing center, agency and member status belong to this person's
                    PROCTAD record and are edited on the Members page.
                    <template v-if="editingMember">
                        Change the role below only to move them onto a Commission staff account.
                    </template>
                </p>
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

        <!-- The registry side of an account, shown over this page. Both are the
             same components the Members page uses, so the two entry points can
             never drift apart. -->
        <ViewMemberModal
            :show="showMemberModal"
            :member-id="viewingMemberId"
            @close="showMemberModal = false"
        />

        <CreateMemberModal
            :show="showRegisterModal"
            :account-id="registeringAccountId"
            @close="showRegisterModal = false"
            @saved="onMemberRegistered"
        />
    </DashboardLayout>
</template>
