<script setup>
/**
 * Front view + plan view + title block for a single-girder top-running crane.
 *
 * All geometry comes from the server calculation (millimetres). Here it is
 * only mapped to pixels: one uniform scale for both views so proportions are
 * true, while text and arrows stay a fixed pixel size.
 *
 * Shapes of the hoist, corbels, girder flange width, etc. are SCHEMATIC
 * (visual only). Only the dimensioned values carry meaning.
 */
import { computed, provide, ref } from 'vue';
import Dim from './Dim.vue';
import { palettes } from './palettes.js';

const props = defineProps({
    calculation: { type: Object, required: true },
    name: { type: String, default: '' },
    dutyGroup: { type: String, default: '' },
    theme: { type: String, default: 'paper' },
});

const c = computed(() => palettes[props.theme] ?? palettes.paper);
provide('palette', c);

const svgEl = ref(null);
defineExpose({ svgEl });

const W = 1200;
const SIDE = 190;
const FRONT_TOP = 130;
const FRONT_MAX_H = 420;
const FONT = 'Helvetica, Arial, sans-serif';

const dims = computed(() => props.calculation.dimensions);
const v = computed(() => Object.fromEntries(Object.entries(dims.value).map(([k, d]) => [k, d.value])));

function isEstimate(key) {
    return dims.value[key]?.source === 'estimate';
}

function label(key, prefix = '') {
    const n = Math.round(v.value[key]).toLocaleString('en-US').replace(/,/g, ' ');
    return `${prefix}${isEstimate(key) ? '≈' : ''}${n}`;
}

function atLeast(px, min) {
    return Math.max(px, min);
}

/** Front view mapping and key heights. */
const f = computed(() => {
    const d = v.value;
    const wallL = -(d.end_overhang + d.wall_clearance);
    const wallR = d.span + d.end_overhang + d.wall_clearance;
    const hasBuilding = d.building_height !== undefined;
    const roof = hasBuilding ? d.building_height : d.min_building_height;
    const widthMm = wallR - wallL;
    const s = Math.min((W - 2 * SIDE) / widthMm, FRONT_MAX_H / roof);
    const ox = (W - widthMm * s) / 2;
    const X = (mm) => ox + (mm - wallL) * s;
    const Y = (mm) => FRONT_TOP + (roof - mm) * s;
    const gb = d.rail_height + d.girder_bottom_above_rail;
    const gt = d.rail_height + d.crane_top_above_rail;

    return { s, X, Y, wallL, wallR, roof, hasBuilding, gb, gt, xc: d.span / 2, height: FRONT_TOP + roof * s + 40 };
});

/** Plan view shares the front view's scale and X mapping. */
const p = computed(() => {
    const d = v.value;
    const top = f.value.height + 30;
    const railLen = d.end_carriage_length + 2000;
    const yc = top + 30 + (railLen / 2) * f.value.s;
    const Y = (mm) => yc + mm * f.value.s;

    return { top, railLen, Y, height: 30 + railLen * f.value.s + 100 };
});

const titleTop = computed(() => p.value.top + p.value.height + 10);
const totalHeight = computed(() => titleTop.value + 90);
const today = new Date().toISOString().slice(0, 10);

const hook = computed(() => {
    const { X, Y, xc } = f.value;
    const d = v.value;
    const hr = d.hook_headroom;
    const r = atLeast(0.05 * hr * f.value.s, 3);
    const cx = X(xc);
    return {
        body: { x: X(xc - 650), y: Y(f.value.gb), w: X(xc + 650) - X(xc - 650), h: atLeast(0.45 * hr * f.value.s, 6) },
        ropeTop: Y(f.value.gb - 0.45 * hr),
        ropeBottom: Y(d.lifting_height + 0.25 * hr),
        block: { x: X(xc - 180), y: Y(d.lifting_height + 0.25 * hr), w: atLeast(360 * f.value.s, 6), h: atLeast(0.15 * hr * f.value.s, 3) },
        path: `M ${cx} ${Y(d.lifting_height + 0.1 * hr)} L ${cx} ${Y(d.lifting_height) - r} A ${r} ${r} 0 1 1 ${cx - 2 * r} ${Y(d.lifting_height) - r}`,
        cx,
    };
});

const pendantX = computed(() => v.value.span * 0.3);
</script>

