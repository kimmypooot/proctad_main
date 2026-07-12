<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout
        title="Reset your password"
        subtitle="Choose a new password for your account."
    >
        <Head title="Reset Password" />

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

            <TextInput
                v-model="form.password"
                label="New Password"
                type="password"
                required
                autocomplete="new-password"
                placeholder="At least 8 characters with letters and numbers"
                :error="form.errors.password"
            />

            <TextInput
                v-model="form.password_confirmation"
                label="Confirm New Password"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Re-enter your new password"
                :error="form.errors.password_confirmation"
            />

            <BaseButton
                type="submit"
                variant="primary"
                size="lg"
                block
                :loading="form.processing"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Resetting…' : 'Reset Password' }}
            </BaseButton>
        </form>
    </AuthLayout>
</template>
