<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from './AppLogo.vue';
import AppIcon from './AppIcon.vue';
import BaseButton from './BaseButton.vue';

const page = usePage();
const mobileOpen = ref(false);
const scrolled = ref(false);

const links = [
    { label: 'Home', href: '/' },
    { label: 'About', href: '/about' },
    { label: 'Benefits', href: '/benefits' },
    { label: 'Qualifications', href: '/qualifications' },
    { label: 'FAQs', href: '/faqs' },
    { label: 'Contact', href: '/contact' },
];

const isActive = (href) => {
    const current = page.url.split('?')[0];
    return href === '/' ? current === '/' : current.startsWith(href);
};

const onScroll = () => {
    scrolled.value = window.scrollY > 8;
};

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    document.body.style.overflow = '';
});

// Close the drawer on navigation and lock body scroll while open
watch(() => page.url, () => (mobileOpen.value = false));
watch(mobileOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
</script>

<template>
    <header
        class="sticky top-0 z-40 w-full border-b bg-white transition-shadow duration-200"
        :class="scrolled ? 'border-slate-200 shadow-sm' : 'border-transparent'"
    >
        <!-- Government banner strip -->
        <div class="bg-gradient-to-r from-brand-800 via-brand-800 to-brand-700 text-white">
            <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-1.5 text-xs sm:px-6 lg:px-8">
                <AppIcon name="check-badge" class="h-3.5 w-3.5 shrink-0 text-brand-200" />
                <span class="truncate">An official website of the Civil Service Commission Regional Office VIII</span>
            </div>
        </div>

        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8" aria-label="Main navigation">
            <Link href="/" class="shrink-0 rounded-md" aria-label="ProCTAD — Home">
                <AppLogo />
            </Link>

            <!-- Desktop links -->
            <ul class="hidden items-center gap-1 lg:flex">
                <li v-for="link in links" :key="link.href" class="relative">
                    <Link
                        :href="link.href"
                        class="rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200"
                        :class="isActive(link.href)
                            ? 'text-brand-700 font-semibold'
                            : 'text-slate-600 hover:text-brand-700 hover:bg-slate-50'"
                        :aria-current="isActive(link.href) ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                    <span
                        v-if="isActive(link.href)"
                        class="absolute -bottom-[1px] left-3 right-3 h-0.5 rounded-full bg-accent-500"
                        aria-hidden="true"
                    />
                </li>
            </ul>

            <!--
                Desktop auth actions.

                Login points at the member page, not /login. Visitors arriving
                from the public site are overwhelmingly PROCTAD members, who
                sign in with Google and never set a password — /login leads with
                a username and password form they cannot use. Staff are not cut
                off: /member/login links straight to it, and every framework
                redirect (expired session, `auth` middleware, the maintenance
                notice) still goes to /login, which stays the `login` route.
            -->
            <div class="hidden items-center gap-3 lg:flex">
                <BaseButton href="/member/login" variant="ghost" size="sm">Login</BaseButton>
                <BaseButton href="/register" variant="primary" size="sm">Register</BaseButton>
            </div>

            <!-- Animated hamburger -->
            <button
                type="button"
                class="relative flex h-11 w-11 items-center justify-center rounded-md text-slate-700 transition-colors hover:bg-slate-50 lg:hidden"
                :aria-expanded="mobileOpen"
                aria-controls="mobile-menu"
                :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
                @click="mobileOpen = !mobileOpen"
            >
                <span class="sr-only">{{ mobileOpen ? 'Close menu' : 'Open menu' }}</span>
                <span class="relative block h-4 w-6" aria-hidden="true">
                    <span
                        class="absolute left-0 top-0 h-0.5 w-6 rounded bg-current transition-transform duration-200"
                        :class="mobileOpen ? 'translate-y-[7px] rotate-45' : ''"
                    />
                    <span
                        class="absolute left-0 top-[7px] h-0.5 w-6 rounded bg-current transition-opacity duration-200"
                        :class="mobileOpen ? 'opacity-0' : ''"
                    />
                    <span
                        class="absolute left-0 top-[14px] h-0.5 w-6 rounded bg-current transition-transform duration-200"
                        :class="mobileOpen ? '-translate-y-[7px] -rotate-45' : ''"
                    />
                </span>
            </button>
        </nav>

        <!-- Mobile menu -->
        <Transition
            enter-active-class="transition-[opacity,transform] duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition-[opacity,transform] duration-150 ease-in"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="mobileOpen"
                id="mobile-menu"
                class="absolute inset-x-0 top-full max-h-[calc(100vh-6rem)] overflow-y-auto border-b border-slate-200 bg-white shadow-lg lg:hidden"
            >
                <ul class="space-y-1 px-4 py-4 sm:px-6">
                    <li v-for="link in links" :key="link.href">
                        <Link
                            :href="link.href"
                            class="block rounded-lg px-4 py-3 text-sm font-medium transition-colors duration-200 min-h-[44px]"
                            :class="isActive(link.href)
                                ? 'bg-brand-50 text-brand-700'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-brand-700'"
                            :aria-current="isActive(link.href) ? 'page' : undefined"
                        >
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
                <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:px-6">
                    <!-- Same destination as the desktop button above. -->
                    <BaseButton href="/member/login" variant="outline" block>Login</BaseButton>
                    <BaseButton href="/register" variant="primary" block>Register</BaseButton>
                </div>
            </div>
        </Transition>
    </header>
</template>
