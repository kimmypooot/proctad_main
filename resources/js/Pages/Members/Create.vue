<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import MemberForm from './Partials/MemberForm.vue';

const props = defineProps({
    fieldOffices: { type: Array, required: true },
});

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    sex: '',
    email: '',
    mobile_number: '',
    agency: '',
    position: '',
    field_office_id: props.fieldOffices.length === 1 ? props.fieldOffices[0].id : '',
    photo: null,
});

const submit = () => form.post('/members');
</script>

<template>
    <Head title="Add PROCTAD Member" />

    <DashboardLayout>
        <div class="mx-auto max-w-3xl">
            <DashboardPageHeader
                title="Add PROCTAD Member"
                subtitle="A permanent PROCTAD ID is generated automatically, along with a member login account."
            />

            <form class="mt-6 rounded-xl border border-slate-200 bg-white p-6" novalidate @submit.prevent="submit">
                <MemberForm :form="form" :field-offices="fieldOffices" />

                <div class="mt-8 flex justify-end gap-3">
                    <BaseButton href="/members" variant="outline" size="sm">Cancel</BaseButton>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        size="sm"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Register Member
                    </BaseButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
