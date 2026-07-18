<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import OtherExaminationPersonnelForm from './OtherExaminationPersonnelForm.vue';

const props = defineProps({
    show: { type: Boolean, required: true },
    oepId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const loaded = ref(false);
const fieldOffices = ref([]);
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
    is_active: true,
    photo: null,
});

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
    form.clearErrors();
    try {
        const response = await fetch(`/other-examination-personnel/${props.oepId}/edit-data`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error();
        const json = await response.json();

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
        form.is_active = json.oep.is_active;
        form.photo = null;

        fieldOffices.value = json.fieldOffices;
        personnelTypes.value = json.personnelTypes;
        loaded.value = true;
    } catch {
        loaded.value = false;
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
            <div class="max-h-[70vh] overflow-y-auto -mx-6 -mt-5 px-6 pt-5">
                <form novalidate @submit.prevent="submit">
                    <OtherExaminationPersonnelForm :form="form" :field-offices="fieldOffices" :personnel-types="personnelTypes" editing />

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
            Could not load personnel data.
        </div>
    </BaseModal>
</template>
