<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        :fill="fill"
        :stroke="stroke"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        style="filter: saturate(1.4)"
        v-html="path"
    />
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    name: { type: String, required: true },
    size: { type: Number, default: 18 },
    stroke: { type: String, default: 'currentColor' },
    fill: { type: String, default: 'none' },
})

const ICONS = {
    plus:       '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    import:     '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    export:     '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
    save:       '<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
    share:      '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
    globe:      '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 010 20 15.3 15.3 0 010-20z"/>',
    history:    '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    help:       '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 115.8 1c0 2-3 2-3 4"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
    'value-type': '<path d="M4 5h16"/><path d="M9 5v14"/><path d="M5 19h8"/><circle cx="17" cy="14" r="3"/><path d="M17 11v6"/>',
    database:    '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>',
    reference:   '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/><path d="M11 7.5h2.5A3.5 3.5 0 0117 11v2"/><path d="M13 16.5h-2.5A3.5 3.5 0 017 13v-2"/><path d="M6.5 6.5h2"/><path d="M15.5 15.5h2"/>',
    interface:   '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/><path d="M11 7.5h3.5A2.5 2.5 0 0117 10v3"/><path d="M13 16.5H9.5A2.5 2.5 0 017 14v-3"/>',
    bolt:        '<path d="M13 2L3 14h8l-1 8 11-14h-8l0-6z"/>',
    pipe:        '<path d="M4 7h6a4 4 0 014 4v2a4 4 0 004 4h2"/><path d="M4 17h6a4 4 0 004-4v-2a4 4 0 014-4h2"/><circle cx="4" cy="7" r="2"/><circle cx="4" cy="17" r="2"/><circle cx="20" cy="7" r="2"/><circle cx="20" cy="17" r="2"/>',
    'pipe-plus': '<path d="M4 7h6a4 4 0 014 4v1"/><path d="M4 17h6a4 4 0 004-4v-1"/><circle cx="4" cy="7" r="2"/><circle cx="4" cy="17" r="2"/><path d="M18 11v8"/><path d="M14 15h8"/>',
    'pipe-json': '<path d="M4 6h5a4 4 0 014 4"/><path d="M4 18h5a4 4 0 004-4"/><circle cx="4" cy="6" r="2"/><circle cx="4" cy="18" r="2"/><path d="M15 8l-2 4 2 4"/><path d="M19 8l2 4-2 4"/>',
    trash:      '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>',
    copy:       '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>',
    gear:       '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
    eye:        '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    'eye-off':  '<path d="M17.94 17.94A10.94 10.94 0 0112 20C5 20 1 12 1 12a20.29 20.29 0 015.06-5.94"/><path d="M9.9 4.24A10.45 10.45 0 0112 4c7 0 11 8 11 8a20.16 20.16 0 01-3.23 4.31"/><path d="M14.12 14.12A3 3 0 019.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/>',
    logout:     '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    'table-list': '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/>',
    chat:       '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
    drag:       '<circle cx="9" cy="5" r="1.5" fill="currentColor"/><circle cx="15" cy="5" r="1.5" fill="currentColor"/><circle cx="9" cy="12" r="1.5" fill="currentColor"/><circle cx="15" cy="12" r="1.5" fill="currentColor"/><circle cx="9" cy="19" r="1.5" fill="currentColor"/><circle cx="15" cy="19" r="1.5" fill="currentColor"/>',
    close:      '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    list:       '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
    download:   '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    warning:    '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    edit:       '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L8 18l-4 1 1-4z"/>',
    note:       '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/>',
    folder:     '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>',
    file:       '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>',
    'chevron-right': '<polyline points="9 18 15 12 9 6"/>',
    'chevron-down':  '<polyline points="6 9 12 15 18 9"/>',
    search:     '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    refresh:    '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>',
    sparkles:   '<path d="M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9z"/><path d="M19 14l.7 2L22 16.5l-2.3.5L19 19l-.7-2L16 16.5l2.3-.5z"/>',
}

const path = computed(() => ICONS[props.name] ?? '')
</script>
