<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    schools: { type: Array, required: true },
    stats: { type: Object, required: true },
    fieldOffices: { type: Array, required: true },
    can: { type: Object, required: true },
});

/* --- Search + status filter (client-side, matches a short per-office list) --- */
const search = ref('');
const statusFilter = ref('all');

const filteredSchools = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.schools.filter((school) => {
        const matchesSearch = !term
            || school.name.toLowerCase().includes(term)
            || school.municipality.toLowerCase().includes(term);
        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && school.is_active)
            || (statusFilter.value === 'inactive' && !school.is_active);

        return matchesSearch && matchesStatus;
    });
});

/* --- Create / edit modal --- */
const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const form = useForm({
    name: '',
    municipality: '',
    contact_person: '',
    contact_number: '',
    contact_email: '',
    field_office_id: '',
    is_active: true,
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.field_office_id = props.fieldOffices.length === 1 ? props.fieldOffices[0].id : '';
    showForm.value = true;
};

const openEdit = (school) => {
    editing.value = school;
    form.clearErrors();
    form.name = school.name;
    form.municipality = school.municipality;
    form.contact_person = school.contact_person ?? '';
    form.contact_number = school.contact_number ?? '';
    form.contact_email = school.contact_email ?? '';
    form.field_office_id = school.field_office?.id ?? '';
    form.is_active = school.is_active;
    showForm.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    if (editing.value) {
        form.put(`/schools/${editing.value.id}`, options);
    } else {
        form.post('/schools', options);
    }
};

const destroyForm = useForm({});
const confirmDelete = () => destroyForm.delete(`/schools/${deleting.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (deleting.value = null),
});
</script>

<template>
    <Head title="Schools" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Schools"
            subtitle="Venue registry maintained per Testing Center. Schools become examination venues once attached to an examination."
        >
            <template v-if="can.create" #actions>
                <BaseButton variant="primary" size="sm" @click="openCreate">
                    Add School
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <!-- At-a-glance stats -->
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <StatCard label="Total Schools" :value="stats.total" icon="building-office" />
            <StatCard label="Active" :value="stats.active" icon="check-circle" hint="Selectable as a venue" />
            <StatCard label="Inactive" :value="stats.inactive" icon="x-circle" />
        </div>

        <!-- Search + status filter -->
        <div class="mt-6 flex flex-wrap items-end gap-4">
            <TextInput v-model="search" label="Search" placeholder="Name or municipality" class="max-w-xs flex-1" />
            <div class="flex gap-2 pb-0.5">
                <button
                    v-for="option in [{ value: 'all', label: 'All' }, { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]"
                    :key="option.value"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"
                    :class="statusFilter === option.value ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    @click="statusFilter = option.value"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div v-if="filteredSchools.length" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">School</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Municipality</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Testing Center</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Contact</th>
                        <th class="hidden px-3 py-2 md:table-cell">Used In</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="school in filteredSchools" :key="school.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 font-medium text-slate-900">
                            {{ school.name }}
                            <p class="text-xs font-normal text-slate-400 sm:hidden">{{ school.municipality }}</p>
                        </td>
                        <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ school.municipality }}</td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 xl:table-cell">{{ school.field_office?.name }}</td>
                        <td class="hidden px-3 py-2 text-slate-600 xl:table-cell">
                            <p v-if="school.contact_person">{{ school.contact_person }}</p>
                            <p class="text-xs text-slate-400">{{ school.contact_number || school.contact_email || '—' }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 md:table-cell">
                            {{ school.venues_count }} exam{{ school.venues_count === 1 ? '' : 's' }}
                        </td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="school.is_active ? 'success' : 'neutral'">
                                {{ school.is_active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div v-if="school.can_manage" class="inline-flex gap-1">
                                <IconButton icon="pencil" label="Edit" @click="openEdit(school)" />
                                <IconButton icon="trash" label="Remove" variant="danger" @click="deleting = school" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-4">
            <EmptyState
                icon="building-office"
                :title="schools.length ? 'No schools match your search' : 'No schools yet'"
                :description="schools.length ? 'Try a different name or municipality, or clear the status filter.' : 'Add the schools that serve as examination venues in each Testing Center.'"
            />
        </div>

        <!-- Create / edit modal -->
        <BaseModal :show="showForm" :title="editing ? 'Edit School' : 'Add School'" @close="showForm = false">
            <form id="school-form" class="space-y-4" novalidate @submit.prevent="submit">
                <TextInput v-model="form.name" label="School Name" required :error="form.errors.name" placeholder="e.g. Leyte National High School" />
                <TextInput v-model="form.municipality" label="Municipality" required :error="form.errors.municipality" />
                <SelectInput
                    v-model="form.field_office_id"
                    label="Testing Center"
                    required
                    :options="fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))"
                    :error="form.errors.field_office_id"
                />
                <TextInput v-model="form.contact_person" label="Contact Person" optional :error="form.errors.contact_person" />
                <TextInput v-model="form.contact_number" label="Contact Number" optional :error="form.errors.contact_number" />
                <TextInput v-model="form.contact_email" label="Contact Email" type="email" optional :error="form.errors.contact_email" />
                <CheckboxInput v-model="form.is_active">Active (selectable as an examination venue)</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showForm = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="school-form"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save Changes' : 'Add School' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Delete confirm -->
        <BaseModal :show="!!deleting" title="Remove school" @close="deleting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ deleting?.name }}</strong>?
            </p>
            <p
                v-if="deleting?.venues_count"
                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                This school is used as a venue in <strong>{{ deleting.venues_count }}</strong> examination{{ deleting.venues_count === 1 ? '' : 's' }}.
                Removing it also removes those venue links and any rooms configured under them.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="deleting = null">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="destroyForm.processing" @click="confirmDelete">
                    Remove
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
