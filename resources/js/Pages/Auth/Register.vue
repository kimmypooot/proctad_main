<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import BaseButton from '@/Components/BaseButton.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { useFormErrors } from '@/Composables/useFormErrors';

/** Keys whose humanised form would not match the label on the control. */
const errorLabels = {
    testing_center_id: 'Testing Center',
    date_of_birth: 'Date of Birth',
    terms: 'Terms and Conditions',
};

const props = defineProps({
    google: { type: Object, default: null },
    testingCenters: { type: Array, required: true },
});

const sexOptions = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

/** Registrants must be at least 18 — matches the server-side `before:-18 years` rule. */
const maxDateOfBirth = (() => {
    const eighteenYearsAgo = new Date();
    eighteenYearsAgo.setFullYear(eighteenYearsAgo.getFullYear() - 18);

    return eighteenYearsAgo.toISOString().slice(0, 10);
})();

const form = useForm({
    first_name: props.google?.first_name ?? '',
    middle_name: '',
    last_name: props.google?.last_name ?? '',
    suffix: '',
    sex: '',
    date_of_birth: '',
    mobile_number: '',
    agency: '',
    position: '',
    testing_center_id: '',
    terms: false,
});

useFormErrors(form);

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            first_name: data.first_name.toUpperCase(),
            middle_name: data.middle_name.toUpperCase(),
            last_name: data.last_name.toUpperCase(),
            suffix: data.suffix.toUpperCase(),
        }))
        .post('/register');
};
</script>

