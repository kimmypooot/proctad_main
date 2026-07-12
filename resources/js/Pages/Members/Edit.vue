<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import MemberForm from './Partials/MemberForm.vue';

const props = defineProps({
    member: { type: Object, required: true },
    fieldOffices: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const form = useForm({
    first_name: props.member.first_name,
    middle_name: props.member.middle_name ?? '',
    last_name: props.member.last_name,
    suffix: props.member.suffix ?? '',
    sex: props.member.sex,
    email: props.member.email,
    mobile_number: props.member.mobile_number,
    agency: props.member.agency,
    position: props.member.position ?? '',
    field_office_id: props.member.field_office_id,
    status: props.member.status,
    disqualification_remarks: props.member.disqualification_remarks ?? '',
    photo: null,
});

// File uploads can't ride a real PUT; use POST + method spoofing when a photo is attached.
const submit = () => form
    .transform((data) => ({ ...data, _method: 'put' }))
    .post(`/members/${props.member.id}`);
</script>

<template>
    <Head :title="`Edit ${member.proctad_id}`" />

    <DashboardLayout>
        <div class="mx-auto max-w-3xl">
            <DashboardPageHeader title="Edit Member">
                <template #eyebrow>{{ member.proctad_id }}</template>
            </DashboardPageHeader>

            <form class="mt-6 rounded-xl border border-slate-200 bg-white p-6" novalidate @submit.prevent="submit">
                <MemberForm :form="form" :field-offices="fieldOffices" :statuses="statuses" />

                <div class="mt-8 flex justify-end gap-3">
                    <BaseButton :href="`/members/${member.id}`" variant="outline" size="sm">Cancel</BaseButton>
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
