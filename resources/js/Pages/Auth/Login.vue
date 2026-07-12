<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import BaseButton from '@/Components/BaseButton.vue';

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthLayout
        title="Welcome back"
        subtitle="Sign in to your ProCTAD account to continue."
    >
        <Head title="Login">
            <meta head-key="description" name="description" content="Sign in to the ProCTAD portal of CSC Regional Office VIII.">
        </Head>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <TextInput
                v-model="form.login"
                label="Username or Email"
                type="text"
                required
                autocomplete="username"
                placeholder="Your username or email address"
                :error="form.errors.login"
            />

            <div>
                <TextInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    :error="form.errors.password"
                />
            </div>

            <div class="flex items-center justify-between gap-4">
                <CheckboxInput v-model="form.remember">Remember me</CheckboxInput>
                <Link
                    href="/forgot-password"
                    class="text-sm font-medium text-brand-700 transition-colors hover:text-brand-800 hover:underline"
                >
                    Forgot password?
                </Link>
            </div>

            <BaseButton
                type="submit"
                variant="primary"
                size="lg"
                block
                :loading="form.processing"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Signing in…' : 'Login' }}
            </BaseButton>
        </form>

        <div class="mt-6 flex items-center gap-4" aria-hidden="true">
            <span class="h-px flex-1 bg-slate-200" />
            <span class="text-xs font-medium uppercase tracking-wide text-slate-400">or</span>
            <span class="h-px flex-1 bg-slate-200" />
        </div>

        <a
            href="/auth/google/redirect"
            class="mt-6 inline-flex min-h-[3rem] w-full items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition-colors hover:border-brand-400 hover:text-brand-700"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M23.52 12.273c0-.851-.076-1.67-.218-2.455H12v4.642h6.458a5.52 5.52 0 0 1-2.394 3.622v3.011h3.878c2.269-2.089 3.578-5.165 3.578-8.82Z" />
                <path fill="#34A853" d="M12 24c3.24 0 5.956-1.075 7.942-2.907l-3.878-3.011c-1.075.72-2.45 1.145-4.064 1.145-3.125 0-5.771-2.111-6.715-4.948H1.276v3.11A11.995 11.995 0 0 0 12 24Z" />
                <path fill="#FBBC05" d="M5.285 14.28A7.213 7.213 0 0 1 4.909 12c0-.79.136-1.56.376-2.28V6.61H1.276A11.995 11.995 0 0 0 0 12c0 1.936.464 3.769 1.276 5.39l4.009-3.11Z" />
                <path fill="#EA4335" d="M12 4.773c1.762 0 3.344.605 4.587 1.794l3.442-3.442C17.951 1.19 15.235 0 12 0A11.995 11.995 0 0 0 1.276 6.61l4.009 3.11C6.229 6.884 8.875 4.773 12 4.773Z" />
            </svg>
            Continue with Google
        </a>

        <p class="mt-8 text-center text-sm text-slate-500">
            Don't have an account?
            <Link href="/register" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 hover:underline">
                Register here
            </Link>
        </p>

        <p class="mt-3 text-center text-sm text-slate-500">
            PROCTAD member?
            <Link href="/member/login" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 hover:underline">
                Use the member login
            </Link>
        </p>
    </AuthLayout>
</template>
