<script setup>
import { reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';

const props = defineProps({
    examRoleRates: { type: Array, required: true },
    personnelTypeRates: { type: Array, required: true },
    can: { type: Object, required: true },
});

const groupBy = (rows) => {
    const groups = [];
    const byKey = new Map();

    for (const row of rows) {
        if (!byKey.has(row.group)) {
            const group = { key: row.group, label: row.group_label, rows: [] };
            byKey.set(row.group, group);
            groups.push(group);
        }
        byKey.get(row.group).rows.push(row);
    }

    return groups;
};

const examRoleGroups = groupBy(props.examRoleRates);
const personnelTypeGroups = groupBy(props.personnelTypeRates);

const forms = reactive({});

const formFor = (row) => {
    const key = `${row.payee_type}:${row.payee_value}`;
    if (!forms[key]) {
        forms[key] = useForm({
            payee_type: row.payee_type,
            payee_value: row.payee_value,
            amount: row.amount,
        });
    }
    return forms[key];
};

const save = (row) => {
    const form = formFor(row);
    form.put('/fee-schedules', { preserveScroll: true });
};
</script>

<template>
    <Head title="Fee Management" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Fee Management"
            subtitle="Configure honorarium rates per examination role and other examination personnel type. These rates are the single source used to compute Payroll and Payroll Posting reports."
        />

        <div class="mt-6 space-y-8">
            <section v-for="group in examRoleGroups" :key="`exam-role-${group.key}`">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</h2>
                <!-- Mobile (below md): a stacked card per rate. -->
                <div class="space-y-2 md:hidden">
                    <BaseCard
                        v-for="row in group.rows"
                        :key="`m-${row.payee_value}`"
                        padding="sm"
                    >
                        <p class="font-medium text-slate-900">
                            {{ row.label }}
                            <BaseBadge v-if="!row.configured" variant="warning" size="xs" class="ml-2">Not set</BaseBadge>
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                v-model.number="formFor(row).amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-32 rounded-md border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                                :disabled="!can.manage"
                            >
                            <button
                                v-if="can.manage"
                                type="button"
                                class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                                :disabled="formFor(row).processing"
                                @click="save(row)"
                            >
                                Save
                            </button>
                        </div>
                    </BaseCard>
                </div>

                <BaseTable
                    class="hidden md:block"
                    :columns="[
                        { label: 'Role' },
                        { label: 'Rate (PHP)' },
                        { label: '' },
                    ]"
                >
                            <tr v-for="row in group.rows" :key="row.payee_value" class="hover:bg-brand-50/40">
                                <td class="px-3 py-2 font-medium text-slate-900">
                                    {{ row.label }}
                                    <BaseBadge v-if="!row.configured" variant="warning" size="xs" class="ml-2">Not set</BaseBadge>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="formFor(row).amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-32 rounded-md border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                                        :disabled="!can.manage"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <button
                                        v-if="can.manage"
                                        type="button"
                                        class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                                        :disabled="formFor(row).processing"
                                        @click="save(row)"
                                    >
                                        Save
                                    </button>
                                </td>
                            </tr>
                </BaseTable>
            </section>

            <section v-for="group in personnelTypeGroups" :key="`personnel-type-${group.key}`">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</h2>
                <!-- Mobile (below md): a stacked card per rate. -->
                <div class="space-y-2 md:hidden">
                    <BaseCard
                        v-for="row in group.rows"
                        :key="`m-${row.payee_value}`"
                        padding="sm"
                    >
                        <p class="font-medium text-slate-900">
                            {{ row.label }}
                            <BaseBadge v-if="!row.configured" variant="warning" size="xs" class="ml-2">Not set</BaseBadge>
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                v-model.number="formFor(row).amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-32 rounded-md border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                                :disabled="!can.manage"
                            >
                            <button
                                v-if="can.manage"
                                type="button"
                                class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                                :disabled="formFor(row).processing"
                                @click="save(row)"
                            >
                                Save
                            </button>
                        </div>
                    </BaseCard>
                </div>

                <BaseTable
                    class="hidden md:block"
                    :columns="[
                        { label: 'Personnel Type' },
                        { label: 'Rate (PHP)' },
                        { label: '' },
                    ]"
                >
                            <tr v-for="row in group.rows" :key="row.payee_value" class="hover:bg-brand-50/40">
                                <td class="px-3 py-2 font-medium text-slate-900">
                                    {{ row.label }}
                                    <BaseBadge v-if="!row.configured" variant="warning" size="xs" class="ml-2">Not set</BaseBadge>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="formFor(row).amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-32 rounded-md border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                                        :disabled="!can.manage"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <button
                                        v-if="can.manage"
                                        type="button"
                                        class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                                        :disabled="formFor(row).processing"
                                        @click="save(row)"
                                    >
                                        Save
                                    </button>
                                </td>
                            </tr>
                </BaseTable>
            </section>
        </div>
    </DashboardLayout>
</template>