<template>
    <AuthLayout
        title="Create your account"
        :subtitle="google
            ? 'Just a few more details to finish setting up your account.'
            : 'Connect your Google account to begin registering as a Certified Test Administrator.'"
        wide
    >
        <Head title="Register">
            <meta head-key="description" name="description" content="Register for the ProCTAD portal of CSC Regional Office VIII.">
        </Head>

        <div v-if="google" class="mb-5 flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-xs font-semibold text-white">
                <UserAvatar
                    :src="google.avatar"
                    :name="`${google.first_name ?? ''} ${google.last_name ?? ''}`.trim()"
                    :initials="`${google.first_name?.[0] ?? ''}${google.last_name?.[0] ?? ''}`"
                />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Continuing with Google</p>
                <p class="truncate text-xs text-slate-600">{{ google.email }}</p>
            </div>
            <a href="/auth/google/cancel" class="ml-auto shrink-0 text-xs font-medium text-brand-700 hover:underline">
                Not you?
            </a>
        </div>

        <!-- Shown at the only point the advice can still change the outcome: while
             the account is chosen. A DepEd or agency Google account and its mailbox
             are disabled together when someone resigns or retires, which leaves no
             way back into the record. -->
        <div v-if="google" class="mb-5 flex gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <AppIcon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>
                <span class="font-semibold">Use an account you'll keep.</span>
                A DepEd or agency Google account stops working when you resign or retire, and
                you would lose access to your PROCTAD record with it. A personal Google account
                stays with you.
            </p>
        </div>

        <a
            v-if="!google"
            href="/auth/google/redirect"
            class="inline-flex min-h-[3rem] w-full items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition-colors hover:border-brand-400 hover:text-brand-700"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M23.52 12.273c0-.851-.076-1.67-.218-2.455H12v4.642h6.458a5.52 5.52 0 0 1-2.394 3.622v3.011h3.878c2.269-2.089 3.578-5.165 3.578-8.82Z" />
                <path fill="#34A853" d="M12 24c3.24 0 5.956-1.075 7.942-2.907l-3.878-3.011c-1.075.72-2.45 1.145-4.064 1.145-3.125 0-5.771-2.111-6.715-4.948H1.276v3.11A11.995 11.995 0 0 0 12 24Z" />
                <path fill="#FBBC05" d="M5.285 14.28A7.213 7.213 0 0 1 4.909 12c0-.79.136-1.56.376-2.28V6.61H1.276A11.995 11.995 0 0 0 0 12c0 1.936.464 3.769 1.276 5.39l4.009-3.11Z" />
                <path fill="#EA4335" d="M12 4.773c1.762 0 3.344.605 4.587 1.794l3.442-3.442C17.951 1.19 15.235 0 12 0A11.995 11.995 0 0 0 1.276 6.61l4.009 3.11C6.229 6.884 8.875 4.773 12 4.773Z" />
            </svg>
            Continue with Google
        </a>

        <form v-if="google" class="space-y-5" novalidate @submit.prevent="submit">
            <FormErrorSummary :errors="form.errors" :labels="errorLabels" />
            <div class="grid gap-5 sm:grid-cols-2">
                <TextInput
                    v-model="form.first_name" name="first_name"
                    label="First Name"
                    required
                    autocomplete="given-name"
                    placeholder="Juan"
                    input-class="uppercase"
                    :error="form.errors.first_name"
                />
                <TextInput
                    v-model="form.middle_name" name="middle_name"
                    label="Middle Name"
                    optional
                    autocomplete="additional-name"
                    placeholder="Santos"
                    input-class="uppercase"
                    :error="form.errors.middle_name"
                />
                <TextInput
                    v-model="form.last_name" name="last_name"
                    label="Last Name"
                    required
                    autocomplete="family-name"
                    placeholder="Dela Cruz"
                    input-class="uppercase"
                    :error="form.errors.last_name"
                />
                <TextInput
                    v-model="form.suffix" name="suffix"
                    label="Suffix"
                    optional
                    placeholder="Jr., III"
                    input-class="uppercase"
                    :error="form.errors.suffix"
                />
                <SelectInput
                    v-model="form.sex" name="sex"
                    label="Sex"
                    required
                    :options="sexOptions"
                    :error="form.errors.sex"
                />
                <TextInput
                    v-model="form.date_of_birth" name="date_of_birth"
                    label="Date of Birth"
                    type="date"
                    required
                    autocomplete="bday"
                    :max="maxDateOfBirth"
                    tooltip="You must be at least 18 years old to register."
                    :error="form.errors.date_of_birth"
                />
            </div>

            <TextInput
                v-model="form.mobile_number" name="mobile_number"
                label="Mobile Number"
                type="tel"
                inputmode="tel"
                required
                autocomplete="tel"
                placeholder="09171234567"
                hint="Philippine mobile number, e.g. 09171234567"
                :error="form.errors.mobile_number"
            />

            <SelectInput
                v-model="form.testing_center_id" name="testing_center_id"
                label="Testing Center"
                required
                placeholder="Select your Testing Center"
                :options="testingCenters.map((tc) => ({ value: tc.id, label: tc.name }))"
                :error="form.errors.testing_center_id"
                hint="The city where you serve as a test administrator."
            />

            <div class="grid gap-5 sm:grid-cols-2">
                <TextInput
                    v-model="form.agency" name="agency"
                    label="Agency"
                    required
                    placeholder="e.g. DepEd Division Office"
                    :error="form.errors.agency"
                />
                <TextInput
                    v-model="form.position" name="position"
                    label="Position"
                    optional
                    :error="form.errors.position"
                />
            </div>

            <CheckboxInput v-model="form.terms" name="terms" :error="form.errors.terms">
                I have read and agree to the
                <a href="/terms-and-conditions" target="_blank" rel="noopener noreferrer" class="font-medium text-brand-700 hover:underline">Terms and Conditions</a>
                and
                <a href="/privacy-policy" target="_blank" rel="noopener noreferrer" class="font-medium text-brand-700 hover:underline">Privacy Policy</a>.
            </CheckboxInput>

            <BaseButton
                type="submit"
                variant="primary"
                size="lg"
                block
                :loading="form.processing"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Creating account…' : 'Complete Registration' }}
            </BaseButton>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            Already have an account?
            <Link href="/login" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 hover:underline">
                Login here
            </Link>
        </p>
    </AuthLayout>
</template>
