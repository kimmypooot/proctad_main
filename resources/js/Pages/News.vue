<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import BaseButton from '@/Components/BaseButton.vue';
</script>

<template>
    <PublicLayout>
        <Head title="News and Announcements">
            <meta
                head-key="description"
                name="description"
                content="Latest news and announcements from the Professionalized Corps of Test Administrators, CSC Regional Office VIII."
            >
        </Head>

        <PageHeader
            title="News and Announcements"
            description="Official updates, advisories, and announcements from CSC Regional Office VIII."
            :breadcrumbs="[{ label: 'News' }]"
        />

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <EmptyState
                    icon="newspaper"
                    title="No announcements yet"
                    description="Official news and announcements will be published here. Please check back soon or follow our social media channels for updates."
                >
                    <template #action>
                        <BaseButton href="/" variant="outline">Back to Home</BaseButton>
                    </template>
                </EmptyState>
            </div>
        </section>
    </PublicLayout>
</template>
