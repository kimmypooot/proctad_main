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
    settings: { type: Array, required: true },
    groupOrder: { type: Array, default: () => [] },
    can: { type: Object, required: true },
});

/* ---------------------------------------------------------------------- */
/* Friendly view                                                          */
/* ---------------------------------------------------------------------- */
const showAdvanced = ref(false);

/** Groups in the server's declared order, skipping any with nothing in them. */
const populatedGroups = computed(() => props.groupOrder
    .map((name) => ({ name, settings: props.settings.filter((s) => s.group === name) }))
    .filter((group) => group.settings.length));

const isOn = (setting) => ['1', 'true'].includes(String(setting.value ?? ''));

/** Pending text/number edits, keyed by setting id — toggles save immediately. */
const drafts = ref({});
const isDirty = (setting) => drafts.value[setting.id] !== undefined
    && String(drafts.value[setting.id]) !== String(setting.value ?? '');

const savingId = ref(null);
const valueForm = useForm({ value: '', description: '', is_public: false });

/**
 * The update endpoint replaces description and is_public alongside the value,
 * so both are echoed back unchanged — otherwise saving a value here would wipe
 * the setting's description.
 */
const saveValue = (setting, value) => {
    savingId.value = setting.id;
    valueForm.value = String(value);
    valueForm.description = setting.description ?? '';
    valueForm.is_public = setting.is_public;
    valueForm.put(`/settings/${setting.id}`, {
        preserveScroll: true,
        onSuccess: () => delete drafts.value[setting.id],
        onFinish: () => (savingId.value = null),
    });
};

const typeOptions = ['string', 'number', 'boolean', 'json'];

const showCreate = ref(false);
const editing = ref(null);
const deleting = ref(null);

const createForm = useForm({ key: '', value: '', type: 'string', description: '', is_public: false });
const editForm = useForm({ value: '', description: '', is_public: false });

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreate.value = true;
};

const submitCreate = () => createForm.post('/settings', {
    preserveScroll: true,
    onSuccess: () => (showCreate.value = false),
});

const openEdit = (setting) => {
    editing.value = setting;
    editForm.clearErrors();
    editForm.value = setting.value ?? '';
    editForm.description = setting.description ?? '';
    editForm.is_public = setting.is_public;
};

const submitEdit = () => editForm.put(`/settings/${editing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
});

const destroyForm = useForm({});
const confirmDelete = () => destroyForm.delete(`/settings/${deleting.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (deleting.value = null),
});
</script>

