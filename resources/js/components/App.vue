<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { api, ApiError } from '../api.js';
import DesignForm from './DesignForm.vue';
import DesignList from './DesignList.vue';
import DimensionTable from './DimensionTable.vue';
import CraneDrawing from './drawing/CraneDrawing.vue';

/** New designs start from the reference drawing crane (10 t, 25 m). */
const DEFAULTS = {
    name: '',
    capacity_t: 10,
    span_mm: 25000,
    lifting_height_mm: 7900,
    rail_height_mm: 8321,
    building_height_mm: null,
    duty_group: 'A5',
    notes: '',
};
const NUMERIC = ['capacity_t', 'span_mm', 'lifting_height_mm', 'rail_height_mm', 'building_height_mm'];
const CRANE_FIELDS = [...NUMERIC, 'duty_group'];

const form = reactive({ ...DEFAULTS });
const currentId = ref(null);
const savedSnapshot = ref(JSON.stringify(DEFAULTS));
const designs = ref([]);
const listLoading = ref(true);
const calculation = ref(null);
const previewStale = ref(false);
const fieldErrors = ref({});
const banner = ref(null);
const saving = ref(false);
const drawing = ref(null);

const payload = computed(() => {
    const out = { ...form };
    for (const key of NUMERIC) {
        out[key] = out[key] === '' || out[key] === undefined ? null : out[key];
    }
    return out;
});
const dirty = computed(() => JSON.stringify(payload.value) !== savedSnapshot.value);

function showBanner(type, text) {
    banner.value = { type, text };
    if (type === 'ok') {
        setTimeout(() => banner.value?.text === text && (banner.value = null), 2500);
    }
}

function handleError(error, context) {
    if (error instanceof ApiError && error.status === 422) {
        fieldErrors.value = error.errors;
        return;
    }
    showBanner('error', `${context}: ${error.message}`);
}

async function loadList() {
    listLoading.value = true;
    try {
        designs.value = await api.listDesigns();
    } catch (error) {
        handleError(error, 'Could not load designs');
    } finally {
        listLoading.value = false;
    }
}

/* Live preview: debounce, and ignore responses that arrive out of order. */
let previewTimer = null;
let previewSeq = 0;
async function preview() {
    const seq = ++previewSeq;
    const crane = Object.fromEntries(CRANE_FIELDS.map((k) => [k, payload.value[k]]));
    try {
        const result = await api.calculate(crane);
        if (seq !== previewSeq) return;
        calculation.value = result;
        previewStale.value = false;
        fieldErrors.value = Object.fromEntries(Object.entries(fieldErrors.value).filter(([k]) => !CRANE_FIELDS.includes(k)));
    } catch (error) {
        if (seq !== previewSeq) return;
        previewStale.value = true;
        if (error instanceof ApiError && error.status === 422) {
            const nonCrane = Object.fromEntries(Object.entries(fieldErrors.value).filter(([k]) => !CRANE_FIELDS.includes(k)));
            fieldErrors.value = { ...nonCrane, ...error.errors };
        } else {
            handleError(error, 'Preview failed');
        }
    }
}
watch(
    () => CRANE_FIELDS.map((k) => form[k]),
    () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(preview, 300);
    },
);

function fillForm(design) {
    for (const key of Object.keys(DEFAULTS)) {
        form[key] = design[key] ?? DEFAULTS[key];
    }
    form.notes = design.notes ?? '';
    savedSnapshot.value = JSON.stringify(payload.value);
}

function confirmDiscard() {
    return !dirty.value || window.confirm('You have unsaved changes. Discard them?');
}

function newDesign() {
    if (!confirmDiscard()) return;
    currentId.value = null;
    fieldErrors.value = {};
    fillForm(DEFAULTS);
    preview();
}

async function openDesign(id) {
    if (id === currentId.value || !confirmDiscard()) return;
    try {
        const design = await api.getDesign(id);
        currentId.value = design.id;
        fieldErrors.value = {};
        fillForm(design);
        calculation.value = design.calculation;
        previewStale.value = false;
    } catch (error) {
        handleError(error, 'Could not open design');
        if (error.status === 404) loadList();
    }
}

