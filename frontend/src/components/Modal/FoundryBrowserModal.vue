<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal-card foundry-browser">
            <div class="modal-header">
                <span class="modal-title">Foundry Browser</span>
                <div class="fb-head-actions">
                    <button v-if="canEdit && isConnected" type="button" class="fb-btn fb-btn--ghost" title="Re-sync all Foundry-linked tables" @click="$emit('sync')">
                        <SvgIcon name="refresh" :size="14" /> Sync linked
                    </button>
                    <button class="modal-close" type="button" title="Refresh" aria-label="Refresh" @click="reloadAll">
                        <SvgIcon name="refresh" :size="15" />
                    </button>
                    <button class="modal-close" type="button" aria-label="Close" @click="$emit('close')">
                        <SvgIcon name="close" :size="16" />
                    </button>
                </div>
            </div>

            <div class="foundry-browser__body">
                <p v-if="!diagramId" class="fb-empty">Save the diagram first to browse Foundry.</p>

                <div v-else-if="loading" class="fb-empty">Loading…</div>

                <div v-else-if="!isConnected" class="fb-notice">
                    <p>{{ stateMessage || 'Not connected to Foundry.' }}</p>
                    <p class="fb-notice__hint">Open <strong>Diagram Details</strong> in the right sidebar to set a host and connect, then reopen this browser.</p>
                </div>

                <template v-else>
                    <!-- Ontology dropdown -->
                    <div class="fb-ontology">
                        <label for="fb-ontology-select">Ontology</label>
                        <select id="fb-ontology-select" v-model="selectedOntologyRid" class="fb-select" @change="onOntologyChange">
                            <option v-if="!ontologyOptions.length" :value="''" disabled>{{ ontologiesLoading ? 'Loading…' : 'No ontologies' }}</option>
                            <option v-for="o in ontologyOptions" :key="o.rid" :value="o.rid">
                                {{ o.displayName || o.apiName || o.rid }}{{ o.rid === defaults.ontology ? ' — default' : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Path bar -->
                    <div class="fb-pathbar">
                        <template v-for="(crumb, i) in path" :key="i">
                            <button type="button" class="fb-crumb" @click="onCrumb(crumb)">{{ crumb.name }}</button>
                            <SvgIcon v-if="i < path.length - 1" name="chevron-right" :size="11" class="fb-pathsep" />
                        </template>
                    </div>

                    <!-- Tree | List -->
                    <div class="fb-split">
                        <div class="fb-tree">
                            <div v-if="!tree.length" class="fb-empty fb-empty--pad">No spaces.</div>
                            <FoundryTreeNode
                                v-for="node in tree"
                                :key="node.id"
                                :node="node"
                                :selected-id="selectedNode ? selectedNode.id : null"
                                :depth="0"
                                @select="selectNode"
                                @toggle="toggleNode"
                            />
                        </div>

                        <div class="fb-list">
                            <div class="fb-list__bar">
                                <span class="fb-search">
                                    <SvgIcon name="search" :size="13" class="fb-search__icon" />
                                    <input v-model="search" type="text" placeholder="Search this folder" />
                                </span>
                                <button
                                    v-if="canEdit && selectedNode && selectedNode.kind === 'dataset'"
                                    type="button"
                                    class="fb-mini fb-mini--primary"
                                    :disabled="importingRid === selectedNode.rid"
                                    @click="importDataset({ rid: selectedNode.rid, name: selectedNode.name })"
                                >
                                    {{ importingRid === selectedNode.rid ? '…' : 'Import' }}
                                </button>
                                <button
                                    v-if="canManageHost && isFolderLike(selectedNode)"
                                    type="button"
                                    class="fb-mini"
                                    @click="setDefaultFolder({ rid: selectedNode.rid, type: selectedNode.type })"
                                >
                                    Set default
                                </button>
                            </div>

                            <div class="fb-list__scroll">
                                <div v-if="itemsLoading" class="fb-empty fb-empty--pad">Loading…</div>
                                <ul v-else-if="filteredItems.length" class="fb-rows">
                                    <li v-for="row in filteredItems" :key="row.id" class="fb-row" :title="row.rid || row.path || row.name">
                                        <span class="fb-row__icon" :class="`fb-ticon--${row.colorKind}`"><SvgIcon :name="row.icon" :size="15" /></span>
                                        <button type="button" class="fb-row__main" :class="{ 'is-leaf': !row.openable }" :disabled="!row.openable" @click="openRow(row)">
                                            <strong>{{ row.name }}</strong>
                                            <span>{{ row.sub }}</span>
                                        </button>
                                        <div class="fb-row__actions">
                                            <button v-if="canEdit && row.kind === 'dataset'" type="button" class="fb-mini fb-mini--primary" :disabled="importingRid === row.rid" @click="importDataset(row)">
                                                {{ importingRid === row.rid ? '…' : 'Import' }}
                                            </button>
                                            <button v-if="canManageHost && (row.kind === 'folder' || row.kind === 'project') && row.rid" type="button" class="fb-mini" @click="setDefaultFolder(row)">Set</button>
                                        </div>
                                    </li>
                                </ul>
                                <p v-else class="fb-empty fb-empty--pad">{{ search ? 'No matches.' : 'Empty.' }}</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toast-notification'
import { Foundry, foundryErrorMessage } from '@/services/Foundry.js'
import { datasetSchemaToJsonSchema } from '@/services/foundryImport.js'
import FoundryTreeNode from './FoundryTreeNode.vue'
import SvgIcon from '../SvgIcon.vue'

const props = defineProps({
    diagramId: { type: Number, default: null },
    canManageHost: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'import-reference', 'sync'])

const $toast = useToast()

const loading = ref(false)
const status = ref(null)
const defaults = ref({ project: null, folder: null, ontology: null })

const ontologies = ref([])
const ontologiesLoading = ref(false)
const selectedOntologyRid = ref('')

const tree = ref([])
const rootLoaded = ref(false)
const rootRows = ref([])
const selectedNode = ref(null) // null = root (Spaces)
const items = ref([])
const itemsLoading = ref(false)
const search = ref('')
const importingRid = ref(null)

const isConnected = computed(() => status.value?.state === 'connected')

const stateMessage = computed(() => {
    switch (status.value?.state) {
        case 'expired': return 'Your Foundry connection expired. Reconnect in the sidebar.'
        case 'disconnected': return 'Connect your Foundry account to browse.'
        case 'host_not_configured': return 'This host needs administrator OAuth setup before anyone can connect.'
        case 'host_not_set': return 'No Foundry host is set for this diagram.'
        default: return ''
    }
})

const ontologyOptions = computed(() => {
    const list = [...ontologies.value]
    list.sort((a, b) => {
        if (a.rid === defaults.value.ontology) return -1
        if (b.rid === defaults.value.ontology) return 1
        return (a.displayName || a.apiName || '').localeCompare(b.displayName || b.apiName || '')
    })
    return list
})

const path = computed(() => {
    const crumbs = [{ name: 'Spaces', node: null }]
    let node = selectedNode.value
    const chain = []
    while (node) {
        chain.unshift(node)
        node = node.parent
    }
    for (const n of chain) crumbs.push({ name: n.name, node: n })
    return crumbs
})

const filteredItems = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return items.value
    return items.value.filter((row) => row.name.toLowerCase().includes(q))
})

