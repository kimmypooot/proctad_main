<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
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
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    signatories: { type: Array, required: true },
    fieldOffices: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const form = useForm({
    name: '',
    position: '',
    field_office_id: '',
    active: true,
    signature: null,
    remove_signature: false,
});

/** Local object URL for previewing a freshly picked file before it's uploaded. */
const signaturePreview = ref(null);
const setSignaturePreview = (file) => {
    if (signaturePreview.value) URL.revokeObjectURL(signaturePreview.value);
    signaturePreview.value = file ? URL.createObjectURL(file) : null;
};

const onSignaturePicked = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.signature = file;
    form.remove_signature = false;
    setSignaturePreview(file);
};

/** Clears both a pending pick and an already-stored image. */
const clearSignature = () => {
    form.signature = null;
    form.remove_signature = true;
    setSignaturePreview(null);
};

onBeforeUnmount(() => setSignaturePreview(null));

const scopeOptions = [
    { value: 'region', label: 'Region-wide (default for all Field Offices)' },
    ...props.fieldOffices.map((fo) => ({ value: fo.id, label: fo.name })),
];

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    setSignaturePreview(null);
    // FO admins can only assign their single office; preselect it.
    form.field_office_id = props.fieldOffices.length === 1 ? props.fieldOffices[0].id : 'region';
    showForm.value = true;
};

const openEdit = (signatory) => {
    editing.value = signatory;
    form.clearErrors();
    form.name = signatory.name;
    form.position = signatory.position;
    form.field_office_id = signatory.field_office_id ?? 'region';
    form.active = signatory.active;
    form.signature = null;
    form.remove_signature = false;
    setSignaturePreview(null);
    showForm.value = true;
};

/** Existing stored image, unless the user has just replaced or cleared it. */
const currentSignatureUrl = computed(() => {
    if (signaturePreview.value) return signaturePreview.value;
    if (form.remove_signature) return null;
    return editing.value?.signature_url ?? null;
});

const submit = () => {
    const transform = (data) => ({
        ...data,
        field_office_id: data.field_office_id === 'region' ? null : data.field_office_id,
        // PHP can't parse a multipart PUT body, so edits go out as POST with
        // Laravel's method override — required now that this form carries a file.
        ...(editing.value ? { _method: 'put' } : {}),
    });

    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    const url = editing.value ? `/signatories/${editing.value.id}` : '/signatories';

    form.transform(transform).post(url, options);
};