async function save() {
    saving.value = true;
    try {
        const design = currentId.value
            ? await api.updateDesign(currentId.value, payload.value)
            : await api.createDesign(payload.value);
        currentId.value = design.id;
        fieldErrors.value = {};
        fillForm(design);
        calculation.value = design.calculation;
        previewStale.value = false;
        showBanner('ok', 'Saved.');
        loadList();
    } catch (error) {
        handleError(error, 'Save failed');
        if (error.status === 404) {
            currentId.value = null;
            showBanner('error', 'This design was deleted elsewhere. Saving again will create a new one.');
        }
    } finally {
        saving.value = false;
    }
}

async function deleteDesign(design) {
    if (!window.confirm(`Delete "${design.name}"? This cannot be undone.`)) return;
    try {
        await api.deleteDesign(design.id);
        if (design.id === currentId.value) {
            currentId.value = null;
            savedSnapshot.value = '';
        }
        loadList();
    } catch (error) {
        handleError(error, 'Delete failed');
    }
}

function downloadSvg() {
    const svg = drawing.value?.svgEl;
    if (!svg) return;
    const blob = new Blob([new XMLSerializer().serializeToString(svg)], { type: 'image/svg+xml' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${(form.name || 'crane').replace(/[^\w-]+/g, '_')}.svg`;
    link.click();
    URL.revokeObjectURL(link.href);
}

onMounted(() => {
    loadList();
    preview();
});
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-300 bg-white">
            <div class="mx-auto flex max-w-[1600px] items-center justify-between px-4 py-3">
                <div>
                    <h1 class="text-lg font-semibold">EOT Crane Drawing Generator</h1>
                    <p class="text-xs text-slate-500">Single-girder, top-running overhead crane · preliminary layout drawing</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn" @click="newDesign">New</button>
                    <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
                        {{ saving ? 'Saving…' : currentId ? 'Save changes' : 'Save design' }}
                    </button>
                </div>
            </div>
        </header>

        <div
            v-if="banner"
            class="mx-auto mt-3 max-w-[1600px] px-4"
        >
            <div
                class="flex justify-between rounded border px-3 py-2 text-sm"
                :class="banner.type === 'ok' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-red-300 bg-red-50 text-red-800'"
            >
                <span>{{ banner.text }}</span>
                <button type="button" class="ml-4" @click="banner = null">×</button>
            </div>
        </div>

        <main class="mx-auto grid max-w-[1600px] gap-4 p-4 lg:grid-cols-[260px_300px_1fr]">
            <section class="panel">
                <h2 class="panel-title">Saved designs</h2>
                <DesignList :designs="designs" :current-id="currentId" :loading="listLoading" @open="openDesign" @delete="deleteDesign" />
            </section>

            <section class="panel">
                <h2 class="panel-title">
                    {{ currentId ? 'Edit design' : 'New design' }}
                    <span v-if="dirty" class="ml-1 text-xs font-normal text-amber-700">· unsaved</span>
                </h2>
                <DesignForm v-model="form" :errors="fieldErrors" />
            </section>

            <section class="min-w-0 space-y-4">
                <div class="panel">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="panel-title mb-0">Drawing</h2>
                        <button type="button" class="btn" :disabled="!calculation" @click="downloadSvg">Download SVG</button>
                    </div>
                    <p v-if="previewStale" class="mb-2 rounded bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Inputs are invalid (see the form). Showing the last valid drawing.
                    </p>
                    <ul v-if="calculation?.warnings?.length" class="mb-2 space-y-1">
                        <li v-for="w in calculation.warnings" :key="w" class="rounded bg-amber-50 px-3 py-2 text-sm text-amber-900">⚠ {{ w }}</li>
                    </ul>
                    <div :class="{ 'opacity-40': previewStale }">
                        <CraneDrawing
                            v-if="calculation"
                            ref="drawing"
                            :calculation="calculation"
                            :name="form.name"
                            :duty-group="form.duty_group"
                        />
                        <p v-else class="py-12 text-center text-sm text-slate-500">Enter valid inputs to see the drawing.</p>
                    </div>
                </div>

                <div v-if="calculation" class="panel">
                    <h2 class="panel-title">All dimensions and where they come from</h2>
                    <DimensionTable :dimensions="calculation.dimensions" />
                </div>
            </section>
        </main>
    </div>
</template>
