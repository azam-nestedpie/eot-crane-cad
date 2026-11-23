<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { api, ApiError } from '../api.js';
import DesignForm from './DesignForm.vue';
import DesignList from './DesignList.vue';
import DimensionTable from './DimensionTable.vue';
import CraneDrawing from './drawing/CraneDrawing.vue';
import ConfirmDialog from './ui/ConfirmDialog.vue';
import Icon from './ui/Icon.vue';

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
const savedSnapshot = ref(null);
const designs = ref([]);
const listLoading = ref(true);
const schema = ref(null);
const calculation = ref(null);
const previewStale = ref(false);
const previewLoading = ref(false);
const fieldErrors = ref({});
const saving = ref(false);
const drawing = ref(null);
const tab = ref('drawing');
const theme = ref(localStorageGet('drawing-theme') ?? 'paper');
const zoom = ref(1);
const toasts = ref([]);
const confirmRequest = ref(null);

function localStorageGet(key) {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}
watch(theme, (t) => {
    try {
        localStorage.setItem('drawing-theme', t);
    } catch {
        /* storage unavailable: theme just won't persist */
    }
});

const payload = computed(() => {
    const out = { ...form };
    for (const key of NUMERIC) {
        out[key] = out[key] === '' || out[key] === undefined ? null : out[key];
    }
    return out;
});
const dirty = computed(() => savedSnapshot.value !== null && JSON.stringify(payload.value) !== savedSnapshot.value);
const status = computed(() => {
    if (!currentId.value) return { text: 'Not saved', cls: 'bg-white/10 text-slate-300' };
    if (dirty.value) return { text: 'Unsaved changes', cls: 'bg-crane-400/15 text-crane-300' };
    return { text: 'Saved', cls: 'bg-emerald-400/15 text-emerald-300' };
});

/* ---------- toasts & confirm ---------- */
let toastId = 0;
function toast(type, text) {
    const id = ++toastId;
    toasts.value.push({ id, type, text });
    setTimeout(() => (toasts.value = toasts.value.filter((t) => t.id !== id)), type === 'error' ? 6000 : 2500);
}
function ask(options) {
    return new Promise((resolve) => (confirmRequest.value = { ...options, resolve }));
}
function answer(value) {
    confirmRequest.value?.resolve(value);
    confirmRequest.value = null;
}

function handleError(error, context) {
    if (error instanceof ApiError && error.status === 422) {
        fieldErrors.value = error.errors;
        toast('error', 'Please fix the highlighted fields.');
        return;
    }
    toast('error', `${context}: ${error.message}`);
}

/* ---------- data ---------- */
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
function onlyNonCraneErrors() {
    return Object.fromEntries(Object.entries(fieldErrors.value).filter(([k]) => !CRANE_FIELDS.includes(k)));
}
async function preview() {
    const seq = ++previewSeq;
    previewLoading.value = true;
    const crane = Object.fromEntries(CRANE_FIELDS.map((k) => [k, payload.value[k]]));
    try {
        const result = await api.calculate(crane);
        if (seq !== previewSeq) return;
        calculation.value = result;
        previewStale.value = false;
        fieldErrors.value = onlyNonCraneErrors();
    } catch (error) {
        if (seq !== previewSeq) return;
        previewStale.value = true;
        if (error instanceof ApiError && error.status === 422) {
            fieldErrors.value = { ...onlyNonCraneErrors(), ...error.errors };
        } else {
            toast('error', `Preview failed: ${error.message}`);
        }
    } finally {
        if (seq === previewSeq) previewLoading.value = false;
    }
}
watch(
    () => CRANE_FIELDS.map((k) => form[k]),
    () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(preview, 250);
    },
);

function fillForm(design) {
    for (const key of Object.keys(DEFAULTS)) {
        form[key] = design[key] ?? DEFAULTS[key];
    }
    savedSnapshot.value = JSON.stringify(payload.value);
}

async function confirmDiscard() {
    if (!dirty.value) return true;
    return ask({ title: 'Discard changes?', message: 'This design has unsaved changes.', confirmLabel: 'Discard' });
}

async function newDesign() {
    if (!(await confirmDiscard())) return;
    currentId.value = null;
    fieldErrors.value = {};
    fillForm(DEFAULTS);
    savedSnapshot.value = null;
    preview();
}

