<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal-card foundry-browser">
            <div class="modal-header">
                <span class="modal-title">Foundry Browser</span>
                <div class="foundry-browser__head-actions">
                    <button v-if="canEdit && isConnected" type="button" class="foundry-btn foundry-btn--ghost" title="Re-sync all Foundry-linked tables" @click="$emit('sync')">
                        <SvgIcon name="history" :size="14" /> Sync linked
                    </button>
                    <button class="modal-close" type="button" title="Refresh" aria-label="Refresh" @click="init">
                        <SvgIcon name="history" :size="15" />
                    </button>
                    <button class="modal-close" type="button" aria-label="Close" @click="$emit('close')">
                        <SvgIcon name="close" :size="16" />
                    </button>
                </div>
            </div>

            <div class="foundry-browser__body">
                <p v-if="!diagramId" class="foundry-empty">Save the diagram first to browse Foundry.</p>

                <div v-else-if="loading" class="foundry-empty">Loading…</div>

                <div v-else-if="!isConnected" class="foundry-notice">
                    <p>{{ stateMessage || 'Not connected to Foundry.' }}</p>
                    <p class="foundry-notice__hint">Open <strong>Diagram Details</strong> in the right sidebar to set a host and connect, then reopen this browser.</p>
                </div>

                <div v-else class="foundry-browser__grid">
                    <!-- Ontologies -->
                    <section class="foundry-col">
                        <div class="foundry-col__head">
                            <h3>Ontologies</h3>
                            <button type="button" class="foundry-link" :disabled="ontologiesLoading" @click="loadOntologies">
                                {{ ontologiesLoading ? 'Loading…' : 'Refresh' }}
                            </button>
                        </div>
                        <p v-if="defaults.ontology" class="foundry-meta">Default: {{ ontologyLabel(defaults.ontology) }}</p>
                        <ul v-if="ontologies.length" class="foundry-list foundry-list--tall">
                            <li v-for="o in ontologies" :key="o.rid" class="foundry-list__item foundry-list__item--row" :title="o.rid">
                                <div class="foundry-item-text">
                                    <strong>{{ o.displayName || o.apiName || o.rid }}</strong>
                                    <span>{{ o.apiName }}</span>
                                </div>
                                <button v-if="canManageHost" type="button" class="foundry-mini" @click="setDefaultOntology(o)">
                                    {{ defaults.ontology === o.rid ? 'Default' : 'Set' }}
                                </button>
                            </li>
                        </ul>
                        <p v-else class="foundry-empty">No ontologies.</p>
                    </section>

                    <!-- Files & folders browser -->
                    <section class="foundry-col">
                        <div class="foundry-col__head">
                            <h3>Files &amp; Folders</h3>
                            <div class="foundry-col__head-actions">
                                <button
                                    v-if="canEdit && mode === 'files' && datasetRid"
                                    type="button"
                                    class="foundry-mini foundry-mini--primary"
                                    :disabled="importingRid === datasetRid"
                                    @click="importCurrentDataset"
                                >
                                    {{ importingRid === datasetRid ? '…' : 'Import dataset' }}
                                </button>
                                <button type="button" class="foundry-link" :disabled="browsing" @click="loadSpaces">Spaces</button>
                            </div>
                        </div>
                        <p v-if="defaults.folder" class="foundry-meta">Default folder: {{ defaults.folder }}</p>
                        <div v-if="crumbs.length" class="foundry-crumbs">
                            <template v-for="(c, i) in crumbs" :key="i">
                                <button type="button" class="foundry-link" @click="crumbTo(i)">{{ c.name }}</button>
                                <span v-if="i < crumbs.length - 1" class="foundry-crumbs__sep">/</span>
                            </template>
                        </div>
                        <div class="foundry-row">
                            <input v-model="manualRid" type="text" placeholder="Open folder by RID…" />
                            <button type="button" class="foundry-btn" :disabled="!manualRid || browsing" @click="openRid">Open</button>
                        </div>
                        <ul v-if="rows.length" class="foundry-list foundry-list--tall">
                            <li v-for="row in rows" :key="row.id" class="foundry-list__item foundry-list__item--row" :title="row.rid || row.path || row.name">
                                <span class="foundry-row-icon" :class="`foundry-row-icon--${colorKind(row)}`">
                                    <SvgIcon :name="iconFor(row)" :size="15" />
                                </span>
                                <button type="button" class="foundry-item-main" :class="{ 'is-leaf': !row.openable }" :disabled="!row.openable" @click="openRow(row)">
                                    <strong>{{ row.name }}</strong>
                                    <span>{{ row.sub }}</span>
                                </button>
                                <div class="foundry-row-actions">
                                    <button v-if="canEdit && row.kind === 'dataset'" type="button" class="foundry-mini foundry-mini--primary" :disabled="importingRid === row.rid" @click="importDataset(row)">
                                        {{ importingRid === row.rid ? '…' : 'Import' }}
                                    </button>
                                    <button v-if="canManageHost && row.kind === 'fs' && row.openable" type="button" class="foundry-mini" @click="setDefaultFolder(row)">Set</button>
                                </div>
                            </li>
                        </ul>
                        <p v-else-if="browsed" class="foundry-empty">{{ mode === 'files' ? 'No files here.' : 'Empty.' }}</p>
                        <p v-else class="foundry-empty">Click “Spaces” to start browsing, then open a dataset to see its files.</p>
                        <div v-if="mode === 'files' && filesNextToken" class="foundry-loadmore">
                            <button type="button" class="foundry-link" :disabled="browsing" @click="loadMoreFiles">Load more files</button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toast-notification'
