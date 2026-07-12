<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import TextInput from '@/Components/TextInput.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    forced: { type: Boolean, default: false },
});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put('/change-password', {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <AuthLayout
        title="Change your password"
        :subtitle="forced
            ? 'For security, you must set a new password before continuing.'
            : 'Choose a new password for your account.'"
    >
        <Head title="Change Password" />

        <BaseAlert v-if="forced" class="mb-5" variant="warning">
            Your account is using a temporary or default password. Please set a new one to continue.
        </BaseAlert>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <TextInput
                v-model="form.current_password"
                label="Current Password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Enter your current password"
                :error="form.errors.current_password"
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
                {{ form.processing ? 'Saving…' : 'Update Password' }}
            </BaseButton>
        </form>
    </AuthLayout>
</template>
