<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { useDetailsResource } from '@/Composables/useDetailsResource';

const props = defineProps({
    show: { type: Boolean, required: true },
    trainingId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);

const { loading, data: raw, error, load } = useDetailsResource(
    () => `/trainings/${props.trainingId}/modal`,
    'Could not load training details.',
);

const training = () => raw.value?.training ?? null;
const assignments = () => raw.value?.assignments ?? [];
const assignableMembers = () => raw.value?.assignableMembers ?? [];
const can = () => raw.value?.can ?? { assign: false, manage: false, complete: false };

const memberSearch = ref('');
const assignForm = useForm({ member_id: '' });

const filteredMembers = computed(() => {
    const term = memberSearch.value.toLowerCase();
    const pool = term
        ? assignableMembers().filter((m) => m.label.toLowerCase().includes(term))
        : assignableMembers();
    return pool.slice(0, 50).map((m) => ({ value: m.id, label: m.label }));
});

const assign = () => assignForm.post(`/trainings/${training().id}/assignments`, {
    preserveScroll: true,
    onSuccess: () => {
        assignForm.reset();
        memberSearch.value = '';
        load();
    },
});

const editingAssignment = ref(null);
const editForm = useForm({ attended: false });

const openEdit = (assignment) => {
    editingAssignment.value = assignment;
    editForm.clearErrors();
    editForm.attended = assignment.attended;
};

const saveEdit = () => editForm.put(`/training-assignments/${editingAssignment.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
        editingAssignment.value = null;
        load();
    },
});

const removing = ref(null);
const removeForm = useForm({});
const confirmRemove = () => removeForm.delete(`/training-assignments/${removing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
        removing.value = null;
        load();
    },
});

const completeForm = useForm({});
const confirmingComplete = ref(false);
const complete = () => completeForm.post(`/trainings/${training().id}/complete`, {
    preserveScroll: true,
    onSuccess: () => {
        confirmingComplete.value = false;
        load();
        emit('saved');
    },
});

watch(() => props.show, (open) => {
    if (open && props.trainingId) {
        load();
    }
});

</script>

<template>
    <BaseModal :show="show" title="Training Details" max-width="4xl" @close="emit('close')">
        <div v-if="loading" class="max-h-[75vh] space-y-6 overflow-y-auto -mx-6 -mt-5 px-6 pt-5 animate-pulse">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="h-3 w-24 rounded bg-slate-200" />
                    <div class="h-5 w-56 rounded bg-slate-200" />
                    <div class="flex gap-2">
                        <div class="h-5 w-16 rounded-full bg-slate-200" />
                        <div class="h-5 w-28 rounded bg-slate-200" />
                    </div>
                </div>
                <div class="h-8 w-28 rounded-lg bg-slate-200" />
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="h-4 w-4 rounded bg-slate-200" />
                    <div class="h-5 w-44 rounded bg-slate-200" />
                </div>
                <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    <div class="h-8 rounded-lg bg-slate-200" />
                    <div class="h-8 rounded-lg bg-slate-200" />
                    <div class="h-8 w-16 rounded-lg bg-slate-200" />
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white">
                <div class="px-5 py-3 border-b border-slate-100">
                    <div class="h-5 w-28 rounded bg-slate-200" />
                </div>
                <div class="p-5 space-y-3">
                    <div v-for="i in 3" :key="i" class="flex items-center gap-4">
                        <div class="h-4 flex-1 rounded bg-slate-200" />
                        <div class="h-4 w-20 rounded bg-slate-200 hidden sm:block" />
                        <div class="h-4 w-24 rounded bg-slate-200" />
                        <div class="h-4 w-16 rounded bg-slate-200" />
                    </div>
                </div>
            </div>
        </div>

        <template v-else-if="raw">
            <div class="max-h-[75vh] space-y-6 overflow-y-auto -mx-6 -mt-5 px-6 pt-5">
                <!-- Header -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-brand-700">
                            {{ training().type_label }} · {{ training().training_date }}
                        </p>
                        <h3 class="text-xl font-semibold text-slate-900">{{ training().title }}</h3>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <BaseBadge :variant="training().completed ? 'success' : 'warning'">
                                {{ training().completed ? 'Completed' : 'Scheduled' }}
                            </BaseBadge>
                            <span v-if="training().field_office" class="text-xs text-slate-500">
                                <AppIcon name="building-office" class="inline h-3.5 w-3.5" />
                                {{ training().field_office.name }}
                            </span>
                            <span v-if="training().exam" class="text-xs text-slate-500">
                                <AppIcon name="briefcase" class="inline h-3.5 w-3.5" />
                                {{ training().exam.title }}
                            </span>
                            <span v-if="training().venue" class="text-xs text-slate-500">
                                <AppIcon name="map-pin" class="inline h-3.5 w-3.5" />
                                {{ training().venue }}
                            </span>
                            <span v-if="training().end_date" class="text-xs text-slate-500">
                                <AppIcon name="calendar" class="inline h-3.5 w-3.5" />
                                until {{ training().end_date }}
                            </span>
                        </div>
                    </div>
                    <BaseButton
                        v-if="can().complete"
                        variant="primary"
                        size="sm"
                        @click="confirmingComplete = true"
                    >
                        <AppIcon name="check-circle" class="h-4 w-4" />
                        Mark Completed
                    </BaseButton>
                </div>

                <!-- Add participant -->
                <div v-if="can().assign" class="rounded-xl border border-slate-200 bg-white p-5">
                    <h4 class="text-base font-semibold text-slate-900">Add a Participant</h4>
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
                <div>
                    <h4 class="text-base font-semibold text-slate-900">
                        Participants
                        <span v-if="assignments().length" class="ml-1 text-sm font-normal text-slate-400">({{ assignments().length }})</span>
                    </h4>
                    <div v-if="assignments().length" class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
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
                                <tr v-for="assignment in assignments()" :key="assignment.id" class="transition-colors hover:bg-brand-50/40">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-slate-900">{{ assignment.member.name }}</p>
                                        <p class="font-mono text-xs text-brand-700">{{ assignment.member.proctad_id }}</p>
                                    </td>
                                    <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">
                                        {{ assignment.field_office?.name ?? '—' }}
                                    </td>
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
                    <div v-else class="mt-3">
                        <EmptyState
                            icon="users"
                            title="No participants yet"
                            description="Add PROCTAD members to build this training's attendance record."
                        />
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="py-16 text-center text-sm text-slate-400">
            {{ error ?? 'Could not load training details.' }}
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
                Remove <strong>{{ removing?.member.name }}</strong> from {{ training()?.title }}?
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
                This will mark <strong>{{ training()?.title }}</strong> as completed and automatically issue and
                email a Certificate of Completion to every attendance-confirmed participant. This cannot be undone.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="confirmingComplete = false">Cancel</BaseButton>
                <BaseButton variant="primary" size="sm" :loading="completeForm.processing" @click="complete">
                    Mark Completed
                </BaseButton>
            </template>
        </BaseModal>
    </BaseModal>
</template>
