<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    /** Set by PasswordResetLinkController from ?from=member — members return to
     *  their own sign-in screen, not the staff one. */
    fromMember: { type: Boolean, default: false },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};

const backTo = props.fromMember ? '/member/login' : '/login';
</script>

<template>
    <AuthLayout
        title="Forgot your password?"
        subtitle="Enter the email address for your account and we'll send you a link to reset your password."
    >
        <Head title="Forgot Password">
            <meta head-key="description" name="description" content="Request a password reset link for the ProCTAD portal.">
        </Head>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <TextInput
                v-model="form.email"
                label="Email Address"
                type="email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :error="form.errors.email"
            />

            <BaseButton
                type="submit"
                variant="primary"
                size="lg"
                block
                :loading="form.processing"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Sending…' : 'Email Password Reset Link' }}
            </BaseButton>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            {{ fromMember ? 'Prefer to use Google?' : 'Remembered your password?' }}
            <Link :href="backTo" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 hover:underline">
                Back to sign in
            </Link>
        </p>
    </AuthLayout>
</template>
