<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import OtherExaminationPersonnelForm from './Partials/OtherExaminationPersonnelForm.vue';
import { useFormErrors } from '@/Composables/useFormErrors';
import { oepFieldLabels } from './Partials/oepFieldLabels';

const props = defineProps({
    fieldOffices: { type: Array, required: true },
    testingCenters: { type: Array, default: () => [] },
    personnelTypes: { type: Array, required: true },
});

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    sex: '',
    personnel_type: '',
    contact_number: '',
    email: '',
    agency: '',
    position: '',
    field_office_id: props.fieldOffices.length === 1 ? props.fieldOffices[0].id : '',
    testing_center_id: props.testingCenters.length === 1 ? props.testingCenters[0].id : '',
    is_active: true,
    photo: null,
});

useFormErrors(form);

const submit = () => form.post('/other-examination-personnel');
</script>

<template>
    <Head title="Add Other Examination Personnel" />

    <DashboardLayout>
        <div class="mx-auto max-w-3xl">
            <DashboardPageHeader
                title="Add Other Examination Personnel"
                subtitle="A permanent OEP ID is generated automatically for coordinators, inspectors, PNP officers, and other support staff."
            />

            <form class="mt-6 rounded-xl border border-slate-200 bg-white p-6" novalidate @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" :labels="oepFieldLabels" class="mb-5" />
                <OtherExaminationPersonnelForm
                    :form="form"
                    :field-offices="fieldOffices"
                    :testing-centers="testingCenters"
                    :personnel-types="personnelTypes"
                />

                <div class="mt-8 flex justify-end gap-3">
                    <BaseButton href="/other-examination-personnel" variant="outline" size="sm">Cancel</BaseButton>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        size="sm"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Register
                    </BaseButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