// --- helpers ---
// Foundry Resource.type values are uppercase namespaced constants
// (e.g. COMPASS_FOLDER, FOUNDRY_DATASET). Spaces are synthesised with type 'space'.
function resKind(type) {
    const t = (type || '').toUpperCase()
    if (t === 'SPACE') return 'space'
    if (t === 'COMPASS_FOLDER') return 'folder'
    if (t === 'FOUNDRY_DATASET') return 'dataset'
    return 'other'
}

function isContainerKind(kind) {
    return kind === 'space' || kind === 'project' || kind === 'folder'
}

function isFolderLike(node) {
    return !!node && (node.kind === 'folder' || node.kind === 'project')
}

function iconFor(kind) {
    if (kind === 'space') return 'globe'
    if (kind === 'dataset') return 'database'
    if (kind === 'file') return 'file'
    return 'folder'
}

function colorKind(kind) {
    if (kind === 'space') return 'space'
    if (kind === 'dataset') return 'dataset'
    if (kind === 'file') return 'file'
    return 'folder'
}

function humanSize(bytes) {
    const n = Number(bytes)
    if (!Number.isFinite(n) || n <= 0) return ''
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    let value = n
    let i = 0
    while (value >= 1024 && i < units.length - 1) { value /= 1024; i++ }
    return `${value.toFixed(value < 10 && i > 0 ? 1 : 0)} ${units[i]}`
}

