<template>
    <aside :class="['right-sidebar', { 'is-open': open }]" aria-label="Diagram side panel">
        <button class="right-sidebar__toggle" type="button" @click="$emit('toggle')" title="Ontology and changelog">
            <SvgIcon name="list" :size="18" />
        </button>

        <template v-if="open">
            <div class="right-sidebar__header">
                <div>
                    <h2>Diagram Details</h2>
                    <p>Ontology metadata and activity</p>
                </div>
                <button class="right-sidebar__refresh" type="button" @click="loadEntries" title="Refresh changelog">
                    <SvgIcon name="history" :size="15" />
                </button>
            </div>

            <div class="right-sidebar__body">
                <template v-if="isOntology">
                    <input
                        v-model="search"
                        class="right-sidebar__search"
                        type="search"
                        placeholder="Search ontology metadata"
                        aria-label="Search ontology metadata"
                    />

                    <section v-for="section in metadataSections" :key="section.key" class="metadata-section">
                        <h3>{{ section.label }} <span>{{ section.items.length }}</span></h3>
                        <div v-if="section.items.length" class="metadata-list">
                            <button
                                v-for="item in section.items"
                                :key="item.key"
                                type="button"
                                class="metadata-item"
                                @dblclick.stop="openMetadataItem(section.key, item.raw)"
                                @keydown.enter.prevent="openMetadataItem(section.key, item.raw)"
                            >
                                <strong>{{ item.title }}</strong>
                                <span>{{ item.subtitle }}</span>
                            </button>
                        </div>
                        <p v-else class="metadata-empty">No matches</p>
                    </section>
                </template>

                <section class="changelog-section">
                    <div class="changelog-section__heading">
                        <h3>Changelog</h3>
                    </div>
                </section>

                <div v-if="loading" class="right-sidebar__status">Loading...</div>
                <div v-else-if="!entries.length" class="right-sidebar__status">No actions recorded yet.</div>
                <ul v-else class="right-sidebar__list">
                    <li v-for="entry in entries" :key="entry.id" class="changelog-entry">
                        <span class="changelog-entry__avatar" :style="{ background: colorFor(entry.user_id) }">
                            {{ initials(entry.user_name) }}
                        </span>
                        <div class="changelog-entry__body">
                            <span class="changelog-entry__user">{{ entry.user_name }}</span>
                            <span class="changelog-entry__action">
                                <span class="changelog-entry__dot" :style="{ background: actionColor(entry.action) }"></span>
                                <span :style="{ color: actionColor(entry.action) }">{{ labelFor(entry.action, entry.details) }}</span>
                            </span>
                        </div>
                        <span class="changelog-entry__time" :title="fullDate(entry.created_at)">{{ relativeTime(entry.created_at) }}</span>
                    </li>
                </ul>
            </div>
        </template>
    </aside>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Diagram } from '@/services/Diagram.js'
import { CURSOR_COLORS } from '@/composables/useDiagramPresence.js'
import SvgIcon from '../SvgIcon.vue'

const props = defineProps({
    diagramId: { type: Number, default: null },
    open: { type: Boolean, default: false },
    refreshKey: { type: Number, default: 0 },
    dbType: { type: String, default: 'mysql' },
    valueTypes: { type: Array, default: () => [] },
    sharedPropertyTypes: { type: Array, default: () => [] },
    interfaces: { type: Array, default: () => [] },
    interfaceLinkConstraints: { type: Array, default: () => [] },
    customActions: { type: Array, default: () => [] },
})
const emit = defineEmits(['toggle', 'open-value-type', 'open-shared-property-type', 'open-interface', 'open-interface-link-constraint', 'open-custom-action'])

const entries = ref([])
const loading = ref(false)
const search = ref('')
const isOntology = computed(() => props.dbType === 'ontology')