import { Foundry, foundryErrorMessage } from '@/services/Foundry.js'
import { datasetSchemaToJsonSchema } from '@/services/foundryImport.js'
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

const mode = ref('fs') // 'fs' (spaces/projects/folders/datasets) | 'files' (inside a dataset)
const datasetRid = ref(null)
const filePrefix = ref('')
const fsItems = ref([])
const rawFiles = ref([])
const filesNextToken = ref(null)
const crumbs = ref([])
const manualRid = ref('')
const browsing = ref(false)
const browsed = ref(false)
const importingRid = ref(null)

const currentDataset = computed(() => [...crumbs.value].reverse().find((c) => c.kind === 'dataset') || null)

const isConnected = computed(() => status.value?.state === 'connected')

const stateMessage = computed(() => {
    switch (status.value?.state) {
        case 'expired':
            return 'Your Foundry connection expired. Reconnect in the sidebar.'
        case 'disconnected':
            return 'Connect your Foundry account to browse.'
        case 'host_not_configured':
            return 'This host needs administrator OAuth setup before anyone can connect.'
        case 'host_not_set':
            return 'No Foundry host is set for this diagram.'
        default:
            return ''
    }
})

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
        status.value = statusData
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not load Foundry status.'))
    } finally {
        loading.value = false
    }

    if (isConnected.value) {
        await Promise.all([loadOntologies(), loadSpaces()])
    }
}

const LEAF_TYPES = new Set(['dataset', 'media-set', 'mediaset', 'stream', 'code-repository', 'notepad'])

function isFsContainer(resource) {
    const type = (resource?.type || '').toLowerCase()
    if (type === '') return true
    if (LEAF_TYPES.has(type)) return false
    return /space|project|folder/.test(type)
}

function isDataset(resource) {
    return (resource?.type || '').toLowerCase() === 'dataset'
}

function iconFor(row) {
    if (row.kind === 'dataset') return 'database'
    if (row.kind === 'file') return 'file'
    if (row.kind === 'folder') return 'folder'
    return (row.type || '').toLowerCase() === 'space' ? 'globe' : 'folder'
}

function colorKind(row) {
    if (row.kind === 'dataset') return 'dataset'
    if (row.kind === 'file') return 'file'
    if (row.kind === 'folder') return 'folder'
    return (row.type || '').toLowerCase() === 'space' ? 'space' : 'folder'
}

function humanSize(bytes) {
    const n = Number(bytes)
    if (!Number.isFinite(n) || n <= 0) return ''
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    let value = n
    let i = 0
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024
        i++
    }
    return `${value.toFixed(value < 10 && i > 0 ? 1 : 0)} ${units[i]}`
}