function resourceNode(resource, parent) {
    const kind = resKind(resource.type)
    return reactive({
        id: resource.rid,
        name: resource.displayName || resource.rid,
        kind,
        rid: resource.rid,
        type: resource.type,
        parent,
        expandable: isContainerKind(kind) || kind === 'dataset',
        expanded: false,
        loaded: false,
        loading: false,
        children: [],
        rows: [],
        icon: iconFor(kind),
        colorKind: colorKind(kind),
    })
}

function fileFolderNode(datasetRid, prefix, name, parent) {
    return reactive({
        id: `ff:${datasetRid}:${prefix}`,
        name,
        kind: 'file-folder',
        datasetRid,
        prefix,
        parent,
        expandable: true,
        expanded: false,
        loaded: false,
        loading: false,
        children: [],
        rows: [],
        icon: 'folder',
        colorKind: 'folder',
    })
}

function resourceRow(resource) {
    const kind = resKind(resource.type)
    return {
        id: resource.rid,
        name: resource.displayName || resource.rid,
        sub: resource.type || 'resource',
        kind,
        rid: resource.rid,
        type: resource.type,
        openable: isContainerKind(kind) || kind === 'dataset',
        icon: iconFor(kind),
        colorKind: colorKind(kind),
    }
}

function deriveFiles(fileList, prefix) {
    const folders = new Map()
    const files = []
    for (const f of fileList) {
        const filePath = f.path || ''
        if (prefix && !filePath.startsWith(prefix)) continue
        const rel = filePath.slice(prefix.length)
        if (!rel) continue
        const slash = rel.indexOf('/')
        if (slash >= 0) {
            const folderName = rel.slice(0, slash)
            const full = prefix + folderName + '/'
            if (!folders.has(full)) folders.set(full, { name: folderName, prefix: full })
        } else {
            const dot = rel.lastIndexOf('.')
            const ext = dot > 0 ? rel.slice(dot + 1).toLowerCase() : ''
            files.push({
                id: `f:${filePath}`,
                name: rel,
                sub: [ext, humanSize(f.sizeBytes ?? f.size)].filter(Boolean).join(' · '),
                kind: 'file',
                openable: false,
                path: filePath,
                icon: 'file',
                colorKind: 'file',
            })
        }
    }
    const folderRows = [...folders.values()]
        .sort((a, b) => a.name.localeCompare(b.name))
        .map((d) => ({ id: `d:${d.prefix}`, name: d.name, sub: 'folder', kind: 'folder', openable: true, prefix: d.prefix, icon: 'folder', colorKind: 'folder' }))
    files.sort((a, b) => a.name.localeCompare(b.name))
    return { folders, folderRows, fileRows: files }
}

// --- loading ---
async function ensureRootLoaded() {
    if (rootLoaded.value) return
    const result = await Foundry.spaces(props.diagramId)
    const spaces = Array.isArray(result?.data) ? result.data : []
    tree.value = spaces.map((s) => resourceNode({ ...s, type: 'space' }, null))
    rootRows.value = spaces.map((s) => resourceRow({ ...s, type: 'space' }))
    rootLoaded.value = true
}

async function loadNodeChildren(node) {
    if (node.loaded) return
    node.loading = true
    try {
        if (isContainerKind(node.kind)) {
            const result = await Foundry.folderChildren(props.diagramId, node.rid)
            const resources = Array.isArray(result?.data) ? result.data : []
            node.children = resources
                .filter((r) => { const k = resKind(r.type); return isContainerKind(k) || k === 'dataset' })
                .map((r) => resourceNode(r, node))
            node.rows = resources.map(resourceRow)
        } else if (node.kind === 'dataset' || node.kind === 'file-folder') {
            const datasetRid = node.kind === 'dataset' ? node.rid : node.datasetRid
            const prefix = node.kind === 'dataset' ? '' : node.prefix
            const result = await Foundry.listFiles(props.diagramId, datasetRid, prefix ? { pathPrefix: prefix } : {})
            const fileList = Array.isArray(result?.data) ? result.data : []
            const { folders, folderRows, fileRows } = deriveFiles(fileList, prefix)
            node.children = [...folders.values()].map((d) => fileFolderNode(datasetRid, d.prefix, d.name, node))
            node.rows = [...folderRows, ...fileRows]
        }
        node.loaded = true
    } finally {
        node.loading = false
    }
}

