<script setup>
import { computed, watch } from 'vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FileInput from '@/Components/FileInput.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    /** Inertia useForm object owned by the parent page */
    form: { type: Object, required: true },
    fieldOffices: { type: Array, required: true },
    testingCenters: { type: Array, default: () => [] },
    personnelTypes: { type: Array, required: true },
    /** Pass true to show the active/inactive control (edit mode) */
    editing: { type: Boolean, default: false },
});

const sexOptions = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

/** Regional-office personnel serve region-wide, so no single center applies. */
const selectedOfficeIsRegional = computed(() => Boolean(
    props.fieldOffices.find((fo) => fo.id === Number(props.form.field_office_id))?.is_regional,
));

/*
 * Only centers the chosen office actually handles — the same rule
 * StoreOtherExaminationPersonnelRequest enforces, surfaced as a shorter list
 * rather than a rejection after submitting.
 */
const centersForSelectedOffice = computed(() => {
    const officeId = Number(props.form.field_office_id);

    if (!officeId) return props.testingCenters;

    return props.testingCenters.filter(
        (tc) => (tc.field_office_ids ?? []).map(Number).includes(officeId),
    );
});

// Changing the office can strip the chosen center out of the list; clear it
// rather than submitting a value the server will reject. The regional office
// clears it outright, since region-wide personnel sit in no center.
watch([centersForSelectedOffice, selectedOfficeIsRegional], ([centers, regional]) => {
    if (regional) {
        props.form.testing_center_id = '';

        return;
    }

    if (props.form.testing_center_id
        && !centers.some((tc) => tc.id === Number(props.form.testing_center_id))) {
        props.form.testing_center_id = '';
    }
});
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <!--
                Uppercased as typed, matching how the record is stored (see
                OtherExaminationPersonnel's mutators) — otherwise the form shows
                "Juan" right up until it saves as "JUAN", and the ID card,
                roster and this page disagree about what was entered.
            -->
            <TextInput v-model="form.first_name" name="first_name" label="First Name" required uppercase :error="form.errors.first_name" />
            <TextInput v-model="form.middle_name" name="middle_name" label="Middle Name" optional uppercase :error="form.errors.middle_name" />
            <TextInput v-model="form.last_name" name="last_name" label="Last Name" required uppercase :error="form.errors.last_name" />
            <div class="grid grid-cols-2 gap-5">
                <TextInput v-model="form.suffix" name="suffix" label="Suffix" optional uppercase placeholder="JR., III" :error="form.errors.suffix" />
                <SelectInput v-model="form.sex" name="sex" label="Sex" required :options="sexOptions" :error="form.errors.sex" />
            </div>
        </div>

        <SelectInput
            v-model="form.personnel_type" name="personnel_type"
            label="Personnel Type"
            required
            :options="personnelTypes"
            :error="form.errors.personnel_type"
        />

        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput v-model="form.contact_number" name="contact_number" label="Contact Number" optional inputmode="tel" placeholder="09171234567" :error="form.errors.contact_number" />
            <TextInput v-model="form.email" name="email" label="Email Address" type="email" optional :error="form.errors.email" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput v-model="form.agency" name="agency" label="Agency" optional uppercase :error="form.errors.agency" />
            <TextInput v-model="form.position" name="position" label="Position" optional uppercase :error="form.errors.position" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <SelectInput
                v-model="form.field_office_id" name="field_office_id"
                label="Field Office"
                required
                :options="fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))"
                :error="form.errors.field_office_id"
                hint="The office this person was engaged by. The regional office means region-wide — available to every field office."
            />

            <SelectInput
                v-if="!selectedOfficeIsRegional"
                v-model="form.testing_center_id" name="testing_center_id"
                label="Testing Center"
                required
                placeholder="Select a Testing Center"
                :options="centersForSelectedOffice.map((tc) => ({ value: tc.id, label: tc.name }))"
                :error="form.errors.testing_center_id"
                hint="The city this person works in — this decides which staff can see and assign them."
            />
            <p v-else class="self-center text-sm text-slate-500">
                Regional office personnel serve region-wide, so no Testing Center applies.
            </p>
        </div>

        <FileInput
            v-model="form.photo" name="photo"
            label="ID Photo"
            optional
            compress
            accept="image/*"
            :error="form.errors.photo"
            hint="JPG or PNG, max 2 MB."
        />

        <CheckboxInput v-if="editing" v-model="form.is_active" name="is_active">Active</CheckboxInput>
    </div>
</template>
