<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    google: { type: Object, default: null },
    fieldOffices: { type: Array, required: true },
});

const sexOptions = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

const form = useForm({
    first_name: props.google?.first_name ?? '',
    middle_name: '',
    last_name: props.google?.last_name ?? '',
    suffix: '',
    sex: '',
    email: props.google?.email ?? '',
    mobile_number: '',
    agency: '',
    position: '',
    field_office_id: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

/**
 * Password strength: 0–4 based on length, case mix, digits, symbols.
 */
const strength = computed(() => {
    const value = form.password;
    if (!value) return { score: 0, label: '', color: '' };

    let score = 0;
    if (value.length >= 8) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const levels = [
        { label: 'Very weak', color: 'bg-accent-500' },
        { label: 'Weak', color: 'bg-accent-400' },
        { label: 'Fair', color: 'bg-amber-400' },
        { label: 'Good', color: 'bg-emerald-400' },
        { label: 'Strong', color: 'bg-emerald-500' },
    ];

    return { score, ...levels[score] };
});

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            first_name: data.first_name.toUpperCase(),
            middle_name: data.middle_name.toUpperCase(),
            last_name: data.last_name.toUpperCase(),
            suffix: data.suffix.toUpperCase(),
        }))
        .post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
};
</script>

<template>
    <AuthLayout
        title="Create your account"
        :subtitle="google
            ? 'Just a few more details to finish setting up your account.'
            : 'Begin your journey toward becoming a Certified Test Administrator.'"
    >
        <Head title="Register">
            <meta head-key="description" name="description" content="Register for the ProCTAD portal of CSC Regional Office VIII.">
        </Head>

        <div v-if="google" class="mb-5 flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4">
            <img
                v-if="google.avatar"
                :src="google.avatar"
                alt=""
                class="h-10 w-10 shrink-0 rounded-full"
            >
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Continuing with Google</p>
                <p class="truncate text-xs text-slate-600">{{ google.email }}</p>
            </div>
            <Link href="/login" class="ml-auto shrink-0 text-xs font-medium text-brand-700 hover:underline">
                Not you?
            </Link>
        </div>

        <template v-if="!google">
            <div class="relative">
                <a
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
                <BaseBadge variant="success" size="xs" class="absolute -top-2.5 right-3">Recommended</BaseBadge>
            </div>

            <div class="my-6 flex items-center gap-4" aria-hidden="true">
                <span class="h-px flex-1 bg-slate-200" />
                <span class="text-xs font-medium uppercase tracking-wide text-slate-400">or</span>
                <span class="h-px flex-1 bg-slate-200" />
            </div>
        </template>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-2">
                <TextInput
                    v-model="form.first_name"
                    label="First Name"
                    required
                    autocomplete="given-name"
                    placeholder="Juan"
                    input-class="uppercase"
                    :error="form.errors.first_name"
                />
                <TextInput
                    v-model="form.middle_name"
                    label="Middle Name"
                    optional
                    autocomplete="additional-name"
                    placeholder="Santos"
                    input-class="uppercase"
                    :error="form.errors.middle_name"
                />
                <TextInput
                    v-model="form.last_name"
                    label="Last Name"
                    required
                    autocomplete="family-name"
                    placeholder="Dela Cruz"
                    input-class="uppercase"
                    :error="form.errors.last_name"
                />
                <TextInput
                    v-model="form.suffix"
                    label="Suffix"
                    optional
                    placeholder="Jr., III"
                    input-class="uppercase"
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

            <TextInput
                v-if="!google"
                v-model="form.email"
                label="Email Address"
                type="email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :error="form.errors.email"
            />

            <TextInput
                v-model="form.mobile_number"
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
                v-model="form.field_office_id"
                label="Testing Center"
                required
                placeholder="Select your Testing Center"
                :options="fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))"
                :error="form.errors.field_office_id"
                hint="The CSC Testing Center you serve as a test administrator under."
            />

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

            <div v-if="!google">
                <TextInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="At least 8 characters"
                    :error="form.errors.password"
                />

                <!-- Strength indicator -->
                <div v-if="form.password" class="mt-2.5" aria-live="polite">
                    <div class="flex gap-1.5" aria-hidden="true">
                        <span
                            v-for="i in 4"
                            :key="i"
                            class="h-1.5 flex-1 rounded-full transition-colors duration-300"
                            :class="i <= strength.score ? strength.color : 'bg-slate-200'"
                        />
                    </div>
                    <p class="mt-1.5 text-xs font-medium text-slate-500">
                        Password strength: <span class="text-slate-700">{{ strength.label }}</span>
                    </p>
                </div>
            </div>

            <TextInput
                v-if="!google"
                v-model="form.password_confirmation"
                label="Confirm Password"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Re-enter your password"
                :error="form.errors.password_confirmation"
            />

            <CheckboxInput v-model="form.terms" :error="form.errors.terms">
                I have read and agree to the
                <Link href="/terms-and-conditions" class="font-medium text-brand-700 hover:underline">Terms and Conditions</Link>
                and
                <Link href="/privacy-policy" class="font-medium text-brand-700 hover:underline">Privacy Policy</Link>.
            </CheckboxInput>

            <BaseButton
                type="submit"
                variant="primary"
                size="lg"
                block
                :loading="form.processing"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Creating account…' : (google ? 'Complete Registration' : 'Register') }}
            </BaseButton>
        </form>

        <p v-if="!google" class="mt-8 text-center text-sm text-slate-500">
            Already have an account?
            <Link href="/login" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 hover:underline">
                Login here
            </Link>
        </p>
    </AuthLayout>
</template>
