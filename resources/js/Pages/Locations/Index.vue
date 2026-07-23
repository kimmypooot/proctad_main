<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    fieldOffices: { type: Array, required: true },
    testingCenters: { type: Array, required: true },
    allTestingCenters: { type: Array, required: true },
    schools: { type: Array, required: true },
    scope: { type: Object, required: true },
    can: { type: Object, required: true },
});

/* --- Drill-down state: hold IDs, derive everything reactively from props so a
       post-mutation reload always reflects fresh data. --- */
const selectedOfficeId = ref(props.scope.field_office_scoped ? props.scope.field_office_id : null);
const selectedCenterId = ref(null);

const currentOffice = computed(() => props.fieldOffices.find((o) => o.id === selectedOfficeId.value) ?? null);
const currentCenter = computed(() => props.testingCenters.find((c) => c.id === selectedCenterId.value) ?? null);

const level = computed(() => {
    if (!selectedOfficeId.value) return 'offices';
    if (!selectedCenterId.value) return 'centers';
    return 'schools';
});

/* --- Search + status filter, applied to whichever level is showing --- */
const search = ref('');
const statusFilter = ref('all');

const matches = (item) => {
    const term = search.value.trim().toLowerCase();
    const matchesSearch = !term || item.name.toLowerCase().includes(term);
    const matchesStatus = statusFilter.value === 'all'
        || (statusFilter.value === 'active' && item.is_active)
        || (statusFilter.value === 'inactive' && !item.is_active);
    return matchesSearch && matchesStatus;
};

const centerHandledBy = (center, officeId) => (center.field_office_ids ?? []).includes(officeId);

const visibleOffices = computed(() => props.fieldOffices.filter(matches));
const visibleCenters = computed(() => props.testingCenters.filter((c) => centerHandledBy(c, selectedOfficeId.value)).filter(matches));
const visibleSchools = computed(() => props.schools.filter((s) => s.testing_center_id === selectedCenterId.value).filter(matches));

const resetFilters = () => {
    search.value = '';
    statusFilter.value = 'all';
};

const openOffice = (office) => {
    selectedOfficeId.value = office.id;
    selectedCenterId.value = null;
    resetFilters();
};
const openCenter = (center) => {
    selectedCenterId.value = center.id;
    resetFilters();
};

/* --- Breadcrumb --- */
const goToOffices = () => {
    if (props.scope.field_office_scoped) return; // scoped users have no office list
    selectedOfficeId.value = null;
    selectedCenterId.value = null;
    resetFilters();
};
const goToCenters = () => {
    selectedCenterId.value = null;
    resetFilters();
};

/* --- Field Office modal (admins only; no delete) --- */
const officeForm = useForm({ id: null, name: '', code: '', address: '', is_active: true });
const showOfficeForm = ref(false);
const openOfficeCreate = () => {
    officeForm.reset();
    officeForm.clearErrors();
    showOfficeForm.value = true;
};
const openOfficeEdit = (office) => {
    officeForm.clearErrors();
    officeForm.id = office.id;
    officeForm.name = office.name;
    officeForm.code = office.code;
    officeForm.address = office.address ?? '';
    officeForm.is_active = office.is_active;
    showOfficeForm.value = true;
};
const submitOffice = () => {
    const opts = { preserveScroll: true, preserveState: true, onSuccess: () => (showOfficeForm.value = false) };
    if (officeForm.id) officeForm.put(`/field-offices/${officeForm.id}`, opts);
    else officeForm.post('/field-offices', opts);
};

/* --- Testing Center modal ---
   Adding offers two choices: link an existing center (shared hosting) or
   create a new one. Editing only renames an existing center. --- */
const centerForm = useForm({ id: null, name: '', testing_center_id: '', field_office_id: null, is_active: true });
const showCenterForm = ref(false);
const centerMode = ref('new'); // 'new' | 'existing'

// Existing centers not already handled by the office being viewed.
const linkableCenters = computed(() => {
    const linkedIds = new Set(
        props.testingCenters.filter((c) => centerHandledBy(c, selectedOfficeId.value)).map((c) => c.id),
    );
    return props.allTestingCenters.filter((c) => !linkedIds.has(c.id));
});