const metadataTitle = (item) => item?.displayName || item?.apiName || item?.name || item?.id || 'Unnamed'
const metadataKey = (item, index) => item?.id || item?.apiName || item?.displayName || `${index}`
const metadataText = (item) => [item?.apiName, item?.displayName, item?.description, item?.id]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()

const normalizeItems = (items, subtitle, query) => (items ?? [])
    .map((item, index) => ({
        key: metadataKey(item, index),
        title: metadataTitle(item),
        subtitle: typeof subtitle === 'function' ? subtitle(item) : subtitle,
        raw: item,
        searchText: metadataText(item),
    }))
    .filter(item => !query || item.searchText.includes(query))

const metadataSections = computed(() => {
    const query = search.value.trim().toLowerCase()
    return [
        {
            key: 'valueTypes',
            label: 'Value Types',
            items: normalizeItems(props.valueTypes, item => item?.baseType?.type || 'value type', query),
        },
        {
            key: 'sharedPropertyTypes',
            label: 'Shared Property Types',
            items: normalizeItems(props.sharedPropertyTypes, item => item?.type || 'shared property', query),
        },
        {
            key: 'interfaces',
            label: 'Interfaces',
            items: normalizeItems(props.interfaces, item => `${item?.properties?.length ?? 0} properties`, query),
        },
        {
            key: 'interfaceLinkConstraints',
            label: 'Interface Link Constraints',
            items: normalizeItems(props.interfaceLinkConstraints, item => item?.from ? `from ${item.from}` : 'constraint', query),
        },
        {
            key: 'customActions',
            label: 'Custom Actions',
            items: normalizeItems(props.customActions, item => item?.actionType || (item?.function ? 'function-backed' : 'action'), query),
        },
    ]
})

const openMetadataItem = (section, item) => {
    if (section === 'valueTypes') emit('open-value-type', item)
    if (section === 'sharedPropertyTypes') emit('open-shared-property-type', item)
    if (section === 'interfaces') emit('open-interface', item)
    if (section === 'interfaceLinkConstraints') emit('open-interface-link-constraint', item)
    if (section === 'customActions') emit('open-custom-action', item)
}

const loadEntries = async () => {
    if (!props.diagramId) return
    loading.value = true
    const data = await Diagram.getChangelog(props.diagramId)
    entries.value = data ?? []
    loading.value = false
}

watch(() => [props.open, props.diagramId], ([open]) => {
    if (open) loadEntries()
}, { immediate: true })

watch(() => props.refreshKey, () => {
    if (props.open) loadEntries()
})

const metadataSummary = (label, details) => {
    const parts = []
    const addPart = (verb, names, count) => {
        const list = Array.isArray(names) ? names : []
        const total = Number.isInteger(count) ? count : list.length
        if (total <= 0) return
        const visible = list.slice(0, 2).join(', ')
        const suffix = total > 2 ? ` +${total - 2} more` : ''
        parts.push(`${verb} ${visible || total}${suffix}`)
    }
    addPart('added', details?.added, details?.added_count)
    addPart('updated', details?.updated, details?.updated_count)
    addPart('removed', details?.removed, details?.removed_count)
    return parts.length ? `Changed ${label}: ${parts.join('; ')}` : `Changed ${label}`
}

