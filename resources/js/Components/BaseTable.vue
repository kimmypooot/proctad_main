<script setup>
import { computed } from 'vue';
import TableSkeleton from './TableSkeleton.vue';

/**
 * The canonical data table chrome — the `overflow-x-auto` bordered surface, the
 * `bg-slate-50` uppercase header, the `divide-y` body, and the `hover:bg-brand-50/40`
 * row transition — that was hand-repeated across ~39 raw <table> blocks. Row cells
 * stay in the caller (columns differ too much to abstract); BaseTable owns the wrapper
 * and header so every table stays visually in lockstep.
 *
 * Two ways to declare the header:
 *   - pass `columns` (array of { label, class?, align?, srLabel? }) for the common case, or
 *   - use the `#head` slot to hand-build a <tr> of <th> (e.g. when a header cell holds a
 *     select-all checkbox). When `#head` is present, `columns` is ignored for rendering
 *     but still drives the loading skeleton's column count unless `skeletonColumns` is set.
 *
 * `loading` swaps the row slot for a TableSkeleton; the caller keeps its own empty state.
 */
const props = defineProps({
    columns: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    /**
     * Drop the bordered surface and let a caller-supplied wrapper own it. For a
     * table that sits inside a BaseCard under a caption row (the duplicate-member
     * groups) — the default chrome would draw a second border inside the card.
     */
    bare: { type: Boolean, default: false },
    skeletonRows: { type: Number, default: 5 },
    /** Override the skeleton's column count (defaults to the number of `columns`). */
    skeletonColumns: { type: Number, default: null },
});

const alignClass = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};

const skeletonCols = computed(() => props.skeletonColumns ?? props.columns.length ?? 5);
</script>

<template>
    <div class="overflow-x-auto" :class="bare ? '' : 'rounded-xl border border-slate-200 bg-white'">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <slot name="head">
                    <tr>
                        <th
                            v-for="(col, i) in columns"
                            :key="i"
                            class="px-3 py-2"
                            :class="[col.class, col.align ? alignClass[col.align] : '']"
                        >
                            <span v-if="col.srLabel" class="sr-only">{{ col.label }}</span>
                            <template v-else>{{ col.label }}</template>
                        </th>
                    </tr>
                </slot>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <TableSkeleton v-if="loading" :rows="skeletonRows" :columns="skeletonCols" />
                <slot v-else />
            </tbody>
        </table>
    </div>
</template>