// Display rows: filesystem resources (fs mode) or derived folders/files (files mode).
const rows = computed(() => {
    if (mode.value === 'fs') {
        return fsItems.value.map((r) => ({
            id: r.rid,
            name: r.displayName || r.rid,
            sub: r.type || 'resource',
            openable: isFsContainer(r) || isDataset(r),
            kind: isDataset(r) ? 'dataset' : 'fs',
            rid: r.rid,
            type: r.type,
        }))
    }

    const prefix = filePrefix.value
    const folders = new Map()
    const files = []
    for (const f of rawFiles.value) {
        const path = f.path || ''
        if (prefix && !path.startsWith(prefix)) continue
        const rel = path.slice(prefix.length)
        if (!rel) continue
        const slash = rel.indexOf('/')
        if (slash >= 0) {
            const folderName = rel.slice(0, slash)
            const full = prefix + folderName + '/'
            if (!folders.has(full)) {
                folders.set(full, { id: `d:${full}`, name: folderName, sub: 'folder', openable: true, kind: 'folder', prefix: full })
            }
        } else {
            const dot = rel.lastIndexOf('.')
            const ext = dot > 0 ? rel.slice(dot + 1).toLowerCase() : ''
            const sub = [ext, humanSize(f.sizeBytes ?? f.size)].filter(Boolean).join(' · ')
            files.push({ id: `f:${path}`, name: rel, sub, openable: false, kind: 'file', path })
        }
    }
    const folderRows = [...folders.values()].sort((a, b) => a.name.localeCompare(b.name))
    files.sort((a, b) => a.name.localeCompare(b.name))
    return [...folderRows, ...files]
})

async function loadOntologies() {
    if (ontologiesLoading.value) return
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

function ontologyLabel(rid) {
    const match = ontologies.value.find((o) => o.rid === rid)
    return match ? (match.displayName || match.apiName || rid) : rid
}

async function setDefaultOntology(ontology) {
    try {
        await Foundry.updateConfig(props.diagramId, { default_ontology_rid: ontology.rid })
        defaults.value = { ...defaults.value, ontology: ontology.rid }
        $toast.success('Default ontology saved.')
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not save default ontology.'))
    }
}

async function loadSpaces() {
    if (browsing.value) return
    browsing.value = true
    try {
        const result = await Foundry.spaces(props.diagramId)
        fsItems.value = (Array.isArray(result?.data) ? result.data : []).map((s) => ({ ...s, type: 'space' }))
        mode.value = 'fs'
        datasetRid.value = null
        filePrefix.value = ''
        crumbs.value = [{ kind: 'spaces', name: 'Spaces' }]
        browsed.value = true
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not list spaces.'))
    } finally {
        browsing.value = false
    }
}

async function loadChildren(rid) {
    browsing.value = true
    try {
        const result = await Foundry.folderChildren(props.diagramId, rid)
        fsItems.value = Array.isArray(result?.data) ? result.data : []
        mode.value = 'fs'
        datasetRid.value = null
        browsed.value = true
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not list folder contents.'))
    } finally {
        browsing.value = false
    }
}

async function loadFiles(reset = true) {
    if (!datasetRid.value) return
    browsing.value = true
    try {
        const params = { pageSize: 500 }
        if (filePrefix.value) params.pathPrefix = filePrefix.value
        if (!reset && filesNextToken.value) params.pageToken = filesNextToken.value
        const result = await Foundry.listFiles(props.diagramId, datasetRid.value, params)
        const data = Array.isArray(result?.data) ? result.data : []
        rawFiles.value = reset ? data : [...rawFiles.value, ...data]
        filesNextToken.value = result?.nextPageToken ?? null
        mode.value = 'files'
        browsed.value = true
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not list dataset files.'))
    } finally {
        browsing.value = false
    }
}

async function loadMoreFiles() {
    await loadFiles(false)
}

async function openDataset(row) {
    datasetRid.value = row.rid
    filePrefix.value = ''
    filesNextToken.value = null
    crumbs.value = [...crumbs.value, { kind: 'dataset', rid: row.rid, name: row.name }]
    await loadFiles(true)
}

async function openRow(row) {
    if (!row.openable) return
    if (mode.value === 'fs') {
        if (row.kind === 'dataset') {
            await openDataset(row)
        } else {
            crumbs.value = [...crumbs.value, { kind: 'fs', rid: row.rid, name: row.name }]
            await loadChildren(row.rid)
        }
        return
    }
    // files mode: drilling into a folder within the dataset
    crumbs.value = [...crumbs.value, { kind: 'file', prefix: row.prefix, name: row.name }]
    filePrefix.value = row.prefix
    filesNextToken.value = null
    await loadFiles(true)
}

async function crumbTo(index) {
    const target = crumbs.value[index]
    crumbs.value = crumbs.value.slice(0, index + 1)
    if (target.kind === 'spaces') {
        await loadSpaces()
    } else if (target.kind === 'fs') {
        await loadChildren(target.rid)
    } else if (target.kind === 'dataset') {
        datasetRid.value = target.rid
        filePrefix.value = ''
        filesNextToken.value = null
        await loadFiles(true)
    } else if (target.kind === 'file') {
        filePrefix.value = target.prefix
        filesNextToken.value = null
        await loadFiles(true)
    }
}

async function openRid() {
    const rid = manualRid.value.trim()
    if (!rid) return
    crumbs.value = [...crumbs.value, { kind: 'fs', rid, name: rid }]
    manualRid.value = ''
    await loadChildren(rid)
}

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

// --- Import a dataset's schema into the design as a (Foundry-linked) reference table ---
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

function importCurrentDataset() {
    if (!datasetRid.value) return
    importDataset({ rid: datasetRid.value, name: currentDataset.value?.name || 'Dataset' })
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
    width: 820px;
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

.foundry-browser__head-actions {
    display: flex;
    align-items: center;
    gap: 4px;
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
    padding: 16px 18px;
    overflow-y: auto;
}

.foundry-browser__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

@media (max-width: 680px) {
    .foundry-browser__grid {
        grid-template-columns: 1fr;
    }
}

.foundry-col {
    min-width: 0;
}

.foundry-col__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}

.foundry-col__head-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.foundry-col__head h3 {
    margin: 0;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary);
}

