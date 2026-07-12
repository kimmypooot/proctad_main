<script setup>
defineProps({
    rows: { type: Number, default: 5 },
    columns: { type: Number, default: 5 },
});
</script>

<template>
    <tr v-for="r in rows" :key="r" class="animate-pulse">
        <td v-for="c in columns" :key="c" class="px-4 py-3">
            <div class="h-4 rounded bg-slate-200" :style="{ width: c === 1 ? '70%' : '45%' }" />
        </td>
    </tr>
</template>
