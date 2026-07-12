<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import NonExamPersonnelForm from './Partials/NonExamPersonnelForm.vue';

const props = defineProps({
    nep: { type: Object, required: true },
    fieldOffices: { type: Array, required: true },
    personnelTypes: { type: Array, required: true },
});

const form = useForm({
    first_name: props.nep.first_name,
    middle_name: props.nep.middle_name ?? '',
    last_name: props.nep.last_name,
    suffix: props.nep.suffix ?? '',
    sex: props.nep.sex,
    personnel_type: props.nep.personnel_type,
    contact_number: props.nep.contact_number ?? '',
    email: props.nep.email ?? '',
    agency: props.nep.agency ?? '',
    position: props.nep.position ?? '',
    field_office_id: props.nep.field_office_id ?? '',
    is_active: props.nep.is_active,
    photo: null,
});

// File uploads can't ride a real PUT; use POST + method spoofing when a photo is attached.
const submit = () => form
    .transform((data) => ({ ...data, _method: 'put' }))
    .post(`/non-exam-personnel/${props.nep.id}`);
</script>

<template>
    <Head :title="`Edit ${nep.nep_id}`" />

    <DashboardLayout>
        <div class="mx-auto max-w-3xl">
            <DashboardPageHeader title="Edit Non-Exam Personnel">
                <template #eyebrow>{{ nep.nep_id }}</template>
            </DashboardPageHeader>

            <form class="mt-6 rounded-xl border border-slate-200 bg-white p-6" novalidate @submit.prevent="submit">
                <NonExamPersonnelForm :form="form" :field-offices="fieldOffices" :personnel-types="personnelTypes" editing />

                <div class="mt-8 flex justify-end gap-3">
                    <BaseButton :href="`/non-exam-personnel/${nep.id}`" variant="outline" size="sm">Cancel</BaseButton>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        size="sm"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Save Changes
                    </BaseButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
