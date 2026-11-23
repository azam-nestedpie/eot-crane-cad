<script setup>
/**
 * Inputs. Slider limits and hints come from GET /input-schema, so they are
 * the exact limits the server validates against. The server still does all
 * validation; its messages are shown under each field.
 */
import { computed } from 'vue';
import Icon from './ui/Icon.vue';

const form = defineModel({ type: Object, required: true });

const props = defineProps({
    schema: { type: Object, default: null },
    errors: { type: Object, default: () => ({}) },
    suggestedBuildingHeight: { type: Number, default: null },
});

const CAPACITY_PRESETS = [1, 2, 3, 5, 10, 16, 20];

const groups = computed(() => [
    {
        title: 'Building & runway',
        fields: [
            { key: 'span_mm', help: 'Rail centre to rail centre' },
            { key: 'rail_height_mm', help: 'Floor to top of runway rail' },
        ],
    },
    {
        title: 'Hook',
        fields: [{ key: 'lifting_height_mm', help: 'Floor to hook at its highest' }],
    },
]);

function meta(key) {
    return props.schema?.fields?.[key];
}

function fill(key) {
    const m = meta(key);
    const value = Number(form.value[key]);
    if (!m || !Number.isFinite(value)) return '0%';
    return `${Math.min(100, Math.max(0, ((value - m.min) / (m.max - m.min)) * 100))}%`;
}

function fmt(n) {
    return Number(n).toLocaleString('en-US').replace(/,/g, ' ');
}

const buildingEnabled = computed(() => form.value.building_height_mm !== null && form.value.building_height_mm !== '');

function toggleBuilding() {
    if (buildingEnabled.value) {
        form.value.building_height_mm = null;
        return;
    }
    const m = meta('building_height_mm');
    const suggestion = props.suggestedBuildingHeight ? Math.ceil(props.suggestedBuildingHeight / 100) * 100 : m.min;
    form.value.building_height_mm = Math.min(m.max, Math.max(m.min, suggestion));
}
</script>