const openCenterCreate = () => {
    centerForm.reset();
    centerForm.clearErrors();
    centerForm.field_office_id = selectedOfficeId.value;
    centerMode.value = linkableCenters.value.length ? 'existing' : 'new';
    showCenterForm.value = true;
};
const openCenterEdit = (center) => {
    centerForm.reset();
    centerForm.clearErrors();
    centerForm.id = center.id;
    centerForm.name = center.name;
    centerForm.is_active = center.is_active;
    showCenterForm.value = true;
};
const submitCenter = () => {
    const opts = { preserveScroll: true, preserveState: true, onSuccess: () => (showCenterForm.value = false) };
    if (centerForm.id) {
        centerForm.put(`/testing-centers/${centerForm.id}`, opts);
        return;
    }
    // Creating: send either the picked existing center or a new name.
    centerForm.transform((data) => ({
        field_office_id: data.field_office_id,
        ...(centerMode.value === 'existing'
            ? { testing_center_id: data.testing_center_id }
            : { name: data.name, is_active: data.is_active }),
    })).post('/testing-centers', { ...opts, onFinish: () => centerForm.transform((d) => d) });
};

/* --- School modal --- */
const schoolForm = useForm({
    id: null, name: '', testing_center_id: null,
    contact_person: '', contact_number: '', contact_email: '', is_active: true,
});
const showSchoolForm = ref(false);
const openSchoolCreate = () => {
    schoolForm.reset();
    schoolForm.clearErrors();
    schoolForm.testing_center_id = selectedCenterId.value;
    showSchoolForm.value = true;
};
const openSchoolEdit = (school) => {
    schoolForm.clearErrors();
    schoolForm.id = school.id;
    schoolForm.name = school.name;
    schoolForm.testing_center_id = school.testing_center_id;
    schoolForm.contact_person = school.contact_person ?? '';
    schoolForm.contact_number = school.contact_number ?? '';
    schoolForm.contact_email = school.contact_email ?? '';
    schoolForm.is_active = school.is_active;
    showSchoolForm.value = true;
};
const submitSchool = () => {
    const opts = { preserveScroll: true, preserveState: true, onSuccess: () => (showSchoolForm.value = false) };
    if (schoolForm.id) schoolForm.put(`/schools/${schoolForm.id}`, opts);
    else schoolForm.post('/schools', opts);
};

/* --- Delete (field offices + testing centers + schools) --- */
const deleting = ref(null); // { type: 'office' | 'center' | 'school', item }
const deleteBlocked = computed(() => {
    if (!deleting.value) return false;
    const { type, item } = deleting.value;
    if (type === 'office') return !!(item.users_count || item.members_count);
    if (type === 'center') return !!item.schools_count;
    return false;
});
const deleteUrl = {
    office: (id) => `/field-offices/${id}`,
    center: (id) => `/testing-centers/${id}`,
    school: (id) => `/schools/${id}`,
};
const destroyForm = useForm({});
const confirmDelete = () => {
    const { type, item } = deleting.value;
    destroyForm.delete(deleteUrl[type](item.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (type === 'office' && selectedOfficeId.value === item.id) {
                selectedOfficeId.value = null;
                selectedCenterId.value = null;
            }
            if (type === 'center' && selectedCenterId.value === item.id) selectedCenterId.value = null;
            deleting.value = null;
        },
    });
};

const deleteTitle = computed(() => ({
    office: 'Remove field office',
    center: 'Remove testing center',
    school: 'Remove school',
}[deleting.value?.type] ?? 'Remove'));

const statusOptions = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];
</script>