const ACTION_LABELS = {
    table_created: (d) => `Created table${d?.table_name ? ` "${d.table_name}"` : ''}`,
    table_copied: (d) => `Copied table${d?.table_name ? ` "${d.table_name}"` : ''}`,
    table_deleted: (d) => `Deleted table${d?.table_name ? ` "${d.table_name}"` : ''}`,
    connection_created: (d) => {
        if (d?.from_table && d?.from_column && d?.to_table && d?.to_column)
            return `Connected ${d.from_table}.${d.from_column} -> ${d.to_table}.${d.to_column}`
        if (d?.from_table && d?.to_table) return `Connected "${d.from_table}" -> "${d.to_table}"`
        return 'Created connection'
    },
    connection_deleted: (d) => {
        if (d?.from_table && d?.from_column && d?.to_table && d?.to_column)
            return `Removed ${d.from_table}.${d.from_column} -> ${d.to_table}.${d.to_column}`
        if (d?.from_table && d?.to_table) return `Removed "${d.from_table}" -> "${d.to_table}"`
        return 'Deleted connection'
    },
    relationship_changed: (d) => {
        const type = d?.type ? ` to ${d.type}` : ''
        if (d?.from_table && d?.from_column && d?.to_table && d?.to_column)
            return `Changed ${d.from_table}.${d.from_column} -> ${d.to_table}.${d.to_column}${type}`
        return `Changed relationship${type}`
    },
    reference_table_created: (d) => `Created reference table${d?.table_name ? ` "${d.table_name}"` : ''}`,
    reference_table_imported: (d) => d?.tables?.length ? `Imported reference ${d.tables.join(', ')}` : 'Imported reference tables',
    reference_table_deleted: (d) => `Deleted reference table${d?.table_name ? ` "${d.table_name}"` : ''}`,
    reference_link_created: (d) => {
        if (d?.from_table && d?.from_column && d?.to_table && d?.to_column)
            return `Linked reference ${d.from_table}.${d.from_column} -> ${d.to_table}.${d.to_column}`
        return 'Created reference link'
    },
    reference_link_deleted: (d) => {
        if (d?.from_table && d?.from_column && d?.to_table && d?.to_column)
            return `Removed reference ${d.from_table}.${d.from_column} -> ${d.to_table}.${d.to_column}`
        return 'Deleted reference link'
    },
    pipeline_transform_created: (d) => `Created pipeline transform${d?.inputs || d?.outputs ? ` (${d?.inputs ?? 0} refs -> ${d?.outputs ?? 0} rows)` : ''}`,
    pipeline_transform_deleted: () => 'Deleted pipeline transform',
    pipeline_transform_changed: () => 'Changed pipeline transform',
    column_added: (d) => d?.table_name ? `Added column to "${d.table_name}"` : 'Added column',
    column_deleted: (d) => {
        if (d?.column_name && d?.table_name) return `Deleted column "${d.column_name}" from "${d.table_name}"`
        if (d?.table_name) return `Deleted column from "${d.table_name}"`
        return 'Deleted column'
    },
    value_types_changed: (d) => metadataSummary('value types', d),
    shared_property_types_changed: (d) => metadataSummary('shared property types', d),
    interfaces_changed: (d) => metadataSummary('interfaces', d),
    interface_link_constraints_changed: (d) => metadataSummary('interface link constraints', d),
    custom_actions_changed: (d) => metadataSummary('custom actions', d),
    import_sql: () => 'Imported SQL',
    export_sql: () => 'Exported SQL',
}

const ACTION_COLORS = {
    table_created: '#22c55e',
    table_copied: '#22c55e',
    column_added: '#22c55e',
    connection_created: '#22c55e',
    table_deleted: '#ef4444',
    column_deleted: '#ef4444',
    connection_deleted: '#ef4444',
    relationship_changed: '#f97316',
    reference_table_created: '#8b5cf6',
    reference_table_imported: '#8b5cf6',
    reference_table_deleted: '#ef4444',
    reference_link_created: '#8b5cf6',
    reference_link_deleted: '#ef4444',
    pipeline_transform_created: '#f59e0b',
    pipeline_transform_deleted: '#ef4444',
    pipeline_transform_changed: '#f59e0b',
    value_types_changed: '#a78bfa',
    shared_property_types_changed: '#38bdf8',
    interfaces_changed: '#facc15',
    interface_link_constraints_changed: '#fb923c',
    custom_actions_changed: '#f472b6',
    import_sql: '#3b82f6',
    export_sql: '#3b82f6',
}

const labelFor = (action, details) => {
    const fn = ACTION_LABELS[action]
    return fn ? fn(details) : action.replace(/_/g, ' ')
}