<template>
    <Head title="System Settings" />

    <DashboardLayout>
        <DashboardPageHeader title="System Settings">
            <template #subtitle>
                Options that change how PROCTAD behaves. Changes take effect straight away.
            </template>
        </DashboardPageHeader>

        <!-- Friendly view: one card per group, real controls, no raw keys. -->
        <div class="mt-6 space-y-5">
            <section
                v-for="group in populatedGroups"
                :key="group.name"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white"
            >
                <header class="border-b border-slate-200 bg-slate-50 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">{{ group.name }}</h2>
                </header>

                <div class="divide-y divide-slate-100">
                    <div
                        v-for="setting in group.settings"
                        :key="setting.id"
                        class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6"
                    >
                        <div class="min-w-0 sm:max-w-xl">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-slate-900">{{ setting.label }}</p>
                                <BaseBadge
                                    v-if="setting.control === 'toggle'"
                                    :variant="isOn(setting) ? 'success' : 'accent'"
                                    size="xs"
                                >
                                    {{ isOn(setting) ? setting.on_label : setting.off_label }}
                                </BaseBadge>
                            </div>
                            <p v-if="setting.help" class="mt-1 text-sm leading-relaxed text-slate-500">
                                {{ setting.help }}
                            </p>
                        </div>

                        <div class="shrink-0 sm:pt-0.5">
                            <!-- Toggle: saves on click, no separate Save step. -->
                            <BaseButton
                                v-if="setting.control === 'toggle'"
                                :variant="isOn(setting) ? 'outline' : 'primary'"
                                size="sm"
                                :disabled="!can.manage || savingId === setting.id"
                                :loading="savingId === setting.id"
                                @click="saveValue(setting, isOn(setting) ? '0' : '1')"
                            >
                                {{ isOn(setting) ? 'Turn off' : 'Turn on' }}
                            </BaseButton>

                            <div v-else-if="setting.control === 'select'" class="w-full sm:w-72">
                                <select
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                    :value="setting.value"
                                    :disabled="!can.manage || savingId === setting.id"
                                    @change="saveValue(setting, $event.target.value)"
                                >
                                    <option v-for="opt in setting.options" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>

                            <div v-else class="flex items-center gap-2">
                                <input
                                    :type="setting.control === 'number' ? 'number' : 'text'"
                                    min="0"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 sm:w-48"
                                    :value="drafts[setting.id] ?? setting.value"
                                    :disabled="!can.manage"
                                    @input="drafts[setting.id] = $event.target.value"
                                />
                                <span v-if="setting.suffix" class="text-sm text-slate-500">{{ setting.suffix }}</span>
                                <BaseButton
                                    v-if="can.manage && isDirty(setting)"
                                    variant="primary"
                                    size="sm"
                                    :loading="savingId === setting.id"
                                    @click="saveValue(setting, drafts[setting.id])"
                                >
                                    Save
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Raw editor kept for anything the catalogue doesn't cover, but out of
             the way of people who just want to change a setting. -->
        <div v-if="can.manage" class="mt-6">
            <button
                type="button"
                class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-slate-700"
                @click="showAdvanced = !showAdvanced"
            >
                <AppIcon :name="showAdvanced ? 'chevron-down' : 'chevron-right'" class="h-3.5 w-3.5" />
                Advanced — raw configuration values
            </button>
            <p v-if="showAdvanced" class="mt-2 text-xs leading-relaxed text-slate-500">
                These are the stored keys and values behind the options above. Only change them if you know what a
                key does. SMTP credentials are not here — they live in the server's
                <code class="text-xs">.env</code> file.
            </p>
        </div>

        <template v-if="showAdvanced">
        <div class="mt-3 flex justify-end">
            <BaseButton v-if="can.manage" variant="outline" size="sm" @click="openCreate">
                Add Setting
            </BaseButton>
        </div>

        <div v-if="settings.length" class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Key</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Type</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Description</th>
                        <th class="hidden px-3 py-2 md:table-cell">Visibility</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="setting in settings" :key="setting.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 font-mono text-xs font-medium text-slate-900">{{ setting.key }}</td>
                        <td class="max-w-[10rem] truncate px-3 py-2 text-slate-600" :title="setting.value || ''">{{ setting.value || '—' }}</td>
                        <td class="hidden px-3 py-2 sm:table-cell"><BaseBadge variant="neutral">{{ setting.type }}</BaseBadge></td>
                        <td class="hidden max-w-xs truncate px-3 py-2 text-slate-500 xl:table-cell" :title="setting.description || ''">{{ setting.description || '—' }}</td>
                        <td class="hidden px-3 py-2 md:table-cell">
                            <BaseBadge :variant="setting.is_public ? 'success' : 'neutral'">
                                {{ setting.is_public ? 'Public' : 'Internal' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div v-if="can.manage" class="inline-flex gap-1">
                                <IconButton icon="pencil" label="Edit" @click="openEdit(setting)" />
                                <IconButton icon="trash" label="Remove" variant="danger" @click="deleting = setting" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-3">
            <EmptyState
                icon="cog-6-tooth"
                title="No settings configured"
                description="Add a key-value setting to control runtime behavior without a deploy."
            />
        </div>
        </template>

        <!-- Create modal -->
        <BaseModal :show="showCreate" title="Add Setting" @close="showCreate = false">
            <form id="setting-create-form" class="space-y-4" novalidate @submit.prevent="submitCreate">
                <TextInput
                    v-model="createForm.key"
                    label="Key"
                    required
                    :error="createForm.errors.key"
                    placeholder="e.g. reminder_days_before_exam"
                />
                <TextInput v-model="createForm.value" label="Value" optional :error="createForm.errors.value" />
                <SelectInput v-model="createForm.type" label="Type" required :options="typeOptions" :error="createForm.errors.type" />
                <TextInput v-model="createForm.description" label="Description" optional :error="createForm.errors.description" />
                <CheckboxInput v-model="createForm.is_public">Public (safe to expose to the member portal / public pages)</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showCreate = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="setting-create-form"
                    variant="primary"
                    size="sm"
                    :loading="createForm.processing"
                    :disabled="createForm.processing"
                >
                    Add Setting
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Edit modal -->
        <BaseModal :show="!!editing" :title="`Edit “${editing?.key}”`" @close="editing = null">
            <form id="setting-edit-form" class="space-y-4" novalidate @submit.prevent="submitEdit">
                <TextInput v-model="editForm.value" label="Value" optional :error="editForm.errors.value" />
                <TextInput v-model="editForm.description" label="Description" optional :error="editForm.errors.description" />
                <CheckboxInput v-model="editForm.is_public">Public</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="editing = null">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="setting-edit-form"
                    variant="primary"
                    size="sm"
                    :loading="editForm.processing"
                    :disabled="editForm.processing"
                >
                    Save Changes
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Delete confirm -->
        <BaseModal :show="!!deleting" title="Remove setting" @close="deleting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong class="font-mono">{{ deleting?.key }}</strong>? Any code reading this key will fall back to its default.
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
