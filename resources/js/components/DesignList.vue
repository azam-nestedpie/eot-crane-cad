<script setup>
import { computed, ref } from 'vue';
import Icon from './ui/Icon.vue';

const props = defineProps({
    designs: { type: Array, required: true },
    currentId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
});

defineEmits(['open', 'delete']);

const query = ref('');
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return q ? props.designs.filter((d) => d.name.toLowerCase().includes(q)) : props.designs;
});

function relative(iso) {
    if (!iso) return '';
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} min ago`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours} h ago`;
    return new Date(iso).toLocaleDateString();
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="relative mb-3">
            <Icon name="search" :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-500" />
            <input
                v-model="query"
                type="search"
                placeholder="Search designs"
                class="w-full rounded-lg bg-white/5 py-2 pr-3 pl-9 text-sm text-slate-200 ring-1 ring-white/10 outline-none placeholder:text-slate-500 focus:ring-crane-400/60"
            />
        </div>

        <div class="mb-2 flex items-center justify-between px-1">
            <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Saved designs</span>
            <span class="rounded-full bg-white/5 px-2 py-0.5 font-mono text-[10px] text-slate-400">{{ designs.length }}</span>
        </div>

        <div class="scroll-thin -mx-2 min-h-0 flex-1 overflow-y-auto px-2">
            <div v-if="loading" class="space-y-2">
                <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-white/5"></div>
            </div>

            <div v-else-if="designs.length === 0" class="mt-6 rounded-xl border border-dashed border-white/10 p-5 text-center">
                <Icon name="sheet" :size="26" class="mx-auto text-slate-600" />
                <p class="mt-2 text-sm text-slate-400">No saved designs yet</p>
                <p class="text-xs text-slate-500">Adjust the inputs and press Save.</p>
            </div>

            <p v-else-if="filtered.length === 0" class="mt-4 text-center text-sm text-slate-500">No match for “{{ query }}”.</p>

            <ul v-else class="space-y-1.5">
                <li v-for="d in filtered" :key="d.id" class="group relative">
                    <button
                        type="button"
                        class="w-full rounded-xl px-3 py-2.5 text-left transition"
                        :class="
                            d.id === currentId
                                ? 'bg-crane-400/10 ring-1 ring-crane-400/40'
                                : 'hover:bg-white/5'
                        "
                        @click="$emit('open', d.id)"
                    >
                        <span class="flex items-center gap-2">
                            <span
                                class="h-1.5 w-1.5 shrink-0 rounded-full"
                                :class="d.id === currentId ? 'bg-crane-400' : 'bg-slate-600'"
                            ></span>
                            <span class="truncate pr-6 text-sm font-medium" :class="d.id === currentId ? 'text-white' : 'text-slate-200'">
                                {{ d.name }}
                            </span>
                        </span>
                        <span class="mt-1.5 flex items-center gap-1.5 pl-3.5 font-mono text-[11px] text-slate-400">
                            <span class="rounded bg-white/5 px-1.5 py-0.5">{{ d.capacity_t }} t</span>
                            <span class="rounded bg-white/5 px-1.5 py-0.5">{{ d.span_mm / 1000 }} m</span>
                            <span class="font-sans text-slate-500">· {{ relative(d.updated_at) }}</span>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="absolute top-2 right-2 rounded-md p-1.5 text-slate-500 opacity-0 transition group-hover:opacity-100 hover:bg-red-500/10 hover:text-red-400 focus:opacity-100"
                        :aria-label="`Delete ${d.name}`"
                        @click="$emit('delete', d)"
                    >
                        <Icon name="trash" :size="15" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
