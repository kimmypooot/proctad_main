<script setup>
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FileInput from '@/Components/FileInput.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

defineProps({
    /** Inertia useForm object owned by the parent page */
    form: { type: Object, required: true },
    fieldOffices: { type: Array, required: true },
    personnelTypes: { type: Array, required: true },
    /** Pass true to show the active/inactive control (edit mode) */
    editing: { type: Boolean, default: false },
});

const sexOptions = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput v-model="form.first_name" label="First Name" required :error="form.errors.first_name" />
            <TextInput v-model="form.middle_name" label="Middle Name" optional :error="form.errors.middle_name" />
            <TextInput v-model="form.last_name" label="Last Name" required :error="form.errors.last_name" />
            <div class="grid grid-cols-2 gap-5">
                <TextInput v-model="form.suffix" label="Suffix" optional placeholder="Jr., III" :error="form.errors.suffix" />
                <SelectInput v-model="form.sex" label="Sex" required :options="sexOptions" :error="form.errors.sex" />
            </div>
        </div>

        <SelectInput
            v-model="form.personnel_type"
            label="Personnel Type"
            required
            :options="personnelTypes"
            :error="form.errors.personnel_type"
        />

        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput v-model="form.contact_number" label="Contact Number" optional inputmode="tel" placeholder="09171234567" :error="form.errors.contact_number" />
            <TextInput v-model="form.email" label="Email Address" type="email" optional :error="form.errors.email" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <TextInput v-model="form.agency" label="Agency" optional :error="form.errors.agency" />
            <TextInput v-model="form.position" label="Position" optional :error="form.errors.position" />
        </div>

        <SelectInput
            v-model="form.field_office_id"
            label="Field Office"
            optional
            placeholder="Region-wide / unassigned"
            :options="fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))"
            :error="form.errors.field_office_id"
        />

        <FileInput
            v-model="form.photo"
            label="ID Photo"
            optional
            compress
            accept="image/*"
            :error="form.errors.photo"
            hint="JPG or PNG, max 2 MB."
        />

        <CheckboxInput v-if="editing" v-model="form.is_active">Active</CheckboxInput>
    </div>
</template>
