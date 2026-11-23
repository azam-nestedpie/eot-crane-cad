<script setup>
/**
 * One dimension: two extension lines, a dimension line with arrows, and a label.
 * Works in screen pixels. (x1,y1) and (x2,y2) are the measured points; `at`
 * is where the dimension line sits (a y for horizontal, an x for vertical).
 * Estimated values are drawn in amber so they never look exact.
 */
import { computed, inject } from 'vue';
import { palettes } from './palettes.js';

const palette = inject('palette', computed(() => palettes.paper));

const props = defineProps({
    x1: { type: Number, required: true },
    y1: { type: Number, required: true },
    x2: { type: Number, required: true },
    y2: { type: Number, required: true },
    at: { type: Number, required: true },
    orient: { type: String, default: 'h' },
    label: { type: String, required: true },
    estimate: { type: Boolean, default: false },
    /** Where the label goes when it does not fit between the arrows: 'after'/'before' (horizontal), 'left'/'right' (vertical). */
    textSide: { type: String, default: null },
});

const ARROW = 7;
const GAP = 3;
const OVERSHOOT = 4;

const color = computed(() => (props.estimate ? palette.value.est : palette.value.dim));
const textWidth = computed(() => props.label.length * 6.4);

function extensionLine(fromX, fromY, toX, toY) {
    const length = Math.hypot(toX - fromX, toY - fromY);
    if (length < GAP) {
        return null;
    }
    const ux = (toX - fromX) / length;
    const uy = (toY - fromY) / length;
    return [fromX + ux * GAP, fromY + uy * GAP, toX + ux * OVERSHOOT, toY + uy * OVERSHOOT];
}

function arrow(tipX, tipY, dirX, dirY) {
    const px = -dirY * 2.5;
    const py = dirX * 2.5;
    const bx = tipX + dirX * ARROW;
    const by = tipY + dirY * ARROW;
    return `${tipX},${tipY} ${bx + px},${by + py} ${bx - px},${by - py}`;
}

const geometry = computed(() => {
    const p = props;

    if (p.orient === 'h') {
        const left = Math.min(p.x1, p.x2);
        const right = Math.max(p.x1, p.x2);
        const fits = right - left > textWidth.value + 2 * ARROW + 4;
        return {
            line: [left, p.at, right, p.at],
            extensions: [extensionLine(p.x1, p.y1, p.x1, p.at), extensionLine(p.x2, p.y2, p.x2, p.at)].filter(Boolean),
            arrows: [arrow(left, p.at, 1, 0), arrow(right, p.at, -1, 0)],
            text: fits
                ? { x: (left + right) / 2, y: p.at - 4, anchor: 'middle', rotate: null }
                : p.textSide === 'before'
                  ? { x: left - 5, y: p.at - 4, anchor: 'end', rotate: null }
                  : { x: right + 5, y: p.at - 4, anchor: 'start', rotate: null },
        };
    }

    const top = Math.min(p.y1, p.y2);
    const bottom = Math.max(p.y1, p.y2);
    const fits = bottom - top > textWidth.value + 2 * ARROW + 4;
    const mid = (top + bottom) / 2;
    let text;
    if (fits) {
        text = { x: p.at - 4, y: mid, anchor: 'middle', rotate: `rotate(-90 ${p.at - 4} ${mid})` };
    } else if (p.textSide === 'right') {
        text = { x: p.at + 5, y: mid + 4, anchor: 'start', rotate: null };
    } else {
        text = { x: p.at - 5, y: mid + 4, anchor: 'end', rotate: null };
    }
    return {
        line: [p.at, top, p.at, bottom],
        extensions: [extensionLine(p.x1, p.y1, p.at, p.y1), extensionLine(p.x2, p.y2, p.at, p.y2)].filter(Boolean),
        arrows: [arrow(p.at, top, 0, 1), arrow(p.at, bottom, 0, -1)],
        text,
    };
});
</script>

<template>
    <g>
        <line
            v-for="(e, i) in geometry.extensions"
            :key="i"
            :x1="e[0]"
            :y1="e[1]"
            :x2="e[2]"
            :y2="e[3]"
            :stroke="color"
            stroke-width="0.6"
        />
        <line
            :x1="geometry.line[0]"
            :y1="geometry.line[1]"
            :x2="geometry.line[2]"
            :y2="geometry.line[3]"
            :stroke="color"
            stroke-width="0.8"
        />
        <polygon v-for="(a, i) in geometry.arrows" :key="'a' + i" :points="a" :fill="color" />
        <text
            :x="geometry.text.x"
            :y="geometry.text.y"
            :text-anchor="geometry.text.anchor"
            :transform="geometry.text.rotate"
            :fill="color"
            font-size="11"
            font-family="Helvetica, Arial, sans-serif"
        >
            {{ label }}
        </text>
    </g>
</template>
