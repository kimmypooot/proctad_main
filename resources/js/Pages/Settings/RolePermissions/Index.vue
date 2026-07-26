<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';

const props = defineProps({
    roles: { type: Array, required: true },
    groups: { type: Array, required: true },
});

/*
 * Saving happens per cell. `saving` holds the "role|permission" key currently
 * in flight so only that checkbox is disabled — locking the whole grid on every
 * click makes the page feel broken when an admin is ticking a row of boxes.
 */
const saving = ref(null);
const resetting = ref(null);

// Read from the props rather than written out, so a role renamed at
// Administration → Roles is named correctly here too.
const lockedRoleLabel = computed(
    () => props.roles.find((role) => role.locked)?.label ?? 'Super Administrator',
);

const toggle = (permission, role, granted) => {
    if (role.locked) return;

    saving.value = `${role.value}|${permission.value}`;

    router.put('/role-permissions', {
        role: role.value,
        permission: permission.value,
        granted,
    }, {
        preserveScroll: true,
        preserveState: false,
        onFinish: () => (saving.value = null),
    });
};

const confirmReset = () => router.delete('/role-permissions', {
    data: { role: resetting.value.value },
    preserveScroll: true,
    onFinish: () => (resetting.value = null),
});
</script>

<template>
    <Head title="Role Permissions" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Role Permissions"
            subtitle="What each role is allowed to do. Changes take effect immediately."
        />

        <BaseCard padding="sm" class="mt-6">
            <p class="text-sm leading-relaxed text-slate-600">
                These control <strong>what</strong> a role may do, not <strong>which records</strong> it may
                reach. Field Office roles stay limited to their own testing centers however
                these are set, and rules tied to a record's status — only a pending certificate
                can be approved — are not affected either.
            </p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ lockedRoleLabel }} always holds every permission, so there is always a way
                back into this page.
            </p>
        </BaseCard>

        <div v-for="group in groups" :key="group.name" class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ group.name }}</h2>

            <BaseCard padding="none" class="mt-2 overflow-x-auto">
                <table class="w-full min-w-[46rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Permission</th>
                            <th
                                v-for="role in roles"
                                :key="role.value"
                                class="px-2 py-2 text-center font-medium text-slate-600"
                            >
                                <span class="block max-w-[6rem] text-xs leading-tight">{{ role.label }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="permission in group.permissions"
                            :key="permission.value"
                            class="border-b border-slate-100 last:border-0 hover:bg-brand-50/40"
                        >
                            <td class="px-3 py-2">
                                <p class="text-slate-800">{{ permission.label }}</p>
                                <p v-if="permission.scope_note" class="mt-0.5 text-xs text-slate-400">
                                    {{ permission.scope_note }}
                                </p>
                            </td>
                            <td
                                v-for="role in roles"
                                :key="role.value"
                                class="px-2 py-2 text-center"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:opacity-40"
                                    :checked="permission.roles[role.value].granted"
                                    :disabled="role.locked || saving === `${role.value}|${permission.value}`"
                                    :title="role.locked
                                        ? `${lockedRoleLabel} always holds every permission`
                                        : permission.roles[role.value].is_default
                                            ? 'Default'
                                            : 'Changed from the default'"
                                    @change="toggle(permission, role, $event.target.checked)"
                                />
                                <!-- A dot marks a cell that no longer matches the built-in
                                     default, so an admin can see at a glance what has been
                                     changed without remembering the original grid. -->
                                <span
                                    v-if="!role.locked && permission.roles[role.value].granted !== permission.roles[role.value].is_default"
                                    class="mx-auto mt-1 block size-1.5 rounded-full bg-amber-400"
                                    title="Changed from the default"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </BaseCard>
        </div>

        <BaseCard padding="sm" class="mt-6">
            <h2 class="text-sm font-medium text-slate-700">Reset a role</h2>
            <p class="mt-1 text-xs text-slate-500">Discards every change made to that role and restores the built-in defaults.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <BaseButton
                    v-for="role in roles.filter((r) => !r.locked)"
                    :key="role.value"
                    variant="outline"
                    size="sm"
                    @click="resetting = role"
                >
                    {{ role.label }}
                </BaseButton>
            </div>
        </BaseCard>

        <BaseModal :show="!!resetting" title="Reset permissions" @close="resetting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Restore <strong>{{ resetting?.label }}</strong> to its default permissions?
                Every change made to this role will be discarded.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="resetting = null">Cancel</BaseButton>
                <BaseButton variant="primary" size="sm" @click="confirmReset">Reset to defaults</BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
