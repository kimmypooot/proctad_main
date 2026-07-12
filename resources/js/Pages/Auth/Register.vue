<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import BaseButton from '@/Components/BaseButton.vue';

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    email: '',
    mobile_number: '',
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
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout
        title="Create your account"
        subtitle="Begin your journey toward becoming a Certified Test Administrator."
    >
        <Head title="Register">
            <meta head-key="description" name="description" content="Register for the ProCTAD portal of CSC Regional Office VIII.">
        </Head>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-2">
                <TextInput
                    v-model="form.first_name"
                    label="First Name"
                    required
                    autocomplete="given-name"
                    placeholder="Juan"
                    :error="form.errors.first_name"
                />
                <TextInput
                    v-model="form.middle_name"
                    label="Middle Name"
                    optional
                    autocomplete="additional-name"
                    placeholder="Santos"
                    :error="form.errors.middle_name"
                />
                <TextInput
                    v-model="form.last_name"
                    label="Last Name"
                    required
                    autocomplete="family-name"
                    placeholder="Dela Cruz"
                    :error="form.errors.last_name"
                />
                <TextInput
                    v-model="form.suffix"
                    label="Suffix"
                    optional
                    placeholder="Jr., III"
                    :error="form.errors.suffix"
                />
            </div>

            <TextInput
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

            <div>
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
                {{ form.processing ? 'Creating account…' : 'Register' }}
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