async function selectRoot() {
    selectedNode.value = null
    search.value = ''
    itemsLoading.value = true
    try {
        await ensureRootLoaded()
        items.value = rootRows.value
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not list spaces.'))
    } finally {
        itemsLoading.value = false
    }
}

async function selectNode(node) {
    selectedNode.value = node
    search.value = ''
    itemsLoading.value = true
    try {
        await loadNodeChildren(node)
        node.expanded = true
        items.value = node.rows
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not list folder contents.'))
    } finally {
        itemsLoading.value = false
    }
}

async function toggleNode(node) {
    if (!node.expanded && !node.loaded) {
        try {
            await loadNodeChildren(node)
        } catch (error) {
            $toast.error(foundryErrorMessage(error, 'Could not load folder.'))
            return
        }
    }
    node.expanded = !node.expanded
}

function onCrumb(crumb) {
    if (!crumb.node) selectRoot()
    else selectNode(crumb.node)
}

function openRow(row) {
    if (!row.openable) return
    const pool = selectedNode.value ? selectedNode.value.children : tree.value
    let child = null
    if (row.kind === 'folder' && row.prefix) {
        child = pool.find((n) => n.kind === 'file-folder' && n.prefix === row.prefix)
    } else if (row.rid) {
        child = pool.find((n) => n.rid === row.rid)
    }
    if (child) selectNode(child)
}

// --- ontology ---
async function loadOntologies() {
    ontologiesLoading.value = true
    try {
        const result = await Foundry.ontologies(props.diagramId)
        ontologies.value = Array.isArray(result?.data) ? result.data : []
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not load ontologies.'))
    } finally {
        ontologiesLoading.value = false
    }
}

async function onOntologyChange() {
    if (props.canManageHost && selectedOntologyRid.value && selectedOntologyRid.value !== defaults.value.ontology) {
        try {
            await Foundry.updateConfig(props.diagramId, { default_ontology_rid: selectedOntologyRid.value })
            defaults.value = { ...defaults.value, ontology: selectedOntologyRid.value }
            $toast.success('Default ontology saved.')
        } catch (error) {
            $toast.error(foundryErrorMessage(error, 'Could not save default ontology.'))
        }
    }
}

// --- defaults / import ---
async function setDefaultFolder(row) {
    const type = (row.type || '').toLowerCase()
    const payload = { default_folder_rid: row.rid }
    if (type === 'project') payload.default_project_rid = row.rid
    try {
        await Foundry.updateConfig(props.diagramId, payload)
        defaults.value = { ...defaults.value, folder: row.rid, project: type === 'project' ? row.rid : defaults.value.project }
        $toast.success('Default folder saved.')
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not save default folder.'))
    }
}

async function importDataset(row) {
    if (importingRid.value) return
    importingRid.value = row.rid
    try {
        const result = await Foundry.getDatasetSchema(props.diagramId, row.rid)
        const fields = result?.schema?.fieldSchemaList
        if (!Array.isArray(fields) || fields.length === 0) {
            $toast.error('That dataset has no applied schema to import.')
            return
        }
        const jsonSchema = datasetSchemaToJsonSchema(row.name, fields)
        emit('import-reference', {
            content: JSON.stringify(jsonSchema),
            title: row.name,
            source: {
                importedFrom: 'foundry-dataset',
                datasetRid: row.rid,
                datasetName: row.name,
                host: status.value?.host_url ?? null,
                syncedAt: new Date().toISOString(),
            },
        })
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not import the dataset schema.'))
    } finally {
        importingRid.value = null
    }
}

// --- lifecycle ---
async function init() {
    if (!props.diagramId) return
    loading.value = true
    try {
        const [config, statusData] = await Promise.all([
            Foundry.getConfig(props.diagramId),
            Foundry.status(props.diagramId),
        ])
        defaults.value = {
            project: config?.default_project_rid ?? null,
            folder: config?.default_folder_rid ?? null,
            ontology: config?.default_ontology_rid ?? null,
        }
        selectedOntologyRid.value = config?.default_ontology_rid ?? ''
        status.value = statusData
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not load Foundry status.'))
    } finally {
        loading.value = false
    }

    if (isConnected.value) {
        await loadOntologies()
        if (!selectedOntologyRid.value && ontologyOptions.value.length) {
            selectedOntologyRid.value = ontologyOptions.value[0].rid
        }
        await selectRoot()
    }
}

