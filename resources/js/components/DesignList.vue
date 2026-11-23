<script setup>
defineProps({
    designs: { type: Array, required: true },
    currentId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
});

defineEmits(['open', 'delete']);

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString() : '';
}
</script>

<template>
    <div>
        <p v-if="loading" class="text-sm text-slate-500">Loading…</p>
        <p v-else-if="designs.length === 0" class="text-sm text-slate-500">No saved designs yet.</p>
        <ul v-else class="divide-y divide-slate-200">
            <li
                v-for="d in designs"
                :key="d.id"
                class="group flex items-start justify-between gap-2 py-2"
                :class="{ 'bg-blue-50 -mx-2 px-2 rounded': d.id === currentId }"
            >
                <button type="button" class="min-w-0 flex-1 text-left" @click="$emit('open', d.id)">
                    <span class="block truncate text-sm font-medium">{{ d.name }}</span>
                    <span class="block text-xs text-slate-500">
                        {{ d.capacity_t }} t · {{ d.span_mm / 1000 }} m span · {{ formatDate(d.updated_at) }}
                    </span>
                </button>
                <button
                    type="button"
                    class="text-xs text-slate-400 hover:text-red-600"
                    :aria-label="`Delete ${d.name}`"
                    @click="$emit('delete', d)"
                >
                    Delete
                </button>
            </li>
        </ul>
    </div>
</template>
