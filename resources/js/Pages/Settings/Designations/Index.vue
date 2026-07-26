<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BaseTable from '@/Components/BaseTable.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    sections: { type: Array, required: true },
});

const peso = (value) => `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/* --- Tabs: one per section, so the two lists never crowd each other --- */
const activeTab = ref(props.sections[0]?.value ?? '');
const section = computed(() => props.sections.find((s) => s.value === activeTab.value) ?? props.sections[0]);

/*
 * Committee options for the whole page, grouped by section so the add modal can
 * render a single <optgroup> select — picking the committee also picks which
 * of the two lists the designation joins.
 */
const committeeGroups = computed(() => props.sections.map((s) => ({
    label: s.name,
    section: s.value,
    options: s.categories.map((c) => ({ value: c.id, label: c.label })),
})));

const committeesInSection = computed(
    () => section.value?.categories.map((c) => ({ value: c.id, label: c.label })) ?? [],
);

/* --- Add --- */
const showAdd = ref(false);
const addForm = useForm({ section: '', designation_category_id: '', label: '', amount: 0, rooms_per_slot: '' });

const openAdd = () => {
    addForm.reset();
    addForm.clearErrors();
    addForm.section = activeTab.value;
    showAdd.value = true;
};

// The section is implied by the committee chosen, so keep them in step.
const onCommitteePicked = (categoryId) => {
    const owner = committeeGroups.value.find((g) => g.options.some((o) => o.value === Number(categoryId)));
    addForm.section = owner?.section ?? activeTab.value;
};

const submitAdd = () => addForm.post('/designations', {
    preserveScroll: true,
    onSuccess: () => (showAdd.value = false),
});

/* --- Edit (rename, move committee, rate, active) --- */
const editing = ref(null);
const editForm = useForm({ label: '', designation_category_id: '', is_active: true, amount: 0, rooms_per_slot: '' });

const openEdit = (item) => {
    editing.value = item;
    editForm.clearErrors();
    editForm.label = item.label;
    editForm.designation_category_id = item.category_id;
    editForm.is_active = item.is_active;
    editForm.amount = item.amount;
    editForm.rooms_per_slot = item.rooms_per_slot ?? '';
};

const submitEdit = () => editForm.put(`/designations/${editing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
});

/* --- Delete: name must be retyped, and the server refuses if it is in use --- */
const deleting = ref(null);
const deleteForm = useForm({ confirm_label: '' });

const openDelete = (item) => {
    deleting.value = item;
    deleteForm.reset();
    deleteForm.clearErrors();
};

const deleteBlocked = computed(() => (deleting.value?.usage_count ?? 0) > 0);
const deleteReady = computed(
    () => !deleteBlocked.value && deleteForm.confirm_label.trim() === deleting.value?.label,
);

const submitDelete = () => deleteForm.delete(`/designations/${deleting.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (deleting.value = null),
});

/* --- Committees --- */
const showCommittees = ref(false);
const committeeForm = useForm({ section: '', label: '' });
const editingCommittee = ref(null);

const submitCommittee = () => {
    if (editingCommittee.value) {
        committeeForm.put(`/designation-categories/${editingCommittee.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (editingCommittee.value = null),
        });

        return;
    }

    committeeForm.section = activeTab.value;
    committeeForm.post('/designation-categories', {
        preserveScroll: true,
        onSuccess: () => committeeForm.reset('label'),
    });
};

const startCommitteeEdit = (category) => {
    editingCommittee.value = category;
    committeeForm.clearErrors();
    committeeForm.label = category.label;
};

const cancelCommitteeEdit = () => {
    editingCommittee.value = null;
    committeeForm.reset('label');
};

const deletingCommittee = ref(null);

const confirmDeleteCommittee = () => router.delete(`/designation-categories/${deletingCommittee.value.id}`, {
    preserveScroll: true,
    onFinish: () => (deletingCommittee.value = null),
});
</script>