async function reloadAll() {
    tree.value = []
    rootLoaded.value = false
    rootRows.value = []
    selectedNode.value = null
    items.value = []
    ontologies.value = []
    await init()
}

onMounted(init)
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 200;
}

.modal-card {
    background: var(--bg-surface);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.foundry-browser {
    width: 920px;
    max-width: calc(100vw - 2rem);
    max-height: calc(100vh - 3rem);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-color);
}

.fb-head-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.modal-title {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 700;
}

.modal-close {
    width: 28px;
    height: 28px;
    border: 0;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
}

.modal-close:hover {
    color: var(--text-primary);
}

.foundry-browser__body {
    padding: 14px 18px 18px;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}

.fb-empty {
    font-size: 0.82rem;
    color: var(--text-muted);
}

.fb-empty--pad {
    padding: 12px;
}

.fb-notice {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.fb-notice__hint {
    margin-top: 8px;
    color: var(--text-muted);
    font-size: 0.78rem;
}

/* Ontology dropdown */
.fb-ontology {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.fb-ontology label {
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary);
}

.fb-select {
    flex: 1;
    min-width: 0;
    height: 34px;
    padding: 0 10px;
    border: 1px solid var(--border-color);
    border-radius: 7px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 0.85rem;
}

/* Path bar */
.fb-pathbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 2px;
    padding: 7px 10px;
    margin-bottom: 10px;
    border: 1px solid var(--border-color);
    border-radius: 7px;
    background: var(--bg-surface-alt);
    min-height: 34px;
}

.fb-crumb {
    border: 0;
    background: none;
    padding: 2px 4px;
    border-radius: 4px;
    color: var(--color-primary-text);
    font-size: 0.78rem;
    cursor: pointer;
}

.fb-crumb:hover {
    background: var(--hover-bg-alt);
}

.fb-pathsep {
    color: var(--text-muted);
    flex-shrink: 0;
}

/* Split */
.fb-split {
    display: flex;
    min-height: 0;
    flex: 1;
    height: 56vh;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.fb-tree {
    width: 260px;
    flex-shrink: 0;
    overflow: auto;
    padding: 6px;
    border-right: 1px solid var(--border-color);
}

.fb-list {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.fb-list__bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border-bottom: 1px solid var(--border-color);
}

.fb-search {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 9px;
    height: 30px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--input-bg);
}

.fb-search__icon {
    color: var(--text-muted);
    flex-shrink: 0;
}

.fb-search input {
    flex: 1;
    min-width: 0;
    border: 0;
    background: transparent;
    color: var(--text-primary);
    font-size: 0.82rem;
    outline: none;
}

.fb-list__scroll {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 6px;
}

.fb-rows {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.fb-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border: 1px solid transparent;
    border-radius: 6px;
}

.fb-row:hover {
    background: var(--bg-surface-alt);
    border-color: var(--border-color);
}

.fb-row__icon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    color: var(--text-muted);
}

.fb-ticon--space { color: #5b8def; }
.fb-ticon--folder { color: #a78bfa; }
.fb-ticon--dataset { color: #5db583; }
.fb-ticon--file { color: #9aa0a6; }

.fb-row__main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    text-align: left;
    background: none;
    border: 0;
    padding: 0;
    cursor: pointer;
    color: inherit;
}

.fb-row__main.is-leaf {
    cursor: default;
}

.fb-row__main strong {
    font-size: 0.82rem;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fb-row__main span {
    font-size: 0.68rem;
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fb-row__actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.fb-btn {
    padding: 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: transparent;
    color: var(--text-primary);
    font-size: 0.75rem;
    cursor: pointer;
}

.fb-btn--ghost {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    font-size: 0.72rem;
}

.fb-btn--ghost:hover {
    background: var(--hover-bg-alt);
    border-color: var(--border-strong);
}

.fb-mini {
    flex-shrink: 0;
    padding: 4px 9px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    background: transparent;
    color: var(--text-secondary);
    font-size: 0.68rem;
    cursor: pointer;
}

.fb-mini:hover:not(:disabled) {
    background: var(--hover-bg-alt);
    border-color: var(--border-strong);
}

.fb-mini:disabled {
    opacity: 0.6;
    cursor: default;
}

.fb-mini--primary {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: var(--color-text-on-primary);
}

.fb-mini--primary:hover:not(:disabled) {
    background: var(--color-primary-hover);
    border-color: var(--color-primary-hover);
}
</style>
