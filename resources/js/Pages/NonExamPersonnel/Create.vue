<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import NonExamPersonnelForm from './Partials/NonExamPersonnelForm.vue';

const props = defineProps({
    fieldOffices: { type: Array, required: true },
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
    is_active: true,
    photo: null,
});

const submit = () => form.post('/non-exam-personnel');
</script>

<template>
    <Head title="Add Non-Exam Personnel" />

    <DashboardLayout>
        <div class="mx-auto max-w-3xl">
            <DashboardPageHeader
                title="Add Non-Exam Personnel"
                subtitle="A permanent NEP ID is generated automatically for coordinators, inspectors, PNP officers, and other support staff."
            />

            <form class="mt-6 rounded-xl border border-slate-200 bg-white p-6" novalidate @submit.prevent="submit">
                <NonExamPersonnelForm :form="form" :field-offices="fieldOffices" :personnel-types="personnelTypes" />

                <div class="mt-8 flex justify-end gap-3">
                    <BaseButton href="/non-exam-personnel" variant="outline" size="sm">Cancel</BaseButton>
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
