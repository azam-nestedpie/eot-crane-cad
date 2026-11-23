<script setup>
/** Tiny inline stroke icon set (24×24), so no icon library is needed. */
const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 18 },
});

const paths = {
    plus: 'M12 5v14M5 12h14',
    save: 'M5 3h11l3 3v15H5zM8 3v5h7V3M8 21v-7h8v7',
    download: 'M12 3v12M7 10l5 5 5-5M5 21h14',
    trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3',
    search: 'M11 4a7 7 0 1 0 0 14a7 7 0 1 0 0-14M21 21l-5-5',
    zoomIn: 'M11 4a7 7 0 1 0 0 14a7 7 0 1 0 0-14M21 21l-5-5M11 8v6M8 11h6',
    zoomOut: 'M11 4a7 7 0 1 0 0 14a7 7 0 1 0 0-14M21 21l-5-5M8 11h6',
    fit: 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5',
    alert: 'M12 3l10 18H2zM12 10v4M12 17.5v.5',
    check: 'M5 12l5 5L20 7',
    x: 'M6 6l12 12M18 6L6 18',
    crane: 'M3 21h18M6 21V4M6 4h15M6 4L3 7M6 8l4-4M17 4v7M15 11h4M17 11v2a1.5 1.5 0 1 1-1.5 1.5',
    ruler: 'M3 17L17 3l4 4L7 21zM7 13l2 2M10 10l2 2M13 7l2 2',
    table: 'M4 4h16v16H4zM4 10h16M4 15h16M10 10v10',
    sheet: 'M6 3h9l4 4v14H6zM15 3v4h4',
    sun: 'M12 8a4 4 0 1 0 0 8a4 4 0 1 0 0-8M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
    grid: 'M4 4h16v16H4zM4 12h16M12 4v16',
    info: 'M12 3a9 9 0 1 0 0 18a9 9 0 1 0 0-18M12 11v6M12 7.5v.5',
    keyboard: 'M3 6h18v12H3zM7 10h.01M11 10h.01M15 10h.01M7 14h10',
};
</script>

<template>
    <svg
        :width="props.size"
        :height="props.size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path :d="paths[props.name]" />
    </svg>
</template>