<template>
    <svg
        ref="svgEl"
        xmlns="http://www.w3.org/2000/svg"
        :viewBox="`0 0 ${W} ${totalHeight}`"
        class="block h-auto w-full"
        :font-family="FONT"
    >
        <defs>
            <pattern id="hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                <line x1="0" y1="0" x2="0" y2="6" :stroke="c.hatch" stroke-width="1" />
            </pattern>
            <pattern id="bp-grid" width="20" height="20" patternUnits="userSpaceOnUse">
                <path d="M 20 0 L 0 0 0 20" fill="none" :stroke="c.grid" stroke-width="0.6" />
            </pattern>
        </defs>

        <rect x="0" y="0" :width="W" :height="totalHeight" :fill="c.bg" />
        <rect v-if="c.grid" x="0" y="0" :width="W" :height="totalHeight" fill="url(#bp-grid)" />

        <rect x="4" y="4" :width="W - 8" :height="totalHeight - 8" fill="none" :stroke="c.ink" stroke-width="1.5" />

        <!-- ================= FRONT VIEW ================= -->
        <text x="20" y="28" font-size="14" font-weight="bold" :fill="c.ink">FRONT VIEW</text>
        <text x="20" y="44" font-size="10" :fill="c.muted">looking along the runway · all dimensions in mm</text>

        <g :stroke="c.ink" fill="none">
            <!-- floor -->
            <rect :x="f.X(f.wallL) - 20" :y="f.Y(0)" :width="f.X(f.wallR) - f.X(f.wallL) + 40" height="8" fill="url(#hatch)" stroke="none" />
            <line :x1="f.X(f.wallL) - 20" :y1="f.Y(0)" :x2="f.X(f.wallR) + 20" :y2="f.Y(0)" stroke-width="1.5" />

            <!-- walls -->
            <rect :x="f.X(f.wallL) - 10" :y="f.Y(f.roof)" width="10" :height="f.Y(0) - f.Y(f.roof)" fill="url(#hatch)" />
            <rect :x="f.X(f.wallR)" :y="f.Y(f.roof)" width="10" :height="f.Y(0) - f.Y(f.roof)" fill="url(#hatch)" />

            <!-- roof -->
            <line
                :x1="f.X(f.wallL) - 10"
                :y1="f.Y(f.roof)"
                :x2="f.X(f.wallR) + 10"
                :y2="f.Y(f.roof)"
                stroke-width="1.2"
                :stroke-dasharray="f.hasBuilding ? null : '6 4'"
            />

            <!-- rail centre lines -->
            <line :x1="f.X(0)" :y1="f.Y(f.roof) + 4" :x2="f.X(0)" :y2="f.Y(0)" :stroke="c.centre" stroke-width="0.5" stroke-dasharray="12 3 2 3" />
            <line :x1="f.X(v.span)" :y1="f.Y(f.roof) + 4" :x2="f.X(v.span)" :y2="f.Y(0)" :stroke="c.centre" stroke-width="0.5" stroke-dasharray="12 3 2 3" />

            <!-- runway corbels + rails (schematic) -->
            <rect :x="f.X(f.wallL)" :y="f.Y(v.rail_height - 30)" :width="f.X(250) - f.X(f.wallL)" :height="atLeast(450 * f.s, 4)" :fill="c.steel" />
            <rect :x="f.X(v.span - 250)" :y="f.Y(v.rail_height - 30)" :width="f.X(f.wallR) - f.X(v.span - 250)" :height="atLeast(450 * f.s, 4)" :fill="c.steel" />
            <rect :x="f.X(0) - 2" :y="f.Y(v.rail_height)" width="4" :height="atLeast(30 * f.s, 2)" :fill="c.ink" />
            <rect :x="f.X(v.span) - 2" :y="f.Y(v.rail_height)" width="4" :height="atLeast(30 * f.s, 2)" :fill="c.ink" />

            <!-- wheels -->
            <circle :cx="f.X(0)" :cy="f.Y(v.rail_height + v.wheel_diameter / 2)" :r="atLeast((v.wheel_diameter / 2) * f.s, 2.5)" :fill="c.bg" />
            <circle :cx="f.X(v.span)" :cy="f.Y(v.rail_height + v.wheel_diameter / 2)" :r="atLeast((v.wheel_diameter / 2) * f.s, 2.5)" :fill="c.bg" />

            <!-- end carriages -->
            <rect :x="f.X(-v.end_overhang)" :y="f.Y(f.gb + v.girder_depth * 0.5)" :width="atLeast(2 * v.end_overhang * f.s, 4)" :height="f.Y(v.rail_height + v.wheel_diameter * 0.3) - f.Y(f.gb + v.girder_depth * 0.5)" :fill="c.steelDark" />
            <rect :x="f.X(v.span - v.end_overhang)" :y="f.Y(f.gb + v.girder_depth * 0.5)" :width="atLeast(2 * v.end_overhang * f.s, 4)" :height="f.Y(v.rail_height + v.wheel_diameter * 0.3) - f.Y(f.gb + v.girder_depth * 0.5)" :fill="c.steelDark" />

            <!-- girder -->
            <rect :x="f.X(-v.end_overhang)" :y="f.Y(f.gt)" :width="f.X(v.span + v.end_overhang) - f.X(-v.end_overhang)" :height="f.Y(f.gb) - f.Y(f.gt)" :fill="c.girder" stroke-width="1.2" />
            <text :x="f.X(f.xc) - Math.min(120, (f.X(v.span) - f.X(0)) / 4)" :y="(f.Y(f.gb) + f.Y(f.gt)) / 2 + 5" font-size="13" font-weight="bold" :fill="c.ink" stroke="none" text-anchor="middle">{{ v.capacity }} t</text>

            <!-- hoist, ropes, hook (schematic) -->
            <rect :x="hook.body.x" :y="hook.body.y" :width="hook.body.w" :height="hook.body.h" :fill="c.steel" />
            <line :x1="hook.cx - 3" :y1="hook.ropeTop" :x2="hook.cx - 3" :y2="hook.ropeBottom" stroke-width="0.8" />
            <line :x1="hook.cx + 3" :y1="hook.ropeTop" :x2="hook.cx + 3" :y2="hook.ropeBottom" stroke-width="0.8" />
            <rect :x="hook.cx - hook.block.w / 2" :y="hook.block.y" :width="hook.block.w" :height="hook.block.h" :fill="c.steelDark" />
            <path :d="hook.path" stroke-width="2" />

            <!-- hook end positions -->
            <line :x1="f.X(v.hook_approach_left)" :y1="f.Y(f.gb)" :x2="f.X(v.hook_approach_left)" :y2="f.Y(v.lifting_height)" :stroke="c.centre" stroke-width="0.7" stroke-dasharray="4 3" />
            <line :x1="f.X(v.span - v.hook_approach_right)" :y1="f.Y(f.gb)" :x2="f.X(v.span - v.hook_approach_right)" :y2="f.Y(v.lifting_height)" :stroke="c.centre" stroke-width="0.7" stroke-dasharray="4 3" />

            <!-- pendant control -->
            <line :x1="f.X(pendantX)" :y1="f.Y(f.gb)" :x2="f.X(pendantX)" :y2="f.Y(v.pendant_above_floor + 400)" stroke-width="0.6" />
            <rect :x="f.X(pendantX) - 3" :y="f.Y(v.pendant_above_floor + 400)" width="6" :height="atLeast(400 * f.s, 8)" :fill="c.bg" />
        </g>

        <g font-size="10" :fill="c.muted">
            <text :x="f.X(f.wallL) + 4" :y="f.Y(0) - 5">Floor ±0</text>
            <text :x="f.X(f.xc)" :y="f.Y(f.roof) - 4" text-anchor="middle">
                {{ f.hasBuilding ? 'Roof (given)' : 'Roof: not given, min. required shown' }}
            </text>
            <text :x="f.X(pendantX) - 6" :y="f.Y(v.pendant_above_floor + 200)" text-anchor="end">pendant</text>
        </g>

        <!-- front horizontal dimensions -->
        <Dim :x1="f.X(f.wallL)" :y1="f.Y(f.roof)" :x2="f.X(-v.end_overhang)" :y2="f.Y(f.gt)" :at="f.Y(f.roof) - 22" :label="label('wall_clearance', 'Min.')" />
        <Dim :x1="f.X(-v.end_overhang)" :y1="f.Y(f.gt)" :x2="f.X(v.span + v.end_overhang)" :y2="f.Y(f.gt)" :at="f.Y(f.roof) - 22" :label="label('overall_length')" />
        <Dim :x1="f.X(v.span + v.end_overhang)" :y1="f.Y(f.gt)" :x2="f.X(f.wallR)" :y2="f.Y(f.roof)" :at="f.Y(f.roof) - 22" :label="label('wall_clearance', 'Min.')" />
        <Dim :x1="f.X(0)" :y1="f.Y(v.rail_height)" :x2="f.X(v.span)" :y2="f.Y(v.rail_height)" :at="f.Y(f.roof) - 50" :label="`Span ${label('span')}`" />
        <Dim :x1="f.X(f.wallL)" :y1="f.Y(f.roof)" :x2="f.X(f.wallR)" :y2="f.Y(f.roof)" :at="f.Y(f.roof) - 78" :label="`Min. building width ${label('min_building_width')}`" />

        <!-- hook approach -->
        <Dim :x1="f.X(0)" :y1="f.Y(v.lifting_height * 0.45)" :x2="f.X(v.hook_approach_left)" :y2="f.Y(v.lifting_height * 0.45)" :at="f.Y(v.lifting_height * 0.45)" :label="label('hook_approach_left', 'Min.')" />
        <Dim :x1="f.X(v.span - v.hook_approach_right)" :y1="f.Y(v.lifting_height * 0.45)" :x2="f.X(v.span)" :y2="f.Y(v.lifting_height * 0.45)" :at="f.Y(v.lifting_height * 0.45)" :label="label('hook_approach_right', 'Min.')" text-side="before" />

        <!-- front vertical dimensions, left chain -->
        <Dim orient="v" :x1="f.X(f.wallL) - 12" :y1="f.Y(0)" :x2="f.X(0)" :y2="f.Y(v.rail_height)" :at="f.X(f.wallL) - 34" :label="label('rail_height')" />
        <Dim orient="v" :x1="f.X(0)" :y1="f.Y(v.rail_height)" :x2="f.X(-v.end_overhang)" :y2="f.Y(f.gt)" :at="f.X(f.wallL) - 34" :label="label('crane_top_above_rail')" :estimate="isEstimate('crane_top_above_rail')" />
        <Dim orient="v" :x1="f.X(-v.end_overhang)" :y1="f.Y(f.gt)" :x2="f.X(f.wallL)" :y2="f.Y(f.roof)" :at="f.X(f.wallL) - 34" :label="label('roof_clearance', 'Min.')" />
        <Dim
            orient="v"
            :x1="f.X(f.wallL) - 12"
            :y1="f.Y(0)"
            :x2="f.X(f.wallL)"
            :y2="f.Y(f.roof)"
            :at="f.X(f.wallL) - 110"
            :label="f.hasBuilding ? label('building_height') : `Min. ${label('min_building_height')}`"
            :estimate="!f.hasBuilding && isEstimate('min_building_height')"
        />

        <!-- right chain: girder position -->
        <Dim orient="v" :x1="f.X(v.span)" :y1="f.Y(v.rail_height)" :x2="f.X(v.span + v.end_overhang)" :y2="f.Y(f.gb)" :at="f.X(f.wallR) + 34" :label="label('girder_bottom_above_rail')" text-side="right" :estimate="isEstimate('girder_bottom_above_rail')" />
        <Dim orient="v" :x1="f.X(v.span + v.end_overhang)" :y1="f.Y(f.gb)" :x2="f.X(v.span + v.end_overhang)" :y2="f.Y(f.gt)" :at="f.X(f.wallR) + 34" :label="label('girder_depth')" text-side="right" :estimate="isEstimate('girder_depth')" />

        <!-- hook heights -->
        <Dim orient="v" :x1="hook.cx" :y1="f.Y(0)" :x2="hook.cx" :y2="f.Y(v.lifting_height)" :at="f.X(f.xc + 1200)" :label="`Lift ${label('lifting_height')}`" />
        <Dim orient="v" :x1="hook.cx" :y1="f.Y(v.lifting_height)" :x2="hook.cx + hook.body.w / 2" :y2="f.Y(f.gb)" :at="f.X(f.xc + 1200)" :label="label('hook_headroom')" text-side="right" :estimate="isEstimate('hook_headroom')" />

        <!-- pendant height -->
        <Dim orient="v" :x1="f.X(pendantX)" :y1="f.Y(0)" :x2="f.X(pendantX)" :y2="f.Y(v.pendant_above_floor)" :at="f.X(pendantX) + 28" :label="label('pendant_above_floor')" />

        <!-- ================= PLAN VIEW ================= -->
        <line x1="4" :y1="p.top - 8" :x2="W - 4" :y2="p.top - 8" :stroke="c.steelDark" />
        <text x="20" :y="p.top + 14" font-size="14" font-weight="bold" :fill="c.ink">PLAN VIEW</text>
        <text x="20" :y="p.top + 30" font-size="10" :fill="c.muted">from above · same scale as front view</text>

        <g :stroke="c.ink" fill="none">
            <!-- runway rails -->
            <line :x1="f.X(0)" :y1="p.Y(-p.railLen / 2)" :x2="f.X(0)" :y2="p.Y(p.railLen / 2)" stroke-width="2" :stroke="c.centre" />
            <line :x1="f.X(v.span)" :y1="p.Y(-p.railLen / 2)" :x2="f.X(v.span)" :y2="p.Y(p.railLen / 2)" stroke-width="2" :stroke="c.centre" />

            <!-- girder (schematic width) -->
            <rect :x="f.X(v.end_overhang)" :y="p.Y(-300)" :width="f.X(v.span - v.end_overhang) - f.X(v.end_overhang)" :height="atLeast(600 * f.s, 4)" :fill="c.girder" />

            <!-- end carriages -->
            <rect :x="f.X(-v.end_overhang)" :y="p.Y(-v.end_carriage_length / 2)" :width="atLeast(2 * v.end_overhang * f.s, 4)" :height="v.end_carriage_length * f.s" :fill="c.steelDark" />
            <rect :x="f.X(v.span - v.end_overhang)" :y="p.Y(-v.end_carriage_length / 2)" :width="atLeast(2 * v.end_overhang * f.s, 4)" :height="v.end_carriage_length * f.s" :fill="c.steelDark" />

            <!-- wheels -->
            <template v-for="x in [0, v.span]" :key="x">
                <rect
                    v-for="y in [-v.wheelbase / 2, v.wheelbase / 2]"
                    :key="y"
                    :x="f.X(x) - 2"
                    :y="p.Y(y) - atLeast((v.wheel_diameter / 2) * f.s, 3)"
                    width="4"
                    :height="2 * atLeast((v.wheel_diameter / 2) * f.s, 3)"
                    :fill="c.ink"
                />
            </template>

            <!-- trolley / hoist (schematic) -->
            <rect :x="f.X(f.xc - 650)" :y="p.Y(-700)" :width="1300 * f.s" :height="1400 * f.s" :fill="c.steel" />
        </g>

        <text :x="f.X(0) + 6" :y="p.Y(-p.railLen / 2) + 10" font-size="10" :fill="c.muted">runway rail</text>

        <!-- plan dimensions -->
        <Dim :x1="f.X(0)" :y1="p.Y(p.railLen / 2)" :x2="f.X(v.span)" :y2="p.Y(p.railLen / 2)" :at="p.Y(p.railLen / 2) + 28" :label="`Span ${label('span')}`" />
        <Dim :x1="f.X(-v.end_overhang)" :y1="p.Y(v.end_carriage_length / 2)" :x2="f.X(v.span + v.end_overhang)" :y2="p.Y(v.end_carriage_length / 2)" :at="p.Y(p.railLen / 2) + 58" :label="`Overall ${label('overall_length')}`" />
        <Dim orient="v" :x1="f.X(v.span)" :y1="p.Y(-v.wheelbase / 2)" :x2="f.X(v.span)" :y2="p.Y(v.wheelbase / 2)" :at="f.X(f.wallR) + 34" :label="label('wheelbase')" :estimate="isEstimate('wheelbase')" />
        <Dim orient="v" :x1="f.X(v.span + v.end_overhang)" :y1="p.Y(-v.end_carriage_length / 2)" :x2="f.X(v.span + v.end_overhang)" :y2="p.Y(v.end_carriage_length / 2)" :at="f.X(f.wallR) + 66" :label="label('end_carriage_length')" :estimate="isEstimate('end_carriage_length')" />

        <!-- ================= TITLE BLOCK ================= -->
        <g :transform="`translate(4 ${titleTop})`" font-size="11" :fill="c.ink">
            <rect x="0" y="0" :width="W - 8" height="82" :fill="c.panel" :stroke="c.ink" />
            <line x1="380" y1="0" x2="380" y2="82" :stroke="c.ink" />
            <line x1="760" y1="0" x2="760" y2="82" :stroke="c.ink" />

            <text x="12" y="20" font-size="10" :fill="c.muted">DESIGN</text>
            <text x="12" y="40" font-size="15" font-weight="bold">{{ name || 'Unsaved design' }}</text>
            <text x="12" y="62">Single-girder top-running EOT crane</text>

            <text x="392" y="20">Capacity: {{ v.capacity }} t · Span: {{ label('span') }} mm</text>
            <text x="392" y="38">Lifting height: {{ label('lifting_height') }} mm · Duty: FEM {{ dutyGroup }}</text>
            <text x="392" y="56">Units: mm · Scale: NTS · Date: {{ today }}</text>
            <text x="392" y="74" font-size="10" :fill="c.muted">Hoist, corbel and flange shapes are schematic.</text>

            <text x="772" y="20" font-weight="bold" :fill="c.danger">PRELIMINARY · NOT FOR CONSTRUCTION</text>
            <text x="772" y="40" :fill="c.dim">Blue: input, reference drawing or exact geometry</text>
            <text x="772" y="58" :fill="c.est">Amber ≈: my estimate, not verified</text>
            <text x="772" y="74" font-size="10" :fill="c.muted">Sources for every value are in the table.</text>
        </g>
    </svg>
</template>