const actionColor = (action) => ACTION_COLORS[action] ?? '#6b7280'

const colorFor = (userId) => {
    if (!userId) return '#6b7280'
    return CURSOR_COLORS[userId % CURSOR_COLORS.length]
}

const initials = (name) => {
    if (!name) return '?'
    const parts = name.split('@')[0].split(/[._-]/)
    return parts.slice(0, 2).map(p => p[0]?.toUpperCase() ?? '').join('') || name[0].toUpperCase()
}

const relativeTime = (dateStr) => {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000)
    if (diff < 60) return 'just now'
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`
    return new Date(dateStr).toLocaleDateString()
}

const fullDate = (dateStr) => new Date(dateStr).toLocaleString()
</script>

<style scoped>
.right-sidebar {
    width: 48px;
    flex: 0 0 48px;
    min-height: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 12px 8px;
    border-left: 1px solid var(--border-color);
    background: var(--bg-surface);
}

.right-sidebar.is-open {
    width: 340px;
    flex-basis: 340px;
    align-items: stretch;
    padding: 12px;
}

.right-sidebar__toggle,
.right-sidebar__refresh {
    width: 32px;
    height: 32px;
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-surface);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.right-sidebar__toggle:hover,
.right-sidebar__refresh:hover {
    background: var(--hover-bg-alt);
}

.right-sidebar__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
}

.right-sidebar__header h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.right-sidebar__header p {
    margin: 2px 0 0;
    color: var(--text-muted);
    font-size: 0.72rem;
}

.right-sidebar__body {
    min-height: 0;
    overflow-y: auto;
    flex: 1;
}

.right-sidebar__search {
    width: 100%;
    height: 32px;
    padding: 0 10px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 13px;
    margin-bottom: 12px;
}

.right-sidebar__search:focus {
    outline: none;
    border-color: var(--border-strong);
}

.metadata-section,
.changelog-section {
    margin-bottom: 14px;
}

.metadata-section h3,
.changelog-section h3 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 0 6px;
    color: var(--text-secondary);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.metadata-section h3 span {
    color: var(--text-muted);
    font-weight: 600;
}

.metadata-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.metadata-item {
    width: 100%;
    min-height: 36px;
    padding: 7px 8px;
    border: 1px solid transparent;
    border-radius: 5px;
    background: none;
    color: var(--text-primary);
    text-align: left;
    cursor: pointer;
}

.metadata-item:hover,
.metadata-item:focus {
    outline: none;
    background: var(--hover-bg-alt);
    border-color: var(--border-color);
}

.metadata-item strong,
.metadata-item span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.metadata-item strong {
    font-size: 0.78rem;
}

.metadata-item span,
.metadata-empty {
    color: var(--text-muted);
    font-size: 0.7rem;
}

.metadata-empty {
    margin: 0 0 4px;
    padding: 5px 8px;
}

.changelog-section {
    padding-top: 4px;
    border-top: 1px solid var(--border-color);
}

.right-sidebar__status {
    padding: 24px 8px;
    text-align: center;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.right-sidebar__list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.changelog-entry {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 4px;
    border-bottom: 1px solid color-mix(in srgb, var(--border-color) 55%, transparent);
}

.changelog-entry__avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    margin-top: 1px;
}

.changelog-entry__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.changelog-entry__user {
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.changelog-entry__action {
    font-size: 0.74rem;
    line-height: 1.35;
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.changelog-entry__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 0.36rem;
}

.changelog-entry__time {
    font-size: 0.68rem;
    color: var(--text-muted);
    white-space: nowrap;
    flex-shrink: 0;
    margin-top: 2px;
}

@media (max-width: 860px) {
    .right-sidebar.is-open {
        position: absolute;
        top: 48px;
        right: 0;
        bottom: 0;
        z-index: 45;
        width: min(340px, calc(100vw - 48px));
        box-shadow: -12px 0 30px rgba(0, 0, 0, 0.28);
    }
}
</style>
