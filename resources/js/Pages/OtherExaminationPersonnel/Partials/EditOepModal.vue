<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import OtherExaminationPersonnelForm from './OtherExaminationPersonnelForm.vue';
import { fetchJson, messageFor } from '@/Composables/useJsonFetch';
import { useFormErrors } from '@/Composables/useFormErrors';
import { oepFieldLabels } from './oepFieldLabels';

const props = defineProps({
    show: { type: Boolean, required: true },
    oepId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const loaded = ref(false);
const loadError = ref(null);
const fieldOffices = ref([]);
const testingCenters = ref([]);
const personnelTypes = ref([]);

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
    field_office_id: '',
    testing_center_id: '',
    is_active: true,
    photo: null,
});

// Move the user to the first rejected field on a server-side validation bounce.
useFormErrors(form);

watch(() => props.show, (open) => {
    if (open && props.oepId) {
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
        const json = await fetchJson(`/other-examination-personnel/${props.oepId}/edit-data`);

        form.first_name = json.oep.first_name ?? '';
        form.middle_name = json.oep.middle_name ?? '';
        form.last_name = json.oep.last_name ?? '';
        form.suffix = json.oep.suffix ?? '';
        form.sex = json.oep.sex ?? '';
        form.personnel_type = json.oep.personnel_type ?? '';
        form.contact_number = json.oep.contact_number ?? '';
        form.email = json.oep.email ?? '';
        form.agency = json.oep.agency ?? '';
        form.position = json.oep.position ?? '';
        form.field_office_id = json.oep.field_office_id ?? '';
        form.testing_center_id = json.oep.testing_center_id ?? '';
        form.is_active = json.oep.is_active;
        form.photo = null;

        fieldOffices.value = json.fieldOffices;
        testingCenters.value = json.testingCenters;
        personnelTypes.value = json.personnelTypes;
        loaded.value = true;
    } catch (e) {
        loaded.value = false;
        loadError.value = messageFor(e, 'Could not load personnel data.');
    } finally {
        loading.value = false;
    }
};

const submit = () => {
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(`/other-examination-personnel/${props.oepId}`, {
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
    <BaseModal :show="show" title="Edit Other Examination Personnel" max-width="2xl" @close="emit('close')">
        <div v-if="loading" class="flex items-center justify-center py-16">
            <p class="text-sm text-slate-500">Loading...</p>
        </div>

        <template v-else-if="loaded">
            <div>
                <form novalidate @submit.prevent="submit">
                    <FormErrorSummary
                        :errors="form.errors"
                        :labels="oepFieldLabels"
                        class="mb-5"
                    />
                    <OtherExaminationPersonnelForm
                        :form="form"
                        :field-offices="fieldOffices"
                        :testing-centers="testingCenters"
                        :personnel-types="personnelTypes"
                        editing
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
            {{ loadError ?? 'Could not load personnel data.' }}
        </div>
    </BaseModal>
</template>