async function openDesign(id) {
    if (id === currentId.value || !(await confirmDiscard())) return;
    try {
        const design = await api.getDesign(id);
        currentId.value = design.id;
        fieldErrors.value = {};
        fillForm(design);
        clearTimeout(previewTimer);
        calculation.value = design.calculation;
        previewStale.value = false;
    } catch (error) {
        handleError(error, 'Could not open design');
        if (error.status === 404) loadList();
    }
}

async function save() {
    if (saving.value) return;
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
        toast('ok', `Saved “${design.name}”`);
        loadList();
    } catch (error) {
        if (error.status === 404) {
            currentId.value = null;
            savedSnapshot.value = null;
            toast('error', 'This design was deleted elsewhere. Save again to create it as new.');
        } else {
            handleError(error, 'Save failed');
        }
    } finally {
        saving.value = false;
    }
}

async function deleteDesign(design) {
    const ok = await ask({
        title: 'Delete design?',
        message: `“${design.name}” will be removed permanently.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    try {
        await api.deleteDesign(design.id);
        if (design.id === currentId.value) {
            currentId.value = null;
            savedSnapshot.value = null;
        }
        toast('ok', 'Design deleted');
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
    link.download = `${(form.name || 'crane').replace(/[^\w-]+/g, '_')}-${theme.value}.svg`;
    link.click();
    URL.revokeObjectURL(link.href);
}

/* ---------- KPIs ---------- */
const d = computed(() => calculation.value?.dimensions ?? {});
function n(value) {
    return Math.round(value).toLocaleString('en-US').replace(/,/g, '\u202f');
}
const hookReach = computed(() => {
    if (!d.value.max_lifting_height) return null;
    return Math.min(100, Math.round((d.value.lifting_height.value / d.value.max_lifting_height.value) * 100));
});
const kpis = computed(() => {
    if (!calculation.value) return [];
    const v = d.value;
    const building = v.building_height
        ? { value: v.building_height.value - v.min_building_height.value, label: 'Roof spare above crane', prefix: '+' }
        : null;
    return [
        { key: 'max_lifting_height', title: 'Max. hook height', value: v.max_lifting_height.value, unit: 'mm', est: true, icon: 'crane', bar: hookReach.value, barLabel: `${hookReach.value}% of reach used` },
        building
            ? { key: 'building', title: building.label, value: building.value, prefix: '+', unit: 'mm', est: true, icon: 'fit' }
            : { key: 'min_building_height', title: 'Min. building height', value: v.min_building_height.value, unit: 'mm', est: true, icon: 'fit' },
        { key: 'overall_length', title: 'Crane overall length', value: v.overall_length.value, unit: 'mm', est: false, icon: 'ruler' },
        { key: 'wheelbase', title: 'End carriage wheelbase', value: v.wheelbase.value, unit: 'mm', est: true, icon: 'grid', sub: `carriage ≈ ${n(v.end_carriage_length.value)} mm` },
    ];
});

/* ---------- zoom & shortcuts ---------- */
function setZoom(z) {
    zoom.value = Math.min(2.5, Math.max(0.5, Math.round(z * 100) / 100));
}
function onKey(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        save();
    }
}
function onBeforeUnload(e) {
    if (dirty.value) e.preventDefault();
}

onMounted(async () => {
    window.addEventListener('keydown', onKey);
    window.addEventListener('beforeunload', onBeforeUnload);
    loadList();
    preview();
    try {
        schema.value = await api.inputSchema();
    } catch (error) {
        handleError(error, 'Could not load input limits');
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100 lg:h-screen lg:overflow-hidden">
        <!-- ============ TOP BAR ============ -->
        <header class="relative z-20 flex h-16 shrink-0 items-center justify-between gap-4 bg-ink-950 px-4 text-white lg:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-crane-400 text-ink-950 shadow-lg shadow-crane-500/30">
                    <Icon name="crane" :size="20" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm leading-tight font-semibold tracking-tight">Crane Drawing Studio</p>
                    <p class="hidden text-[11px] text-slate-400 sm:block">Single-girder EOT · parametric layout</p>
                </div>
                <div class="mx-3 hidden h-8 w-px bg-white/10 md:block"></div>
                <div class="hidden min-w-0 items-center gap-2 md:flex">
                    <span class="truncate text-sm text-slate-200">{{ form.name || 'Untitled design' }}</span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="status.cls">{{ status.text }}</span>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="btn btn-ghost-dark" @click="newDesign">
                    <Icon name="plus" :size="16" /><span class="hidden sm:inline">New</span>
                </button>
                <button type="button" class="btn btn-primary" :disabled="saving" title="Save (Ctrl/⌘ + S)" @click="save">
                    <Icon :name="saving ? 'sheet' : 'save'" :size="16" :class="{ 'animate-pulse': saving }" />
                    {{ saving ? 'Saving…' : currentId ? 'Save changes' : 'Save design' }}
                </button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
            <!-- ============ SIDEBAR ============ -->
            <aside class="shrink-0 bg-ink-900 p-4 lg:w-72 lg:border-r lg:border-white/5">
                <div class="max-h-72 lg:h-full lg:max-h-none">
                    <DesignList :designs="designs" :current-id="currentId" :loading="listLoading" @open="openDesign" @delete="deleteDesign" />
                </div>
            </aside>

            <!-- ============ PARAMETERS ============ -->
            <section class="scroll-thin shrink-0 border-r border-slate-200 bg-white lg:w-[360px] lg:overflow-y-auto">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/90 px-6 py-4 backdrop-blur">
                    <div>
                        <h2 class="font-semibold text-slate-900">Parameters</h2>
                        <p class="text-xs text-slate-400">Validated live by the server</p>
                    </div>
                    <span
                        class="flex items-center gap-1.5 rounded-full px-2 py-1 text-[11px] font-medium"
                        :class="previewStale ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700'"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="[previewStale ? 'bg-red-500' : 'bg-emerald-500', { 'animate-pulse': previewLoading }]"></span>
                        {{ previewStale ? 'Invalid' : 'Valid' }}
                    </span>
                </div>
                <div class="px-6 py-6">
                    <DesignForm
                        v-model="form"
                        :schema="schema"
                        :errors="fieldErrors"
                        :suggested-building-height="d.min_building_height?.value ?? null"
                    />
                </div>
            </section>

            <!-- ============ WORKSPACE ============ -->
            <main class="scroll-thin min-w-0 flex-1 space-y-5 p-4 lg:overflow-y-auto lg:p-6">
                <!-- KPI strip -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <template v-if="calculation">
                        <div v-for="k in kpis" :key="k.key" class="card relative overflow-hidden p-4" :class="{ 'opacity-50': previewStale }">
                            <div class="flex items-start justify-between">
                                <p class="text-xs font-medium text-slate-500">{{ k.title }}</p>
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg" :class="k.est ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600'">
                                    <Icon :name="k.icon" :size="15" />
                                </span>
                            </div>
                            <p class="mt-2 font-mono text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">
                                <span v-if="k.est" class="text-amber-500">≈</span>{{ k.prefix ?? '' }}{{ n(k.value) }}<span class="ml-1 font-sans text-sm font-normal text-slate-400">{{ k.unit }}</span>
                            </p>
                            <div v-if="k.bar !== undefined && k.bar !== null" class="mt-3">
                                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="k.bar > 95 ? 'bg-red-400' : k.bar > 85 ? 'bg-crane-400' : 'bg-emerald-400'"
                                        :style="{ width: k.bar + '%' }"
                                    ></div>
                                </div>
                                <p class="mt-1.5 text-[11px] text-slate-400">{{ k.barLabel }}</p>
                            </div>
                            <p v-else class="mt-3 text-[11px] text-slate-400">{{ k.sub ?? (k.est ? 'Estimate, see table' : 'Exact geometry') }}</p>
                        </div>
                    </template>
                    <template v-else>
                        <div v-for="i in 4" :key="i" class="card h-[118px] animate-pulse bg-slate-50"></div>
                    </template>
                </div>

                <!-- warnings -->
                <TransitionGroup name="fade" tag="div" class="space-y-2">
                    <div
                        v-for="w in calculation?.warnings ?? []"
                        :key="w"
                        class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        <Icon name="alert" :size="18" class="mt-px shrink-0 text-amber-500" />{{ w }}
                    </div>
                    <div
                        v-if="previewStale"
                        key="stale"
                        class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    >
                        <Icon name="alert" :size="18" class="mt-px shrink-0 text-red-500" />
                        The server rejected these inputs (see the form). Showing the last valid drawing.
                    </div>
                </TransitionGroup>

                <!-- drawing / table -->
                <div class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                        <div class="segmented" role="tablist">
                            <button type="button" role="tab" :aria-pressed="tab === 'drawing'" class="!inline-flex items-center gap-1.5" @click="tab = 'drawing'">
                                <Icon name="sheet" :size="14" /> Drawing
                            </button>
                            <button type="button" role="tab" :aria-pressed="tab === 'table'" class="!inline-flex items-center gap-1.5" @click="tab = 'table'">
                                <Icon name="table" :size="14" /> Dimensions
                                <span v-if="calculation" class="font-mono opacity-50">{{ Object.keys(d).length }}</span>
                            </button>
                        </div>

                        <div v-if="tab === 'drawing'" class="flex flex-wrap items-center gap-2">
                            <div class="segmented" role="group" aria-label="Drawing theme">
                                <button type="button" :aria-pressed="theme === 'paper'" @click="theme = 'paper'">Paper</button>
                                <button type="button" :aria-pressed="theme === 'blueprint'" @click="theme = 'blueprint'">Blueprint</button>
                            </div>
                            <div class="flex items-center rounded-lg ring-1 ring-slate-200">
                                <button type="button" class="icon-btn" aria-label="Zoom out" @click="setZoom(zoom - 0.25)"><Icon name="zoomOut" :size="16" /></button>
                                <button type="button" class="w-12 font-mono text-xs text-slate-600 tabular-nums" title="Reset zoom" @click="setZoom(1)">{{ Math.round(zoom * 100) }}%</button>
                                <button type="button" class="icon-btn" aria-label="Zoom in" @click="setZoom(zoom + 0.25)"><Icon name="zoomIn" :size="16" /></button>
                            </div>
                            <button type="button" class="btn btn-soft !py-1.5" :disabled="!calculation" @click="downloadSvg">
                                <Icon name="download" :size="15" /> SVG
                            </button>
                        </div>
                    </div>

                    <div v-show="tab === 'drawing'" class="canvas-dots scroll-thin overflow-auto p-4 sm:p-8">
                        <div
                            class="mx-auto transition-[width] duration-200"
                            :style="{ width: zoom * 100 + '%', minWidth: zoom * 720 + 'px' }"
                            :class="{ 'opacity-40 grayscale': previewStale }"
                        >
                            <div v-if="calculation" class="overflow-hidden rounded-md shadow-[0_20px_50px_-20px_rgba(15,23,42,0.45)] ring-1 ring-slate-900/10">
                                <CraneDrawing ref="drawing" :calculation="calculation" :name="form.name" :duty-group="form.duty_group" :theme="theme" />
                            </div>
                            <div v-else class="flex aspect-[4/3] items-center justify-center rounded-md bg-white/60">
                                <p class="text-sm text-slate-400">Calculating…</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="tab === 'table' && calculation" class="p-5">
                        <DimensionTable :dimensions="calculation.dimensions" />
                    </div>
                </div>

                <p class="pb-2 text-center text-[11px] text-slate-400">
                    Preliminary layout only. Amber ≈ values are estimates from a single reference drawing. Not for construction.
                </p>
            </main>
        </div>

        <!-- toasts -->
        <div class="pointer-events-none fixed right-4 bottom-4 z-50 flex flex-col items-end gap-2">
            <TransitionGroup name="fade">
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    class="pointer-events-auto flex max-w-sm items-start gap-2.5 rounded-xl bg-ink-900 px-4 py-3 text-sm text-white shadow-xl"
                >
                    <span
                        class="mt-px flex h-5 w-5 shrink-0 items-center justify-center rounded-full"
                        :class="t.type === 'ok' ? 'bg-emerald-400/20 text-emerald-300' : 'bg-red-400/20 text-red-300'"
                    >
                        <Icon :name="t.type === 'ok' ? 'check' : 'x'" :size="13" />
                    </span>
                    {{ t.text }}
                </div>
            </TransitionGroup>
        </div>

        <ConfirmDialog :request="confirmRequest" @answer="answer" />
    </div>
</template>
