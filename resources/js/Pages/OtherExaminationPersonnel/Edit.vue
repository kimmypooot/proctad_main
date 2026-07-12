<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import OtherExaminationPersonnelForm from './Partials/OtherExaminationPersonnelForm.vue';

const props = defineProps({
    oep: { type: Object, required: true },
    fieldOffices: { type: Array, required: true },
    personnelTypes: { type: Array, required: true },
});

const form = useForm({
    first_name: props.oep.first_name,
    middle_name: props.oep.middle_name ?? '',
    last_name: props.oep.last_name,
    suffix: props.oep.suffix ?? '',
    sex: props.oep.sex,
    personnel_type: props.oep.personnel_type,
    contact_number: props.oep.contact_number ?? '',
    email: props.oep.email ?? '',
    agency: props.oep.agency ?? '',
    position: props.oep.position ?? '',
    field_office_id: props.oep.field_office_id ?? '',
    is_active: props.oep.is_active,
    photo: null,
});

// File uploads can't ride a real PUT; use POST + method spoofing when a photo is attached.
const submit = () => form
    .transform((data) => ({ ...data, _method: 'put' }))
    .post(`/other-examination-personnel/${props.oep.id}`);
</script>

<template>
    <Head :title="`Edit ${oep.oep_id}`" />

    <DashboardLayout>
        <div class="mx-auto max-w-3xl">
            <DashboardPageHeader title="Edit Other Examination Personnel">
                <template #eyebrow>{{ oep.oep_id }}</template>
            </DashboardPageHeader>

            <form class="mt-6 rounded-xl border border-slate-200 bg-white p-6" novalidate @submit.prevent="submit">
                <OtherExaminationPersonnelForm :form="form" :field-offices="fieldOffices" :personnel-types="personnelTypes" editing />

                <div class="mt-8 flex justify-end gap-3">
                    <BaseButton :href="`/other-examination-personnel/${oep.id}`" variant="outline" size="sm">Cancel</BaseButton>
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
