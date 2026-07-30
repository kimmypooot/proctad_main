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
    /**
     * Seed the form from an existing login (Users page → "Register as test
     * administrator"). Registering against the account someone already holds is
     * what gives a CSC employee their second hat; see MemberController::create.
     */
    accountId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const loaded = ref(false);
const loadError = ref(null);
const fieldOffices = ref([]);
const testingCenters = ref([]);
const account = ref(null);

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
    photo: null,
});

// Status is absent by design: a new record starts at whatever MemberStatus
// defaults to and moves through the requirements checklist. Offering it here
// would let someone skip straight to accredited without the documents.
useFormErrors(form);

const fetchCreateData = async () => {
    loading.value = true;
    loaded.value = false;
    loadError.value = null;
    form.reset();
    form.clearErrors();

    try {
        const query = props.accountId ? `?user=${props.accountId}` : '';
        const json = await fetchJson(`/members/create${query}`);

        fieldOffices.value = json.fieldOffices;
        testingCenters.value = json.testingCenters;
        account.value = json.account;

        if (json.account) {
            form.first_name = json.account.first_name ?? '';
            form.middle_name = json.account.middle_name ?? '';
            form.last_name = json.account.last_name ?? '';
            form.suffix = json.account.suffix ?? '';
            // The email must land on the server exactly as the account holds it
            // or resolveAccount() creates a second login instead of linking.
            form.email = json.account.email ?? '';
            form.mobile_number = json.account.mobile_number ?? '';
            form.field_office_id = json.account.field_office_id ?? '';
            // Set for staff accounts only, and still editable — see
            // Member::CSC_AGENCY.
            form.agency = json.account.agency ?? '';
        }

        loaded.value = true;
    } catch (e) {
        loaded.value = false;
        loadError.value = messageFor(e, 'Could not load the registration form.');
    } finally {
        loading.value = false;
    }
};

/*
 * `immediate` matters: arriving on /members?register=<id> renders this modal
 * already open, so `show` never *changes* and a plain watcher would never fire.
 * Nothing would be fetched, `loaded` would stay false, and the template's
 * fallback branch would report "could not load the registration form" for a
 * request that was never made.
 *
 * It has to sit *below* fetchCreateData for the same reason: an immediate
 * watcher body runs during setup, so a `const` declared later is still in the
 * temporal dead zone and referencing it throws — taking the whole page down to
 * a blank screen rather than failing inside the modal.
 */
watch(() => props.show, (open) => {
    if (open) {
        fetchCreateData();
    } else {
        loaded.value = false;
    }
}, { immediate: true });

const submit = () => {
    form.post('/members', {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('close');
        },
    });
};
</script>

<template>
    <BaseModal
        :show="show"
        :title="accountId ? 'Register Test Administrator' : 'Add Member'"
        max-width="2xl"
        @close="emit('close')"
    >
        <!-- "Not loaded and no error yet" is still loading, not a failure. Saying
             otherwise is what made a watcher that never fired look like a dead
             endpoint. -->
        <div v-if="loading || (!loaded && !loadError)" class="flex items-center justify-center py-16">
            <p class="text-sm text-slate-500">Loading...</p>
        </div>

        <template v-else-if="loaded">
            <form novalidate @submit.prevent="submit">
                <p
                    v-if="account"
                    class="mb-5 rounded-lg bg-brand-50 p-3 text-xs leading-relaxed text-brand-800"
                >
                    This registers <strong>{{ account.email }}</strong> — an existing
                    <strong>{{ account.role_label }}</strong> account — as a test administrator.
                    They keep that account and role, and gain the PROCTAD workspace switcher.
                    The email is locked so the record attaches to the login they already use.
                </p>

                <FormErrorSummary
                    :errors="form.errors"
                    :labels="memberFieldLabels"
                    class="mb-5"
                />

                <MemberForm
                    :form="form"
                    :field-offices="fieldOffices"
                    :testing-centers="testingCenters"
                    :email-locked="account !== null"
                    :is-employee="account?.is_employee ?? false"
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
                        {{ accountId ? 'Register' : 'Add Member' }}
                    </BaseButton>
                </div>
            </form>
        </template>

        <div v-else class="py-16 text-center text-sm text-slate-400">
            {{ loadError }}
        </div>
    </BaseModal>
</template>
