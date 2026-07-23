<script setup>
import FileInput from '@/Components/FileInput.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    /** Inertia useForm object owned by the parent page */
    form: { type: Object, required: true },
    fieldOffices: { type: Array, required: true },
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
                label="First Name"
                required
                :error="form.errors.first_name"
            />
            <TextInput
                v-model="form.middle_name"
                label="Middle Name"
                optional
                :error="form.errors.middle_name"
            />
            <TextInput
                v-model="form.last_name"
                label="Last Name"
                required
                :error="form.errors.last_name"
            />
            <div class="grid grid-cols-2 gap-5">
                <TextInput
                    v-model="form.suffix"
                    label="Suffix"
                    optional
                    placeholder="Jr., III"
                    :error="form.errors.suffix"
                />
                <SelectInput
                    v-model="form.sex"
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
                label="Agency"
                required
                placeholder="e.g. DepEd Division Office"
                :error="form.errors.agency"
            />
            <TextInput
                v-model="form.position"
                label="Position"
                optional
                :error="form.errors.position"
            />
        </div>

        <SelectInput
            v-model="form.field_office_id"
            label="Field Office"
            required
            :options="fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))"
            :error="form.errors.field_office_id"
        />

        <FileInput
            v-model="form.photo"
            label="ID Photo"
            optional
            accept="image/*"
            :error="form.errors.photo"
            hint="JPG or PNG, max 2 MB. If the member signs in with Google, their Google photo is preferred on the ID."
        />

        <template v-if="statuses">
            <SelectInput
                v-model="form.status"
                label="Accreditation Status"
                required
                :options="statuses"
                :error="form.errors.status"
            />
            <TextInput
                v-if="form.status === 'disqualified'"
                v-model="form.disqualification_remarks"
                label="Disqualification Remarks"
                required
                :error="form.errors.disqualification_remarks"
            />
        </template>
    </div>
</template>
