<script setup>
/**
 * Inputs only. No client-side range checks on purpose: the server is the
 * single source of truth and its 422 messages are shown under each field.
 * The hints repeat the server ranges for convenience.
 */
const form = defineModel({ type: Object, required: true });

defineProps({
    errors: { type: Object, default: () => ({}) },
});

const fields = [
    { key: 'capacity_t', label: 'Rated capacity', unit: 't', step: 0.5, hint: '1 – 20 t, steps of 0.5' },
    { key: 'span_mm', label: 'Span (rail centre to centre)', unit: 'mm', step: 100, hint: '5 000 – 30 000 mm' },
    { key: 'rail_height_mm', label: 'Rail height (floor to rail top)', unit: 'mm', step: 10, hint: '3 500 – 22 000 mm' },
    { key: 'lifting_height_mm', label: 'Lifting height (floor to hook)', unit: 'mm', step: 10, hint: '2 500 – 20 000 mm, must fit under the hoist' },
    { key: 'building_height_mm', label: 'Building clear height (optional)', unit: 'mm', step: 10, hint: 'Leave empty to show the minimum needed' },
];
</script>

<template>
    <form class="space-y-3" @submit.prevent>
        <label class="block">
            <span class="text-sm font-medium">Design name</span>
            <input v-model="form.name" type="text" maxlength="120" class="input" placeholder="e.g. Workshop bay 2" />
            <span v-if="errors.name" class="error">{{ errors.name[0] }}</span>
        </label>

        <label v-for="f in fields" :key="f.key" class="block">
            <span class="text-sm font-medium">{{ f.label }}</span>
            <div class="flex items-center gap-2">
                <input v-model.number="form[f.key]" type="number" :step="f.step" class="input" :class="{ 'border-red-500': errors[f.key] }" />
                <span class="w-8 text-sm text-slate-500">{{ f.unit }}</span>
            </div>
            <span v-if="errors[f.key]" class="error">{{ errors[f.key][0] }}</span>
            <span v-else class="block text-xs text-slate-500">{{ f.hint }}</span>
        </label>

        <label class="block">
            <span class="text-sm font-medium">Duty group (FEM)</span>
            <select v-model="form.duty_group" class="input">
                <option v-for="g in ['A3', 'A4', 'A5', 'A6']" :key="g" :value="g">{{ g }}</option>
            </select>
            <span v-if="errors.duty_group" class="error">{{ errors.duty_group[0] }}</span>
            <span v-else class="block text-xs text-slate-500">Shown on the drawing only; does not change dimensions.</span>
        </label>

        <label class="block">
            <span class="text-sm font-medium">Notes</span>
            <textarea v-model="form.notes" rows="2" maxlength="2000" class="input"></textarea>
            <span v-if="errors.notes" class="error">{{ errors.notes[0] }}</span>
        </label>
    </form>
</template>