const destroyForm = useForm({});
const confirmDelete = () => {
    destroyForm.delete(`/signatories/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (deleting.value = null),
    });
};
</script>

<template>
    <Head title="Signatories" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Signatories"
            subtitle="Authorized signatories for PROCTAD IDs and certificates. Changes never affect previously issued documents."
        >
            <template v-if="can.create" #actions>
                <BaseButton variant="primary" size="sm" icon="plus" @click="openCreate">
                    Add Signatory
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <!-- Mobile (below md): a card per signatory so the edit/remove actions stay on screen. -->
        <div v-if="signatories.length" class="mt-6 space-y-3 md:hidden">
            <div
                v-for="signatory in signatories"
                :key="`m-${signatory.id}`"
                class="rounded-xl border border-slate-200 bg-white p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ signatory.name }}</p>
                        <p class="text-xs text-slate-500">{{ signatory.position }}</p>
                    </div>
                    <BaseBadge :variant="signatory.active ? 'success' : 'neutral'" class="shrink-0">
                        {{ signatory.active ? 'Active' : 'Inactive' }}
                    </BaseBadge>
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <BaseBadge :variant="signatory.field_office ? 'neutral' : 'brand'">
                        {{ signatory.field_office?.name ?? 'Region-wide' }}
                    </BaseBadge>
                    <img
                        v-if="signatory.signature_url"
                        :src="signatory.signature_url"
                        :alt="`${signatory.name} signature`"
                        class="h-8 w-auto max-w-[140px] object-contain"
                    >
                    <BaseBadge
                        v-else-if="signatory.active"
                        variant="warning"
                        title="No e-signature uploaded — certificates issued now will have a blank signature line."
                    >
                        No e-signature
                    </BaseBadge>
                    <span v-else class="text-xs text-slate-400">Signed by hand</span>
                </div>
                <div v-if="signatory.can_manage" class="mt-3 flex gap-1 border-t border-slate-100 pt-3">
                    <IconButton icon="pencil" label="Edit" @click="openEdit(signatory)" />
                    <IconButton icon="trash" label="Remove" variant="danger" @click="deleting = signatory" />
                </div>
            </div>
        </div>

        <div v-if="signatories.length" class="mt-6 hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Position</th>
                        <th class="hidden px-3 py-2 lg:table-cell">E-Signature</th>
                        <th class="hidden px-3 py-2 md:table-cell">Scope</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="signatory in signatories" :key="signatory.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 font-medium text-slate-900">
                            {{ signatory.name }}
                            <p class="text-xs font-normal text-slate-400 sm:hidden">{{ signatory.position }}</p>
                        </td>
                        <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ signatory.position }}</td>
                        <td class="hidden px-3 py-2 lg:table-cell">
                            <img
                                v-if="signatory.signature_url"
                                :src="signatory.signature_url"
                                :alt="`${signatory.name} signature`"
                                class="h-8 w-auto max-w-[140px] object-contain"
                            />
                            <BaseBadge
                        v-else-if="signatory.active"
                        variant="warning"
                        title="No e-signature uploaded — certificates issued now will have a blank signature line."
                    >
                        No e-signature
                    </BaseBadge>
                    <span v-else class="text-xs text-slate-400">Signed by hand</span>
                        </td>
                        <td class="hidden px-3 py-2 md:table-cell">
                            <BaseBadge :variant="signatory.field_office ? 'neutral' : 'brand'">
                                {{ signatory.field_office?.name ?? 'Region-wide' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="signatory.active ? 'success' : 'neutral'">
                                {{ signatory.active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div v-if="signatory.can_manage" class="inline-flex gap-1">
                                <IconButton icon="pencil" label="Edit" @click="openEdit(signatory)" />
                                <IconButton icon="trash" label="Remove" variant="danger" @click="deleting = signatory" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="identification"
                title="No signatories yet"
                description="Add the officials whose names appear on PROCTAD IDs and certificates."
            />
        </div>

        <!-- Create / edit modal -->
        <BaseModal :show="showForm" :title="editing ? 'Edit Signatory' : 'Add Signatory'" @close="showForm = false">
            <form id="signatory-form" class="space-y-4" novalidate @submit.prevent="submit">
                <TextInput v-model="form.name" label="Full Name" required :error="form.errors.name" placeholder="e.g. Atty. Juana D. Reyes" />
                <TextInput v-model="form.position" label="Position" required :error="form.errors.position" placeholder="e.g. Director IV" />
                <SelectInput
                    v-model="form.field_office_id"
                    label="Scope"
                    required
                    :options="scopeOptions"
                    :error="form.errors.field_office_id"
                />
                <div>
                    <p class="mb-1.5 block text-sm font-medium text-slate-700">
                        E-Signature <span class="font-normal text-slate-400">(optional)</span>
                    </p>

                    <div v-if="currentSignatureUrl" class="mb-2 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <img :src="currentSignatureUrl" alt="Signature preview" class="h-12 w-auto max-w-[60%] object-contain" />
                        <button type="button" class="ml-auto text-xs font-semibold text-accent-600 hover:text-accent-700" @click="clearSignature">
                            Remove
                        </button>
                    </div>

                    <input
                        type="file"
                        accept="image/png"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100"
                        @change="onSignaturePicked"
                    />

                    <p v-if="form.errors.signature" class="mt-1.5 text-sm text-accent-600" role="alert">
                        {{ form.errors.signature }}
                    </p>
                    <p v-else class="mt-1.5 text-xs leading-relaxed text-slate-500">
                        Transparent PNG, max 2&nbsp;MB. Printed above the name on certificates. Leave empty to keep
                        signing by hand. Certificates already issued keep the signature they were released with.
                    </p>
                </div>

                <CheckboxInput v-model="form.active">Active (used on newly issued IDs and certificates)</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showForm = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="signatory-form"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save Changes' : 'Add Signatory' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Delete confirm -->
        <BaseModal :show="!!deleting" title="Remove signatory" @close="deleting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ deleting?.name }}</strong> from the signatory list?
                Previously issued IDs and certificates are not affected.
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