.foundry-notice {
    padding: 8px 2px;
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.foundry-notice__hint {
    margin-top: 8px;
    color: var(--text-muted);
    font-size: 0.78rem;
}

.foundry-meta {
    margin: 0 0 8px;
    font-size: 0.7rem;
    color: var(--text-muted);
    word-break: break-all;
}

.foundry-empty {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin: 4px 0;
}

.foundry-link {
    background: none;
    border: none;
    padding: 0;
    color: var(--color-primary-text);
    font-size: 0.72rem;
    cursor: pointer;
    text-decoration: underline;
}

.foundry-btn {
    padding: 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: transparent;
    color: var(--text-primary);
    font-size: 0.75rem;
    cursor: pointer;
}

.foundry-btn:hover:not(:disabled) {
    background: var(--hover-bg-alt);
    border-color: var(--border-strong);
}

.foundry-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.foundry-btn--ghost {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    font-size: 0.72rem;
}

.foundry-crumbs {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    margin-bottom: 8px;
}

.foundry-crumbs__sep {
    color: var(--text-muted);
    font-size: 0.7rem;
}

.foundry-row {
    display: flex;
    gap: 6px;
    margin-bottom: 10px;
}

.foundry-row input {
    flex: 1;
    min-width: 0;
    height: 32px;
    padding: 0 10px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 13px;
}

.foundry-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow-y: auto;
}

.foundry-list--tall {
    max-height: 46vh;
}

.foundry-list__item {
    display: flex;
    padding: 7px 9px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-surface-alt);
}

.foundry-list__item--row {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}

.foundry-item-main,
.foundry-item-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
    text-align: left;
}

.foundry-item-main {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: inherit;
}

.foundry-item-main.is-leaf {
    cursor: default;
}

.foundry-list__item strong {
    font-size: 0.8rem;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.foundry-list__item span {
    font-size: 0.68rem;
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.foundry-row-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.foundry-mini {
    flex-shrink: 0;
    padding: 4px 9px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    background: transparent;
    color: var(--text-secondary);
    font-size: 0.68rem;
    cursor: pointer;
}

.foundry-mini:hover:not(:disabled) {
    background: var(--hover-bg-alt);
    border-color: var(--border-strong);
}

.foundry-mini:disabled {
    opacity: 0.6;
    cursor: default;
}

.foundry-mini--primary {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: var(--color-text-on-primary);
}

.foundry-mini--primary:hover:not(:disabled) {
    background: var(--color-primary-hover);
    border-color: var(--color-primary-hover);
}

.foundry-row-icon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    color: var(--text-muted);
}

.foundry-row-icon--space { color: #5b8def; }
.foundry-row-icon--folder { color: #a78bfa; }
.foundry-row-icon--dataset { color: #5db583; }
.foundry-row-icon--file { color: #9aa0a6; }

.foundry-loadmore {
    margin-top: 8px;
}
</style>