<template>
    <Head title="Locations" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Locations"
            subtitle="Field offices, their testing centers, and the schools that serve as examination venues."
        />

        <!-- Breadcrumb -->
        <nav class="mt-6 flex flex-wrap items-center gap-1.5 text-sm">
            <button
                type="button"
                class="rounded px-1.5 py-0.5 font-medium transition-colors"
                :class="level === 'offices' ? 'text-slate-900' : 'text-brand-600 hover:bg-brand-50'"
                :disabled="scope.field_office_scoped && level === 'centers'"
                @click="goToOffices"
            >
                {{ scope.field_office_scoped ? (currentOffice?.name ?? 'My Field Office') : 'All Field Offices' }}
            </button>

            <template v-if="!scope.field_office_scoped && currentOffice">
                <AppIcon name="chevron-right" class="h-3.5 w-3.5 text-slate-300" />
                <button
                    type="button"
                    class="rounded px-1.5 py-0.5 font-medium transition-colors"
                    :class="level === 'centers' ? 'text-slate-900' : 'text-brand-600 hover:bg-brand-50'"
                    @click="goToCenters"
                >
                    {{ currentOffice.name }}
                </button>
            </template>

            <template v-if="currentCenter">
                <AppIcon name="chevron-right" class="h-3.5 w-3.5 text-slate-300" />
                <span class="px-1.5 py-0.5 font-medium text-slate-900">{{ currentCenter.name }}</span>
            </template>
        </nav>

        <!-- Toolbar: title + search/filter + add -->
        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-wrap items-end gap-3">
                <TextInput v-model="search" label="Search" :placeholder="`Search ${level}`" class="max-w-xs" />
                <div class="flex gap-1.5 pb-0.5">
                    <button
                        v-for="opt in statusOptions"
                        :key="opt.value"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="statusFilter === opt.value ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="statusFilter = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <BaseButton
                v-if="level === 'offices' && can.manageFieldOffices"
                variant="primary" size="sm" icon="plus" @click="openOfficeCreate"
            >
                Add Field Office
            </BaseButton>
            <BaseButton
                v-else-if="level === 'centers' && can.createTestingCenter"
                variant="primary" size="sm" icon="plus" @click="openCenterCreate"
            >
                Add Testing Center
            </BaseButton>
            <BaseButton
                v-else-if="level === 'schools' && can.createSchool"
                variant="primary" size="sm" icon="plus" @click="openSchoolCreate"
            >
                Add School
            </BaseButton>
        </div>

        <!-- LIST: Field Offices -->
        <div v-if="level === 'offices'" class="mt-4 space-y-2">
            <button
                v-for="office in visibleOffices"
                :key="office.id"
                type="button"
                class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left transition-colors hover:border-brand-300 hover:bg-brand-50/40"
                @click="openOffice(office)"
            >
                <AppIcon name="building-library" class="h-5 w-5 shrink-0 text-slate-400" />
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 font-medium text-slate-900">
                        {{ office.name }}
                        <span class="font-mono text-xs font-semibold text-brand-700">{{ office.code }}</span>
                        <BaseBadge v-if="!office.is_active" variant="neutral">Hidden</BaseBadge>
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ office.testing_centers_count }} testing center{{ office.testing_centers_count === 1 ? '' : 's' }}
                        · {{ office.members_count }} member{{ office.members_count === 1 ? '' : 's' }}
                        · {{ office.users_count }} staff
                    </p>
                </div>
                <template v-if="can.manageFieldOffices">
                    <IconButton icon="pencil" label="Edit" @click.stop="openOfficeEdit(office)" />
                    <IconButton icon="trash" label="Remove" variant="danger" @click.stop="deleting = { type: 'office', item: office }" />
                </template>
                <AppIcon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300" />
            </button>
            <EmptyState
                v-if="!visibleOffices.length"
                icon="building-library" title="No field offices"
                description="No field offices match your search."
            />
        </div>

        <!-- LIST: Testing Centers -->
        <div v-else-if="level === 'centers'" class="mt-4 space-y-2">
            <button
                v-for="center in visibleCenters"
                :key="center.id"
                type="button"
                class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left transition-colors hover:border-brand-300 hover:bg-brand-50/40"
                @click="openCenter(center)"
            >
                <AppIcon name="map-pin" class="h-5 w-5 shrink-0 text-slate-400" />
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 font-medium text-slate-900">
                        {{ center.name }}
                        <BaseBadge v-if="center.field_office_ids.length > 1" variant="brand">Shared · {{ center.field_office_ids.length }} offices</BaseBadge>
                        <BaseBadge v-if="!center.is_active" variant="neutral">Inactive</BaseBadge>
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ center.schools_count }} school{{ center.schools_count === 1 ? '' : 's' }}
                        · {{ center.users_count }} staff
                    </p>
                </div>
                <template v-if="center.can_manage">
                    <IconButton icon="pencil" label="Edit" @click.stop="openCenterEdit(center)" />
                    <IconButton icon="trash" label="Remove" variant="danger" @click.stop="deleting = { type: 'center', item: center }" />
                </template>
                <AppIcon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300" />
            </button>
            <EmptyState
                v-if="!visibleCenters.length"
                icon="map-pin" title="No testing centers"
                :description="testingCenters.some((c) => centerHandledBy(c, selectedOfficeId)) ? 'No testing centers match your search.' : 'Add the cities where examinations are held in this field office.'"
            />
        </div>

        <!-- LIST: Schools -->
        <div v-else class="mt-4 space-y-2">
            <div
                v-for="school in visibleSchools"
                :key="school.id"
                class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3"
            >
                <AppIcon name="building-office" class="h-5 w-5 shrink-0 text-slate-400" />
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 font-medium text-slate-900">
                        {{ school.name }}
                        <BaseBadge v-if="!school.is_active" variant="neutral">Inactive</BaseBadge>
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ school.venues_count }} exam{{ school.venues_count === 1 ? '' : 's' }}
                        <template v-if="school.contact_person"> · {{ school.contact_person }}</template>
                    </p>
                </div>
                <template v-if="school.can_manage">
                    <IconButton icon="pencil" label="Edit" @click="openSchoolEdit(school)" />
                    <IconButton icon="trash" label="Remove" variant="danger" @click="deleting = { type: 'school', item: school }" />
                </template>
            </div>
            <EmptyState
                v-if="!visibleSchools.length"
                icon="building-office" title="No schools"
                :description="schools.some((s) => s.testing_center_id === selectedCenterId) ? 'No schools match your search.' : 'Add the schools that serve as examination venues in this testing center.'"
            />
        </div>

        <!-- Field Office modal -->
        <BaseModal :show="showOfficeForm" :title="officeForm.id ? 'Edit Field Office' : 'Add Field Office'" @close="showOfficeForm = false">
            <form id="office-form" class="space-y-4" novalidate @submit.prevent="submitOffice">
                <TextInput v-model="officeForm.name" label="Name" required :error="officeForm.errors.name" placeholder="e.g. Leyte Field Office" />
                <TextInput v-model="officeForm.code" label="Code" required :error="officeForm.errors.code" placeholder="e.g. LEY" />
                <TextInput v-model="officeForm.address" label="Address" optional :error="officeForm.errors.address" placeholder="e.g. Palo, Leyte" />
                <CheckboxInput v-model="officeForm.is_active">Active (selectable when creating new records)</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showOfficeForm = false">Cancel</BaseButton>
                <BaseButton type="submit" form="office-form" variant="primary" size="sm" :loading="officeForm.processing" :disabled="officeForm.processing">
                    {{ officeForm.id ? 'Save Changes' : 'Add Field Office' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Testing Center modal -->
        <BaseModal :show="showCenterForm" :title="centerForm.id ? 'Edit Testing Center' : 'Add Testing Center'" @close="showCenterForm = false">
            <form id="center-form" class="space-y-4" novalidate @submit.prevent="submitCenter">
                <p class="text-xs text-slate-500">
                    Field Office: <span class="font-medium text-slate-700">{{ currentOffice?.name }}</span>
                </p>

                <!-- Add mode: link an existing center or create a new one -->
                <div v-if="!centerForm.id && linkableCenters.length" class="flex gap-1.5">
                    <button
                        v-for="opt in [{ value: 'existing', label: 'Link existing' }, { value: 'new', label: 'Create new' }]"
                        :key="opt.value"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="centerMode === opt.value ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="centerMode = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>

                <template v-if="!centerForm.id && centerMode === 'existing' && linkableCenters.length">
                    <SelectInput
                        v-model="centerForm.testing_center_id"
                        label="Existing testing center"
                        required
                        :options="linkableCenters.map((c) => ({ value: c.id, label: c.name }))"
                        :error="centerForm.errors.testing_center_id"
                    />
                    <p class="-mt-2 text-xs text-slate-500">
                        Links a testing center another office already created (e.g. sharing Tacloban City) to
                        <span class="font-medium">{{ currentOffice?.name }}</span>. Its schools are shared.
                    </p>
                </template>
                <template v-else>
                    <TextInput v-model="centerForm.name" label="City / Testing Center" required :error="centerForm.errors.name" placeholder="e.g. Tacloban City" />
                    <CheckboxInput v-model="centerForm.is_active">Active (selectable when registering schools)</CheckboxInput>
                </template>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showCenterForm = false">Cancel</BaseButton>
                <BaseButton type="submit" form="center-form" variant="primary" size="sm" :loading="centerForm.processing" :disabled="centerForm.processing">
                    {{ centerForm.id ? 'Save Changes' : 'Add Testing Center' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- School modal -->
        <BaseModal :show="showSchoolForm" :title="schoolForm.id ? 'Edit School' : 'Add School'" @close="showSchoolForm = false">
            <form id="school-form" class="space-y-4" novalidate @submit.prevent="submitSchool">
                <p class="text-xs text-slate-500">
                    Testing Center: <span class="font-medium text-slate-700">{{ currentCenter?.name }}</span>
                </p>
                <TextInput v-model="schoolForm.name" label="School Name" required :error="schoolForm.errors.name" placeholder="e.g. Leyte National High School" />
                <TextInput v-model="schoolForm.contact_person" label="Contact Person" optional :error="schoolForm.errors.contact_person" />
                <TextInput v-model="schoolForm.contact_number" label="Contact Number" optional :error="schoolForm.errors.contact_number" />
                <TextInput v-model="schoolForm.contact_email" label="Contact Email" type="email" optional :error="schoolForm.errors.contact_email" />
                <CheckboxInput v-model="schoolForm.is_active">Active (selectable as an examination venue)</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showSchoolForm = false">Cancel</BaseButton>
                <BaseButton type="submit" form="school-form" variant="primary" size="sm" :loading="schoolForm.processing" :disabled="schoolForm.processing">
                    {{ schoolForm.id ? 'Save Changes' : 'Add School' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Delete confirm -->
        <BaseModal :show="!!deleting" :title="deleteTitle" @close="deleting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ deleting?.item.name }}</strong>?
            </p>
            <p
                v-if="deleting?.type === 'office' && deleteBlocked"
                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                This field office still anchors
                <strong>{{ deleting.item.users_count }}</strong> user{{ deleting.item.users_count === 1 ? '' : 's' }} and
                <strong>{{ deleting.item.members_count }}</strong> member{{ deleting.item.members_count === 1 ? '' : 's' }}.
                Reassign or remove those first. (Its testing-center links just detach.)
            </p>
            <p
                v-else-if="deleting?.type === 'center' && deleteBlocked"
                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                This testing center still has <strong>{{ deleting.item.schools_count }}</strong> school{{ deleting.item.schools_count === 1 ? '' : 's' }}.
                Reassign or remove those schools first.
            </p>
            <p
                v-else-if="deleting?.type === 'center' && deleting?.item.field_office_ids.length > 1"
                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                This testing center is shared by <strong>{{ deleting.item.field_office_ids.length }}</strong> field offices.
                Deleting it removes it for all of them.
            </p>
            <p
                v-else-if="deleting?.type === 'school' && deleting?.item.venues_count"
                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                This school is used as a venue in <strong>{{ deleting.item.venues_count }}</strong> examination{{ deleting.item.venues_count === 1 ? '' : 's' }}.
                Removing it also removes those venue links and any rooms configured under them.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="deleting = null">Cancel</BaseButton>
                <BaseButton
                    variant="accent" size="sm"
                    :disabled="deleteBlocked || destroyForm.processing"
                    :loading="destroyForm.processing"
                    @click="confirmDelete"
                >
                    Remove
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