<template>
    <Head title="Designations" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Designations"
            subtitle="Examination duties and support personnel — what each is called, which committee it sits in, and what it pays."
        >
            <template #actions>
                <BaseButton variant="outline" size="sm" icon="user-group" @click="showCommittees = true">
                    Committees
                </BaseButton>
                <BaseButton variant="primary" size="sm" icon="plus" @click="openAdd">
                    Add Designation
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <BaseCard padding="sm" class="mt-6">
            <p class="text-sm leading-relaxed text-slate-600">
                This is where a designation's honorarium is set, and the rate here is the one the
                payroll reports use. Switching a designation off removes it from assignment lists
                without touching a single existing assignment, and its rate is kept for when it
                comes back.
            </p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Built-in designations can be renamed, moved and switched off, but not deleted — the
                payroll workbook and the evaluation form each name specific ones. Designations you
                add yourself are fully removable while nothing uses them, and appear in the per-room
                staffing grid as soon as you give them a rooms-covered value.
            </p>
        </BaseCard>

        <!-- Tabs -->
        <div class="mt-6 flex gap-1 border-b border-slate-200">
            <button
                v-for="tab in sections"
                :key="tab.value"
                type="button"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="tab.value === activeTab
                    ? 'border-brand-600 text-brand-700'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                @click="activeTab = tab.value"
            >
                {{ tab.name }}
            </button>
        </div>

        <p class="mt-3 text-sm text-slate-500">{{ section?.description }}</p>

        <div v-for="category in section?.categories ?? []" :key="category.id" class="mt-6">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ category.label }}</h3>
                <BaseBadge v-if="!category.is_builtin" variant="neutral">Custom</BaseBadge>
            </div>

            <EmptyState
                v-if="!category.designations.length"
                class="mt-2"
                icon="briefcase"
                title="No designations"
                description="Nothing is filed under this committee yet."
            />

            <BaseTable
                v-else
                class="mt-2"
                :columns="[
                    { label: 'Designation' },
                    { label: 'Rate', align: 'right' },
                    { label: 'In records', align: 'center', class: 'hidden md:table-cell' },
                    { label: 'Status', align: 'center' },
                    { label: 'Actions', align: 'center' },
                ]"
            >
                <tr
                    v-for="item in category.designations"
                    :key="item.id"
                    class="transition-colors hover:bg-brand-50/40"
                    :class="{ 'opacity-60': !item.is_active }"
                >
                    <td class="px-3 py-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-slate-900">{{ item.label }}</span>
                            <BaseBadge v-if="!item.is_builtin" variant="neutral">Custom</BaseBadge>
                            <BaseBadge v-if="item.rooms_per_slot === 1" variant="brand">
                                Room grid · 1 per room
                            </BaseBadge>
                            <!-- Anything covering several rooms is a default only: each
                                 venue sets its own group size when staffing is generated. -->
                            <BaseBadge v-else-if="item.rooms_per_slot" variant="brand">
                                Room grid · 1 per {{ item.rooms_per_slot }} rooms by default
                            </BaseBadge>
                        </div>
                    </td>
                    <td
                        class="px-3 py-2 text-right tabular-nums"
                        :class="item.rate_configured ? 'text-slate-800' : 'text-slate-400'"
                    >
                        {{ item.rate_configured ? peso(item.amount) : 'Not set' }}
                    </td>
                    <td class="hidden px-3 py-2 text-center text-slate-500 md:table-cell">{{ item.usage_count }}</td>
                    <td class="px-3 py-2 text-center">
                        <BaseBadge :variant="item.is_active ? 'success' : 'neutral'">
                            {{ item.is_active ? 'In use' : 'Not in use' }}
                        </BaseBadge>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <div class="inline-flex gap-1">
                            <IconButton icon="pencil" label="Edit / move" @click="openEdit(item)" />
                            <IconButton
                                v-if="!item.is_builtin"
                                icon="trash"
                                label="Delete"
                                @click="openDelete(item)"
                            />
                        </div>
                    </td>
                </tr>
            </BaseTable>
        </div>

        <!-- Add -->
        <BaseModal :show="showAdd" title="Add designation" @close="showAdd = false">
            <form id="designation-add" class="space-y-4" novalidate @submit.prevent="submitAdd">
                <TextInput v-model="addForm.label" label="Name" required :error="addForm.errors.label" />

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Committee <span class="text-rose-600">*</span>
                    </label>
                    <select
                        v-model="addForm.designation_category_id"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        required
                        @change="onCommitteePicked($event.target.value)"
                    >
                        <option value="" disabled>Select a committee</option>
                        <optgroup v-for="group in committeeGroups" :key="group.section" :label="group.label">
                            <option v-for="option in group.options" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </optgroup>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        The committee decides which list the designation joins.
                    </p>
                    <p v-if="addForm.errors.designation_category_id" class="mt-1 text-xs text-rose-600">
                        {{ addForm.errors.designation_category_id }}
                    </p>
                </div>

                <TextInput
                    v-model="addForm.amount"
                    label="Honorarium rate"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    :error="addForm.errors.amount"
                    hint="What this duty pays per examination. Can be left at 0 and set later."
                />

                <TextInput
                    v-model="addForm.rooms_per_slot"
                    label="Rooms covered per person"
                    type="number"
                    min="1"
                    optional
                    :error="addForm.errors.rooms_per_slot"
                    hint="Leave blank if this duty is not staffed per room. 1 means one per room; 5 means one per group of five, editable on the group's first room."
                />

                <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                    Give it a rooms-covered value and it is staffed in the per-room grid alongside
                    the built-in duties. Its honorarium appears on the payroll either way.
                </p>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showAdd = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="designation-add"
                    variant="primary"
                    size="sm"
                    :loading="addForm.processing"
                    :disabled="addForm.processing"
                >
                    Add
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Edit / move -->
        <BaseModal :show="!!editing" title="Edit designation" @close="editing = null">
            <form id="designation-edit" class="space-y-4" novalidate @submit.prevent="submitEdit">
                <TextInput v-model="editForm.label" label="Name" required :error="editForm.errors.label" />

                <SelectInput
                    v-model="editForm.designation_category_id"
                    label="Committee"
                    required
                    :options="committeesInSection"
                    :error="editForm.errors.designation_category_id"
                    hint="Moving a designation into the REC or an LEC also makes it a coverage duty."
                />

                <TextInput
                    v-model="editForm.amount"
                    label="Honorarium rate"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    :error="editForm.errors.amount"
                />

                <TextInput
                    v-model="editForm.rooms_per_slot"
                    label="Rooms covered per person"
                    type="number"
                    min="1"
                    optional
                    :error="editForm.errors.rooms_per_slot"
                    hint="Blank means it is not staffed per room. 1 means one per room. A higher number is only a starting default — each venue sets its own group size when room staffing is generated."
                />

                <CheckboxInput v-model="editForm.is_active">
                    In use
                    <span class="text-xs text-slate-400">— uncheck to stop it being offered for new assignments</span>
                </CheckboxInput>

                <p
                    v-if="!editForm.is_active && editing?.usage_count"
                    class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800"
                >
                    {{ editing.usage_count }}
                    {{ editing.usage_count === 1 ? 'record uses' : 'records use' }}
                    this designation. They are left exactly as they are.
                </p>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="editing = null">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="designation-edit"
                    variant="primary"
                    size="sm"
                    :loading="editForm.processing"
                    :disabled="editForm.processing"
                >
                    Save
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Delete -->
        <BaseModal :show="!!deleting" title="Delete designation" @close="deleting = null">
            <div v-if="deleteBlocked" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800">
                <strong>{{ deleting?.label }}</strong> is used by {{ deleting?.usage_count }}
                {{ deleting?.usage_count === 1 ? 'record' : 'records' }} and cannot be deleted.
                Switch it off instead so those records keep their meaning.
            </div>

            <form v-else id="designation-delete" novalidate @submit.prevent="submitDelete">
                <p class="text-sm leading-relaxed text-slate-600">
                    This permanently removes <strong>{{ deleting?.label }}</strong> and its rate.
                    Nothing currently uses it. This cannot be undone.
                </p>
                <div class="mt-4">
                    <TextInput
                        v-model="deleteForm.confirm_label"
                        label="Type the name to confirm"
                        :placeholder="deleting?.label"
                        :error="deleteForm.errors.confirm_label"
                    />
                </div>
            </form>

            <template #footer>
                <BaseButton variant="outline" size="sm" @click="deleting = null">Cancel</BaseButton>
                <BaseButton
                    v-if="!deleteBlocked"
                    type="submit"
                    form="designation-delete"
                    variant="accent"
                    size="sm"
                    :disabled="!deleteReady || deleteForm.processing"
                    :loading="deleteForm.processing"
                >
                    Delete permanently
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Committees -->
        <BaseModal :show="showCommittees" title="Committees" @close="showCommittees = false">
            <div class="space-y-4">
                <p class="text-sm text-slate-600">
                    Committees group the designations in <strong>{{ section?.name }}</strong>.
                    Built-in ones can be renamed but not removed.
                </p>

                <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                    <li
                        v-for="category in section?.categories ?? []"
                        :key="category.id"
                        class="flex items-center justify-between gap-2 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm text-slate-800">{{ category.label }}</p>
                            <p class="text-xs text-slate-400">
                                {{ category.designation_count }}
                                {{ category.designation_count === 1 ? 'designation' : 'designations' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <IconButton icon="pencil" label="Rename" @click="startCommitteeEdit(category)" />
                            <IconButton
                                v-if="!category.is_builtin"
                                icon="trash"
                                label="Delete"
                                @click="deletingCommittee = category"
                            />
                        </div>
                    </li>
                </ul>

                <form class="space-y-2 border-t border-slate-100 pt-4" novalidate @submit.prevent="submitCommittee">
                    <TextInput
                        v-model="committeeForm.label"
                        :label="editingCommittee ? `Rename “${editingCommittee.label}”` : 'New committee'"
                        required
                        :error="committeeForm.errors.label"
                    />
                    <div class="flex gap-2">
                        <BaseButton
                            type="submit"
                            variant="primary"
                            size="sm"
                            :loading="committeeForm.processing"
                            :disabled="committeeForm.processing"
                        >
                            {{ editingCommittee ? 'Rename' : 'Add committee' }}
                        </BaseButton>
                        <BaseButton v-if="editingCommittee" variant="outline" size="sm" @click="cancelCommitteeEdit">
                            Cancel
                        </BaseButton>
                    </div>
                </form>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showCommittees = false">Done</BaseButton>
            </template>
        </BaseModal>

        <BaseModal :show="!!deletingCommittee" title="Delete committee" @close="deletingCommittee = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Delete <strong>{{ deletingCommittee?.label }}</strong>?
            </p>
            <p
                v-if="deletingCommittee?.designation_count"
                class="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-800"
            >
                It still holds {{ deletingCommittee.designation_count }}
                {{ deletingCommittee.designation_count === 1 ? 'designation' : 'designations' }}.
                Move them to another committee first.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="deletingCommittee = null">Cancel</BaseButton>
                <BaseButton
                    variant="accent"
                    size="sm"
                    :disabled="!!deletingCommittee?.designation_count"
                    @click="confirmDeleteCommittee"
                >
                    Delete
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
