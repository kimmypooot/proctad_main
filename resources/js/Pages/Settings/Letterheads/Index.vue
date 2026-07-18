<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    letterheads: { type: Array, required: true },
});

const fileInput = ref(null);
const deleting = ref(null);

const form = useForm({
    name: '',
    file: null,
    activate: true,
});

const submit = () => form.post('/letterheads', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
        form.reset();
        if (fileInput.value) fileInput.value.value = '';
    },
});

const activate = (letterhead) => useForm({}).post(`/letterheads/${letterhead.id}/activate`, { preserveScroll: true });

const confirmDelete = () => useForm({}).delete(`/letterheads/${deleting.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (deleting.value = null),
});
</script>

<template>
    <Head title="Letterheads" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Letterheads"
            subtitle="The active letterhead is composited as the background of every newly released certificate. Updating or activating a letterhead never changes certificates already issued."
        />

        <!-- Upload -->
        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-base font-semibold text-slate-900">Upload a Letterhead</h2>
            <form class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-start" novalidate @submit.prevent="submit">
                <TextInput v-model="form.name" label="Name" required placeholder="e.g. Official Letterhead 2026" :error="form.errors.name" />

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">
                        Letterhead File <span class="text-accent-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/png,image/jpeg,image/webp,application/pdf"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium min-h-[2.75rem]"
                        @change="form.file = $event.target.files[0]"
                    >
                    <p v-if="form.errors.file" class="mt-1.5 text-sm text-accent-600" role="alert">{{ form.errors.file }}</p>
                    <p v-else class="mt-1.5 text-xs text-slate-400">PDF, PNG, JPG, or WEBP — full-page portrait, max 5MB.</p>
                </div>

                <CheckboxInput v-model="form.activate" class="sm:mt-7">Set active</CheckboxInput>

                <div class="sm:mt-7">
                    <BaseButton type="submit" variant="primary" size="sm" :loading="form.processing" :disabled="form.processing">
                        Upload
                    </BaseButton>
                </div>
            </form>
        </div>

        <!-- List -->
        <div v-if="letterheads.length" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="letterhead in letterheads" :key="letterhead.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <img v-if="!letterhead.is_pdf" :src="letterhead.preview_url" :alt="letterhead.name" class="h-48 w-full border-b border-slate-100 object-cover object-top">
                <a
                    v-else
                    :href="letterhead.preview_url"
                    target="_blank"
                    rel="noopener"
                    class="flex h-48 w-full flex-col items-center justify-center gap-2 border-b border-slate-100 bg-slate-50 text-slate-400 hover:text-slate-500"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-10 w-10" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z" />
                    </svg>
                    <span class="text-xs font-medium">PDF &middot; open to preview</span>
                </a>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium text-slate-900">{{ letterhead.name }}</p>
                        <BaseBadge v-if="letterhead.is_active" variant="success">Active</BaseBadge>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-400">Uploaded {{ letterhead.uploaded_at }}</p>
                    <div class="mt-3 flex gap-1">
                        <IconButton v-if="!letterhead.is_active" icon="check-circle" label="Set Active" @click="activate(letterhead)" />
                        <IconButton icon="trash" label="Delete" variant="danger" @click="deleting = letterhead" />
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="document-text"
                title="No letterheads uploaded"
                description="Certificates will use the built-in design until a letterhead is uploaded and activated."
            />
        </div>

        <!-- Delete confirm -->
        <BaseModal :show="!!deleting" title="Remove letterhead" @close="deleting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ deleting?.name }}</strong>? Certificates already released keep their original appearance.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="deleting = null">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" @click="confirmDelete">Remove</BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
