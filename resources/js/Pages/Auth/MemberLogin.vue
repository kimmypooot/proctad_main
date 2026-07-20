<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
</script>

<template>
    <AuthLayout
        title="PROCTAD Member Login"
        subtitle="Sign in with the Google account registered to your PROCTAD membership."
    >
        <Head title="Member Login">
            <meta head-key="description" name="description" content="PROCTAD member sign-in for CSC Regional Office VIII.">
        </Head>

        <a
            href="/auth/google/redirect"
            class="inline-flex min-h-[3.25rem] w-full items-center justify-center gap-3 rounded-lg bg-brand-700 px-6 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-800"
        >
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white">
                <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.52 12.273c0-.851-.076-1.67-.218-2.455H12v4.642h6.458a5.52 5.52 0 0 1-2.394 3.622v3.011h3.878c2.269-2.089 3.578-5.165 3.578-8.82Z" />
                    <path fill="#34A853" d="M12 24c3.24 0 5.956-1.075 7.942-2.907l-3.878-3.011c-1.075.72-2.45 1.145-4.064 1.145-3.125 0-5.771-2.111-6.715-4.948H1.276v3.11A11.995 11.995 0 0 0 12 24Z" />
                    <path fill="#FBBC05" d="M5.285 14.28A7.213 7.213 0 0 1 4.909 12c0-.79.136-1.56.376-2.28V6.61H1.276A11.995 11.995 0 0 0 0 12c0 1.936.464 3.769 1.276 5.39l4.009-3.11Z" />
                    <path fill="#EA4335" d="M12 4.773c1.762 0 3.344.605 4.587 1.794l3.442-3.442C17.951 1.19 15.235 0 12 0A11.995 11.995 0 0 0 1.276 6.61l4.009 3.11C6.229 6.884 8.875 4.773 12 4.773Z" />
                </svg>
            </span>
            Continue with Google
        </a>

        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <p class="font-semibold text-slate-700">First time here?</p>
            <p class="mt-1">
                Your Google email must match the email registered to your PROCTAD membership.
                If sign-in fails, contact your Testing Center to verify your registered email.
            </p>
        </div>

        <!-- Members register through Google and never set a password, so "forgot
             password" reads as irrelevant to them. Label the outcome instead, and
             name the case the reset link cannot solve — an unreachable registered
             email would otherwise send them to a form that reports success and
             delivers nothing. -->
        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <p class="font-semibold text-slate-700">Can't access your Google account?</p>
            <p class="mt-1">
                You can
                <Link
                    href="/forgot-password?from=member"
                    class="font-semibold text-brand-700 transition-colors hover:text-brand-800 hover:underline"
                >
                    sign in with a password instead
                </Link>
                — we'll email a link to the address registered to your membership.
            </p>
            <p class="mt-2">
                If you can no longer read that email either, contact CSC RO VIII: your
                registered address has to be updated before you can sign in again.
            </p>
        </div>

        <p class="mt-8 text-center text-sm text-slate-500">
            Don't have an account?
            <Link href="/register" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 hover:underline">
                Register here
            </Link>
        </p>

        <p class="mt-3 text-center text-sm text-slate-500">
            CSC staff member?
            <Link href="/login" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 hover:underline">
                Sign in with your username
            </Link>
        </p>
    </AuthLayout>
</template>
