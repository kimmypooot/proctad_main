<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import MemberForm from './MemberForm.vue';

const props = defineProps({
    show: { type: Boolean, required: true },
    memberId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const loaded = ref(false);
const fieldOffices = ref([]);
const statuses = ref([]);

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
    field_office_id: '',
    status: '',
    disqualification_remarks: '',
    photo: null,
});

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
    form.clearErrors();
    try {
        const response = await fetch(`/members/${props.memberId}/edit-data`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error();
        const json = await response.json();

        form.first_name = json.member.first_name ?? '';
        form.middle_name = json.member.middle_name ?? '';
        form.last_name = json.member.last_name ?? '';
        form.suffix = json.member.suffix ?? '';
        form.sex = json.member.sex ?? '';
        form.email = json.member.email ?? '';
        form.mobile_number = json.member.mobile_number ?? '';
        form.agency = json.member.agency ?? '';
        form.position = json.member.position ?? '';
        form.field_office_id = json.member.field_office_id ?? '';
        form.status = json.member.status ?? '';
        form.disqualification_remarks = json.member.disqualification_remarks ?? '';
        form.photo = null;

        fieldOffices.value = json.fieldOffices;
        statuses.value = json.statuses;
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
            <div class="max-h-[70vh] overflow-y-auto -mx-6 -mt-5 px-6 pt-5">
                <form novalidate @submit.prevent="submit">
                    <MemberForm :form="form" :field-offices="fieldOffices" :statuses="statuses" />

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
            Could not load member data.
        </div>
    </BaseModal>
</template>