<template>
    <form class="space-y-7" @submit.prevent>
        <div>
            <label class="section-label mb-2 block" for="design-name">Design name</label>
            <input
                id="design-name"
                v-model="form.name"
                type="text"
                maxlength="120"
                class="text-input text-base font-medium"
                :class="{ 'ring-red-400': errors.name }"
                placeholder="e.g. Workshop bay 2"
            />
            <p v-if="errors.name" class="mt-1.5 text-xs text-red-600">{{ errors.name[0] }}</p>
        </div>

        <div v-if="!schema" class="space-y-4">
            <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-lg bg-slate-100"></div>
        </div>

        <template v-else>
            <!-- Capacity -->
            <div>
                <div class="mb-3 flex items-baseline justify-between">
                    <span class="section-label">Load</span>
                </div>
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium text-slate-700" for="capacity_t">Rated capacity</label>
                    <div class="num-box !w-28" :class="{ '!ring-red-400': errors.capacity_t }">
                        <input id="capacity_t" v-model.number="form.capacity_t" type="number" :step="meta('capacity_t').step" />
                        <span>t</span>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-7 gap-1">
                    <button
                        v-for="preset in CAPACITY_PRESETS"
                        :key="preset"
                        type="button"
                        class="rounded-md py-1 font-mono text-[11px] ring-1 transition"
                        :class="
                            Number(form.capacity_t) === preset
                                ? 'bg-ink-900 text-crane-300 ring-ink-900'
                                : 'bg-white text-slate-600 ring-slate-200 hover:ring-slate-400'
                        "
                        @click="form.capacity_t = preset"
                    >
                        {{ preset }} t
                    </button>
                </div>
                <p v-if="errors.capacity_t" class="mt-1.5 text-xs text-red-600">{{ errors.capacity_t[0] }}</p>
            </div>

            <!-- Slider groups -->
            <div v-for="group in groups" :key="group.title">
                <span class="section-label mb-3 block">{{ group.title }}</span>
                <div class="space-y-5">
                    <div v-for="f in group.fields" :key="f.key">
                        <div class="mb-1 flex items-end justify-between gap-3">
                            <div>
                                <label :for="f.key" class="block text-sm font-medium text-slate-700">{{ meta(f.key).label }}</label>
                                <span class="text-xs text-slate-400">{{ f.help }}</span>
                            </div>
                            <div class="num-box" :class="{ '!ring-red-400': errors[f.key] }">
                                <input :id="f.key" v-model.number="form[f.key]" type="number" :step="meta(f.key).step" />
                                <span>mm</span>
                            </div>
                        </div>
                        <input
                            v-model.number="form[f.key]"
                            type="range"
                            class="slider mt-2 w-full"
                            :min="meta(f.key).min"
                            :max="meta(f.key).max"
                            :step="meta(f.key).step"
                            :style="{ '--fill': fill(f.key) }"
                            :aria-label="meta(f.key).label"
                        />
                        <div class="mt-1 flex justify-between font-mono text-[10px] text-slate-400">
                            <span>{{ fmt(meta(f.key).min) }}</span>
                            <span>{{ fmt(meta(f.key).max) }}</span>
                        </div>
                        <p v-if="errors[f.key]" class="mt-1 flex gap-1.5 rounded-lg bg-red-50 px-2.5 py-2 text-xs text-red-700">
                            <Icon name="alert" :size="14" class="mt-px shrink-0" />{{ errors[f.key][0] }}
                        </p>
                    </div>

                    <!-- Optional building height lives with the building group -->
                    <div v-if="group.title === 'Building & runway'">
                        <div class="mb-1 flex items-end justify-between gap-3">
                            <div>
                                <span class="block text-sm font-medium text-slate-700">{{ meta('building_height_mm').label }}</span>
                                <span class="text-xs text-slate-400">Optional · checked against the crane</span>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="buildingEnabled"
                                class="relative h-5 w-9 shrink-0 rounded-full transition"
                                :class="buildingEnabled ? 'bg-crane-400' : 'bg-slate-300'"
                                @click="toggleBuilding"
                            >
                                <span
                                    class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition"
                                    :class="{ 'translate-x-4': buildingEnabled }"
                                ></span>
                            </button>
                        </div>
                        <template v-if="buildingEnabled">
                            <div class="mt-2 flex items-center gap-3">
                                <input
                                    v-model.number="form.building_height_mm"
                                    type="range"
                                    class="slider flex-1"
                                    :min="meta('building_height_mm').min"
                                    :max="meta('building_height_mm').max"
                                    :step="meta('building_height_mm').step"
                                    :style="{ '--fill': fill('building_height_mm') }"
                                    aria-label="Building clear height"
                                />
                                <div class="num-box" :class="{ '!ring-red-400': errors.building_height_mm }">
                                    <input v-model.number="form.building_height_mm" type="number" :step="meta('building_height_mm').step" />
                                    <span>mm</span>
                                </div>
                            </div>
                        </template>
                        <p v-else class="mt-1 text-xs text-slate-400">Off: the drawing shows the minimum height needed.</p>
                        <p v-if="errors.building_height_mm" class="mt-1 flex gap-1.5 rounded-lg bg-red-50 px-2.5 py-2 text-xs text-red-700">
                            <Icon name="alert" :size="14" class="mt-px shrink-0" />{{ errors.building_height_mm[0] }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Duty group -->
            <div>
                <span class="section-label mb-3 block">Classification</span>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-700">FEM duty group</span>
                    <div class="segmented" role="group" aria-label="Duty group">
                        <button
                            v-for="g in schema.duty_groups"
                            :key="g"
                            type="button"
                            :aria-pressed="form.duty_group === g"
                            @click="form.duty_group = g"
                        >
                            {{ g }}
                        </button>
                    </div>
                </div>
                <p class="mt-1.5 text-xs text-slate-400">Shown on the drawing only; does not change dimensions.</p>
                <p v-if="errors.duty_group" class="mt-1 text-xs text-red-600">{{ errors.duty_group[0] }}</p>
            </div>

            <div>
                <label class="section-label mb-2 block" for="notes">Notes</label>
                <textarea id="notes" v-model="form.notes" rows="3" maxlength="2000" class="text-input resize-none" placeholder="Customer, site, anything to remember…"></textarea>
                <p v-if="errors.notes" class="mt-1 text-xs text-red-600">{{ errors.notes[0] }}</p>
            </div>
        </template>
    </form>
</template>
