<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import MemberForm from './MemberForm.vue';
import { fetchJson, messageFor } from '@/Composables/useJsonFetch';
import { useFormErrors } from '@/Composables/useFormErrors';
import { memberFieldLabels } from './memberFieldLabels';

const props = defineProps({
    show: { type: Boolean, required: true },
    memberId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const loaded = ref(false);
const loadError = ref(null);
const fieldOffices = ref([]);
const testingCenters = ref([]);
const statuses = ref([]);

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    sex: '',
    date_of_birth: '',
    email: '',
    mobile_number: '',
    agency: '',
    position: '',
    field_office_id: '',
    testing_center_id: '',
    status: '',
    disqualification_remarks: '',
    photo: null,
});

// Pull the user to the first rejected field — this form is long enough that a
// server rejection would otherwise leave them scrolling for red text.
useFormErrors(form);

watch(() => props.show, (open) => {
    if (open && props.memberId) {
        fetchEditData();
    } else if (!open) {
        loaded.value = false;
    }
});

const fetchEditData = async () => {
    loading.value = true;
    loaded.value = false;
    loadError.value = null;
    form.clearErrors();
    try {
        const json = await fetchJson(`/members/${props.memberId}/edit-data`);

        form.first_name = json.member.first_name ?? '';
        form.middle_name = json.member.middle_name ?? '';
        form.last_name = json.member.last_name ?? '';
        form.suffix = json.member.suffix ?? '';
        form.sex = json.member.sex ?? '';
        form.date_of_birth = json.member.date_of_birth ?? '';
        form.email = json.member.email ?? '';
        form.mobile_number = json.member.mobile_number ?? '';
        form.agency = json.member.agency ?? '';
        form.position = json.member.position ?? '';
        form.field_office_id = json.member.field_office_id ?? '';
        form.testing_center_id = json.member.testing_center_id ?? '';
        form.status = json.member.status ?? '';
        form.disqualification_remarks = json.member.disqualification_remarks ?? '';
        form.photo = null;

        fieldOffices.value = json.fieldOffices;
        testingCenters.value = json.testingCenters;
        statuses.value = json.statuses;
        loaded.value = true;
    } catch (e) {
        loaded.value = false;
        loadError.value = messageFor(e, 'Could not load member data.');
    } finally {
        loading.value = false;
    }
};

const submit = () => {
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(`/members/${props.memberId}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
};
</script>

<template>
    <BaseModal :show="show" title="Edit Member" max-width="2xl" @close="emit('close')">
        <div v-if="loading" class="flex items-center justify-center py-16">
            <p class="text-sm text-slate-500">Loading...</p>
        </div>

        <template v-else-if="loaded">
            <div>
                <form novalidate @submit.prevent="submit">
                    <FormErrorSummary
                        :errors="form.errors"
                        :labels="memberFieldLabels"
                        class="mb-5"
                    />
                    <MemberForm
                        :form="form"
                        :field-offices="fieldOffices"
                        :testing-centers="testingCenters"
                        :statuses="statuses"
                    />

                    <div class="mt-8 flex justify-end gap-3 pb-1">
                        <BaseButton variant="outline" size="sm" @click="emit('close')">Cancel</BaseButton>
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
        </template>

        <div v-else class="py-16 text-center text-sm text-slate-400">
            {{ loadError ?? 'Could not load member data.' }}
        </div>
    </BaseModal>
</template>
