<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    dimensions: { type: Object, required: true },
});

const SOURCES = {
    input: { label: 'Input', cls: 'bg-slate-100 text-slate-700 ring-slate-200', dot: 'bg-slate-400' },
    reference: { label: 'Reference', cls: 'bg-blue-50 text-blue-700 ring-blue-200', dot: 'bg-blue-500' },
    derived: { label: 'Derived', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
    estimate: { label: 'Estimate', cls: 'bg-amber-50 text-amber-800 ring-amber-200', dot: 'bg-amber-500' },
};

const filter = ref('all');
const rows = computed(() => Object.entries(props.dimensions).filter(([, d]) => filter.value === 'all' || d.source === filter.value));
const counts = computed(() => {
    const out = { all: Object.keys(props.dimensions).length };
    for (const d of Object.values(props.dimensions)) out[d.source] = (out[d.source] ?? 0) + 1;
    return out;
});

function fmt(d) {
    const n = Number(d.value).toLocaleString('en-US').replace(/,/g, ' ');
    return `${d.source === 'estimate' ? '≈ ' : ''}${n}`;
}
</script>

<template>
    <div>
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="key in ['all', ...Object.keys(SOURCES)]"
                :key="key"
                type="button"
                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium ring-1 transition"
                :class="filter === key ? 'bg-ink-900 text-white ring-ink-900' : 'bg-white text-slate-600 ring-slate-200 hover:ring-slate-400'"
                @click="filter = key"
            >
                <span v-if="key !== 'all'" class="h-1.5 w-1.5 rounded-full" :class="SOURCES[key].dot"></span>
                {{ key === 'all' ? 'All' : SOURCES[key].label }}
                <span class="font-mono opacity-60">{{ counts[key] ?? 0 }}</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-400">
                        <th class="py-2.5 pr-4 font-semibold">Dimension</th>
                        <th class="py-2.5 pr-4 text-right font-semibold">Value</th>
                        <th class="py-2.5 pr-4 font-semibold">Source</th>
                        <th class="py-2.5 font-semibold">Where it comes from</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="[key, d] in rows" :key="key" class="border-b border-slate-100 align-top transition hover:bg-slate-50/70">
                        <td class="py-3 pr-4 font-medium text-slate-800">{{ d.label }}</td>
                        <td class="py-3 pr-4 text-right font-mono whitespace-nowrap tabular-nums" :class="d.source === 'estimate' ? 'text-amber-700' : 'text-slate-900'">
                            {{ fmt(d) }} <span class="text-xs text-slate-400">{{ d.unit }}</span>
                        </td>
                        <td class="py-3 pr-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1" :class="SOURCES[d.source].cls">
                                <span class="h-1.5 w-1.5 rounded-full" :class="SOURCES[d.source].dot"></span>
                                {{ SOURCES[d.source].label }}
                            </span>
                        </td>
                        <td class="max-w-md py-3 text-xs leading-relaxed text-slate-500">{{ d.basis }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
