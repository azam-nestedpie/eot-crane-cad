<script setup>
defineProps({
    dimensions: { type: Object, required: true },
});

const badge = {
    input: 'bg-slate-200 text-slate-800',
    reference: 'bg-blue-100 text-blue-800',
    derived: 'bg-emerald-100 text-emerald-800',
    estimate: 'bg-amber-100 text-amber-900',
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-300 text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Dimension</th>
                    <th class="py-2 pr-3 text-right">Value</th>
                    <th class="py-2 pr-3">Source</th>
                    <th class="py-2">Basis</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(d, key) in dimensions" :key="key" class="border-b border-slate-100 align-top">
                    <td class="py-1.5 pr-3">{{ d.label }}</td>
                    <td class="py-1.5 pr-3 text-right font-mono whitespace-nowrap">
                        {{ d.source === 'estimate' ? '≈' : '' }}{{ d.value }} {{ d.unit }}
                    </td>
                    <td class="py-1.5 pr-3">
                        <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="badge[d.source]">{{ d.source }}</span>
                    </td>
                    <td class="py-1.5 text-xs text-slate-600">{{ d.basis }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
