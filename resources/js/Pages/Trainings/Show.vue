<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    training: { type: Object, required: true },
    assignments: { type: Array, required: true },
    assignableMembers: { type: Array, required: true },
    can: { type: Object, required: true },
});

/* --- Add participant --- */
const memberSearch = ref('');
const assignForm = useForm({ member_id: '' });

const filteredMembers = computed(() => {
    const term = memberSearch.value.toLowerCase();
    const pool = term
        ? props.assignableMembers.filter((m) => m.label.toLowerCase().includes(term))
        : props.assignableMembers;
    return pool.slice(0, 50).map((m) => ({ value: m.id, label: m.label }));
});

const assign = () => assignForm.post(`/trainings/${props.training.id}/assignments`, {
    preserveScroll: true,
    onSuccess: () => {
        assignForm.reset();
        memberSearch.value = '';
    },
});

/* --- Edit attendance --- */
const editingAssignment = ref(null);
const editForm = useForm({ attended: false });

const openEdit = (assignment) => {
    editingAssignment.value = assignment;
    editForm.clearErrors();
    editForm.attended = assignment.attended;
};

const saveEdit = () => editForm.put(`/training-assignments/${editingAssignment.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (editingAssignment.value = null),
});

/* --- Remove participant --- */
const removing = ref(null);
const removeForm = useForm({});
const confirmRemove = () => removeForm.delete(`/training-assignments/${removing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (removing.value = null),
});

/* --- Mark completed --- */
const completeForm = useForm({});
const confirmingComplete = ref(false);
const complete = () => completeForm.post(`/trainings/${props.training.id}/complete`, {
    preserveScroll: true,
    onSuccess: () => (confirmingComplete.value = false),
});
</script>

<template>
    <Head :title="training.title" />

    <DashboardLayout>
        <DashboardPageHeader :title="training.title">
            <template #back>
                <BaseButton href="/trainings" variant="link">← Trainings</BaseButton>
            </template>
            <template #badges>
                <BaseBadge :variant="training.completed ? 'success' : 'warning'">
                    {{ training.completed ? 'Completed' : 'Scheduled' }}
                </BaseBadge>
                <span class="text-sm text-slate-500">
                    {{ training.type_label }} · {{ training.training_date }}
                    <template v-if="training.venue"> · {{ training.venue }}</template>
                </span>
            </template>
            <template v-if="can.complete" #actions>
                <BaseButton variant="primary" size="sm" @click="confirmingComplete = true">
                    <AppIcon name="check-circle" class="h-4 w-4" />
                    Mark Completed
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <!-- Add participant -->
        <div v-if="can.assign" class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-base font-semibold text-slate-900">Add a Participant</h2>
            <form class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="assign">
                <TextInput
                    v-model="memberSearch"
                    label="Search member"
                    placeholder="Name or PROCTAD ID"
                />
                <SelectInput
                    v-model="assignForm.member_id"
                    label="Member"
                    required
                    placeholder="Select a member"
                    :options="filteredMembers"
                    :error="assignForm.errors.member_id"
                />
                <BaseButton
                    type="submit"
                    variant="secondary"
                    size="sm"
                    :loading="assignForm.processing"
                    :disabled="assignForm.processing"
                >
                    Add
                </BaseButton>
            </form>
        </div>

        <!-- Participants -->
        <div v-if="assignments.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Member</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Testing Center</th>
                        <th class="px-3 py-2">Attendance</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="assignment in assignments" :key="assignment.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2">
                            <Link :href="`/members/${assignment.member.id}`" class="font-medium text-slate-900 hover:underline">
                                {{ assignment.member.name }}
                            </Link>
                            <p class="font-mono text-xs text-brand-700">{{ assignment.member.proctad_id }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ assignment.field_office?.name }}</td>
                        <td class="whitespace-nowrap px-3 py-2">
                            <span v-if="assignment.attended" class="inline-flex items-center gap-1.5 text-emerald-700">
                                <AppIcon name="check-circle" class="h-4 w-4" />
                                <span class="text-xs">{{ assignment.attendance_confirmed_at }}</span>
                            </span>
                            <span v-else class="text-slate-400">Not marked</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div v-if="assignment.can_manage" class="inline-flex gap-1">
                                <IconButton icon="pencil" label="Edit" @click="openEdit(assignment)" />
                                <IconButton icon="trash" label="Remove" variant="danger" @click="removing = assignment" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="users"
                title="No participants yet"
                description="Add PROCTAD members to build this training's attendance record."
            />
        </div>

        <!-- Edit attendance modal -->
        <BaseModal :show="!!editingAssignment" title="Update Attendance" @close="editingAssignment = null">
            <form id="attendance-form" class="space-y-4" novalidate @submit.prevent="saveEdit">
                <p class="text-sm text-slate-600">
                    <strong>{{ editingAssignment?.member.name }}</strong>
                    <span class="ml-1 font-mono text-xs text-brand-700">{{ editingAssignment?.member.proctad_id }}</span>
                </p>
                <CheckboxInput v-model="editForm.attended">Attendance confirmed</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="editingAssignment = null">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="attendance-form"
                    variant="primary"
                    size="sm"
                    :loading="editForm.processing"
                    :disabled="editForm.processing"
                >
                    Save
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Remove confirm -->
        <BaseModal :show="!!removing" title="Remove participant" @close="removing = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ removing?.member.name }}</strong> from {{ training.title }}?
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="removing = null">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="removeForm.processing" @click="confirmRemove">
                    Remove
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Complete confirm -->
        <BaseModal :show="confirmingComplete" title="Mark training completed" @close="confirmingComplete = false">
            <p class="text-sm leading-relaxed text-slate-600">
                This will mark <strong>{{ training.title }}</strong> as completed and automatically issue and
                email a Certificate of Completion to every attendance-confirmed participant. This cannot be undone.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="confirmingComplete = false">Cancel</BaseButton>
                <BaseButton variant="primary" size="sm" :loading="completeForm.processing" @click="complete">
                    Mark Completed
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
