<script setup>
import { computed, watch } from 'vue';
import FileInput from '@/Components/FileInput.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    /** Inertia useForm object owned by the parent page */
    form: { type: Object, required: true },
    fieldOffices: { type: Array, required: true },
    /** [{ id, name, field_office_ids }] — the offices are used to narrow the
     *  list to centers the chosen field office actually handles. */
    testingCenters: { type: Array, default: () => [] },
    /** Pass member statuses to show the status controls (edit mode) */
    statuses: { type: Array, default: null },
    /** True when prefilled from an existing unlinked account (Users worklist) — the
     * email must stay exactly what that account uses so submitting links it, not
     * creates a duplicate (see MemberController::resolveAccount()). */
    emailLocked: { type: Boolean, default: false },
});

const sexOptions = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

/*
 * A member's testing center must be one their field office handles, so the list
 * narrows as soon as an office is chosen — the same rule StoreMemberRequest
 * enforces, surfaced early rather than as a rejection after submitting.
 */
const centersForSelectedOffice = computed(() => {
    const officeId = Number(props.form.field_office_id);

    if (!officeId) return props.testingCenters;

    return props.testingCenters.filter(
        (tc) => (tc.field_office_ids ?? []).map(Number).includes(officeId),
    );
});

/* The regional office serves region-wide and sits in no single center. */
const regionalOfficeSelected = computed(() => {
    const officeId = Number(props.form.field_office_id);

    return Boolean(officeId) && centersForSelectedOffice.value.length === 0;
});

// Switching office can strip the chosen center out of the list; clear it rather
// than submitting a value the server will reject.
watch(centersForSelectedOffice, (centers) => {
    if (props.form.testing_center_id
        && !centers.some((tc) => tc.id === Number(props.form.testing_center_id))) {
        props.form.testing_center_id = '';
    }
});

/** Members must be at least 18 — matches StoreMemberRequest's `before:-18 years` rule. */
const maxDateOfBirth = (() => {
    const eighteenYearsAgo = new Date();
    eighteenYearsAgo.setFullYear(eighteenYearsAgo.getFullYear() - 18);

    return eighteenYearsAgo.toISOString().slice(0, 10);
})();
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput
                v-model="form.first_name"
                name="first_name"
                label="First Name"
                required
                :error="form.errors.first_name"
            />
            <TextInput
                v-model="form.middle_name"
                name="middle_name"
                label="Middle Name"
                optional
                :error="form.errors.middle_name"
            />
            <TextInput
                v-model="form.last_name"
                name="last_name"
                label="Last Name"
                required
                :error="form.errors.last_name"
            />
            <div class="grid grid-cols-2 gap-5">
                <TextInput
                    v-model="form.suffix"
                    name="suffix"
                    label="Suffix"
                    optional
                    placeholder="Jr., III"
                    :error="form.errors.suffix"
                />
                <SelectInput
                    v-model="form.sex"
                    name="sex"
                    label="Sex"
                    required
                    :options="sexOptions"
                    :error="form.errors.sex"
                />
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput
                v-model="form.date_of_birth"
                name="date_of_birth"
                label="Date of Birth"
                type="date"
                required
                :max="maxDateOfBirth"
                :error="form.errors.date_of_birth"
            />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput
                v-model="form.email"
                name="email"
                label="Email Address"
                type="email"
                required
                :disabled="emailLocked"
                :error="form.errors.email"
                :hint="emailLocked
                    ? 'Locked to the existing account\'s email so this links it instead of creating a duplicate.'
                    : 'Also used for the member\'s login (Google sign-in supported).'"
            />
            <TextInput
                v-model="form.mobile_number"
                name="mobile_number"
                label="Mobile Number"
                required
                inputmode="tel"
                placeholder="09171234567"
                :error="form.errors.mobile_number"
            />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput
                v-model="form.agency"
                name="agency"
                label="Agency"
                required
                placeholder="e.g. DepEd Division Office"
                :error="form.errors.agency"
            />
            <TextInput
                v-model="form.position"
                name="position"
                label="Position"
                optional
                :error="form.errors.position"
            />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <SelectInput
                v-model="form.field_office_id"
                name="field_office_id"
                label="Field Office"
                required
                :options="fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))"
                :error="form.errors.field_office_id"
                hint="Who administers this member's record."
            />

            <SelectInput
                v-model="form.testing_center_id"
                name="testing_center_id"
                label="Testing Center"
                :required="!regionalOfficeSelected"
                :placeholder="regionalOfficeSelected ? 'Serves region-wide — no center' : 'Select a Testing Center'"
                :options="centersForSelectedOffice.map((tc) => ({ value: tc.id, label: tc.name }))"
                :error="form.errors.testing_center_id"
                hint="The city the member serves — this decides which staff can see and manage them."
            />
        </div>

        <FileInput
            v-model="form.photo"
            name="photo"
            label="ID Photo"
            optional
            compress
            accept="image/*"
            :error="form.errors.photo"
            hint="JPG or PNG, max 2 MB. If the member signs in with Google, their Google photo is preferred on the ID."
        />

        <template v-if="statuses">
            <SelectInput
                v-model="form.status"
                name="status"
                label="Accreditation Status"
                required
                :options="statuses"
                :error="form.errors.status"
            />
            <TextInput
                v-if="form.status === 'disqualified'"
                v-model="form.disqualification_remarks"
                name="disqualification_remarks"
                label="Disqualification Remarks"
                required
                :error="form.errors.disqualification_remarks"
            />
        </template>
    </div>
</template>
