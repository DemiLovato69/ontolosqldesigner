<template>
    <!-- Loading state -->
    <div v-if="loading" class="diagram-status-screen">
        <span class="diagram-status-screen__text">Loading…</span>
    </div>

    <!-- Not available state -->
    <div v-else-if="notAvailable" class="diagram-status-screen">
        <div class="diagram-status-screen__icon-wrap">
            <span class="diagram-status-screen__logo-mark">S</span>
        </div>
        <span class="diagram-status-screen__text">This diagram is not available.</span>
        <div style="display:flex;gap:0.5rem;margin-top:1.25rem">
            <button class="btn btn-secondary" @click="router.push({ name: 'diagrams' })">My Diagrams</button>
        </div>
    </div>

    <!-- Pending approval state -->
    <div v-else-if="pendingApproval" class="diagram-status-screen">
        <div class="diagram-status-screen__icon-wrap">
            <span class="diagram-status-screen__logo-mark">S</span>
        </div>
        <span class="diagram-status-screen__text">Access requires approval.</span>
        <span class="diagram-status-screen__subtext">Your request has been sent to the diagram owner. Check back once they've approved you.</span>
        <div style="display:flex;gap:0.5rem;margin-top:1.25rem">
            <button class="btn btn-primary" @click="retryAccess" :disabled="loading">{{ loading ? 'Checking…' : 'Retry' }}</button>
            <button class="btn btn-secondary" @click="router.push({ name: 'diagrams' })">My Diagrams</button>
        </div>
    </div>

    <template v-else>
        <DiagramHeader
            :canEdit="canEdit"
            :isOwner="isOwner"
            :isDemo="isDemo"
            :dbType="diagramDbType"
            :hasPendingVisitors="hasPendingVisitors"
            @add-table="addTable"
            @show-share="showShareModal = true"
            @show-help="showHotkeysModal = true"
            @add-reference-table="addReferenceTable"
            @add-pipeline="onAddPipeline"
            @open-reference-json-import="showReferenceJsonImportModal = true"
            @show-value-types="openValueTypesModal"
            @show-shared-property-types="showSharedPropertyTypesModal = true"
            @show-interfaces="showInterfacesModal = true"
            @show-custom-actions="showCustomActionsModal = true"
        />

        <ShareModal
            v-if="showShareModal"
            :diagramId="diagramId"
            :token="token"
            v-model:shareAccess="diagramShareAccess"
            v-model:requireApproval="diagramRequireApproval"
            v-model:inLibrary="diagramInLibrary"
            v-model:hasPendingVisitors="hasPendingVisitors"
            @save="saveDiagram"
            @close="showShareModal = false"
        />

        <div :class="['diagram-workspace', { 'is-sidebar-collapsed': !tableSidebarOpen }]">
            <aside class="schema-sidebar" aria-label="Schema tables">
                <button class="schema-sidebar__toggle" type="button" @click="tableSidebarOpen = !tableSidebarOpen" title="Tables">
                    <SvgIcon name="table-list" :size="18" />
                </button>
                <template v-if="tableSidebarOpen">
                    <input
                        v-model="tableSearch"
                        class="schema-sidebar__search"
                        type="search"
                        placeholder="Search tables"
                        aria-label="Search tables"
                    />
                    <div class="schema-sidebar__section-head">
                        <span>Tables</span>
                        <button v-if="tables.length" type="button" @click="toggleAllTablesVisibility">
                            {{ allTablesHidden ? 'Show all' : 'Hide all' }}
                        </button>
                    </div>
                    <div class="schema-sidebar__list">
                        <div
                            v-for="t in filteredTables"
                            :key="t.id"
                            :class="['schema-sidebar__item', { 'schema-sidebar__item--hidden': isTableEffectivelyHidden(t) }]"
                        >
                            <button
                                class="schema-sidebar__item-main"
                                type="button"
                                @dblclick.stop="navigateToTable(t.id)"
                                @keydown.enter.prevent="navigateToTable(t.id)"
                                :title="isTableEffectivelyHidden(t) ? 'Table is hidden' : 'Double-click to focus table'"
                            >
                                <span>{{ t.label }}</span>
                                <span v-if="t.data?.reference || t.data?.tableKind === 'reference'" class="schema-sidebar__tag">REF</span>
                            </button>
                            <button
                                class="schema-sidebar__eye"
                                type="button"
                                :title="isTableEffectivelyHidden(t) ? 'Show table' : 'Hide table'"
                                :aria-label="isTableEffectivelyHidden(t) ? `Show ${t.label}` : `Hide ${t.label}`"
                                @click.stop="toggleTableVisibility(t)"
                            >
                                <SvgIcon :name="isTableEffectivelyHidden(t) ? 'eye-off' : 'eye'" :size="15" />
                            </button>
                        </div>
                        <span v-if="!tables.length" class="schema-sidebar__empty">No tables</span>
                        <span v-else-if="!filteredTables.length" class="schema-sidebar__empty">No matches</span>
                    </div>
                    <div class="schema-sidebar__filters" aria-label="View filters">
                        <div class="schema-sidebar__filters-head">
                            <span>View</span>
                        </div>
                        <button
                            v-for="option in viewFilterOptions"
                            :key="option.key"
                            :class="['schema-sidebar__filter', { 'schema-sidebar__filter--off': !viewFilters[option.key] }]"
                            type="button"
                            @click="toggleViewFilter(option.key)"
                        >
                            <span>{{ option.label }}</span>
                            <span class="schema-sidebar__switch" aria-hidden="true"></span>
                        </button>
                    </div>
                </template>
            </aside>

            <div class="diagram-canvas-wrapper" ref="canvasWrapperRef" @mousemove="onCanvasMouseMove">
                <div class="cursor-layer" aria-hidden="true">
                    <RemoteCursor
                        v-for="cursor in remoteCursors"
                        :key="cursor.id"
                        :x="cursor.screenX"
                        :y="cursor.screenY"
                        :name="cursor.name"
                        :color="cursor.color"
                    />
                    <div
                        v-for="ind in offScreenCursors"
                        :key="'edge-' + ind.id"
                        class="cursor-edge-indicator"
                        :style="{ transform: `translate(calc(${ind.x}px - 50%), calc(${ind.y}px - 50%))` }"
                    >
                        <svg class="cursor-edge-indicator__arrow" :style="{ transform: `rotate(${ind.angle}deg)` }" width="14" height="14" viewBox="0 0 14 14" :fill="ind.color">
                            <polygon points="1,3 1,11 13,7" />
                        </svg>
                    </div>
                </div>
                <VueFlow
                :default-edge-options="{ type: 'chickenFoot' }"
                @edge-update="canEdit && onEdgeUpdate($event)"
                @edge-click="canEdit && openRelationshipModal($event)"
                @connect="canEdit && onConnect($event)"
                @connect-start="isConnecting = true"
                @connect-end="isConnecting = false"
                @node-drag-start="onNodeDragStart"
                @node-drag="onNodeDrag"
                @node-drag-stop="onNodeDragStop"
                @node-click="({ node }) => elevateTable(node)"
                @pane-click="onCanvasPaneClick"
                @node-mouse-enter="onNodeMouseEnter"
                @node-mouse-leave="onNodeMouseLeave"
                :is-valid-connection="isValidConnection"
                v-model="schema"
                :min-zoom="0.01"
                :max-zoom="4"
                :fit-view-on-init="!isLargeDiagram"
                only-render-visible-elements
                :zoomOnDoubleClick="false"
                :controlled="false"
                :pan-on-drag="!isPlacingTable"
                :nodes-draggable="canEdit"
                :nodes-connectable="canEdit"
                :edges-updatable="canEdit"
                :class="['diagram-canvas', { 'is-placing-table': isPlacingTable, 'is-connecting': isConnecting, 'is-large-overview': isLargeOverview }]"
            >
                <Panel v-if="canEdit" position="top-left" class="table-navigator">
                    <div class="table-navigator__row">
                        <template v-if="canEdit">
                            <label class="table-navigator__color-btn" title="Default table color">
                                <span class="table-navigator__color-swatch table-navigator__color-swatch--table" :style="{ background: defaultTableColor }"></span>
                                <input type="color" v-model="defaultTableColor" class="table-navigator__color-input" />
                            </label>
                            <label class="table-navigator__color-btn" title="Default connection color">
                                <span class="table-navigator__color-swatch table-navigator__color-swatch--line" :style="{ background: defaultConnectionColor }"></span>
                                <input type="color" v-model="defaultConnectionColor" class="table-navigator__color-input" />
                            </label>
                        </template>
                    </div>
                </Panel>


                <template #edge-chickenFoot="props">
                    <ChickenFootEdge v-if="!isLargeOverview" v-bind="props" :simple-routing="isLargeDiagram" />
                </template>

                <template #edge-transform="props">
                    <TransformEdge v-if="!isLargeOverview" v-bind="props" />
                </template>

                <Panel position="bottom-right" class="support-panel">
                    <button class="support-panel__btn" @click.stop="openSupportModal" title="Support">
                        ?
                    </button>
                </Panel>

                <template #node-table="nodeProps">
                    <TableNode
                        :id="nodeProps.id"
                        :data="nodeProps.data"
                        :label="nodeProps.label"
                        :dbType="diagramDbType"
                        :columns="tableColumns.get(nodeProps.id) ?? []"
                        :interfaces="interfaces"
                        :canEdit="canEdit"
                        @delete-node="deleteNode"
                        @update-label="updateLabel"
                        @copy-table="copyTable"
                        @add-row="addRow({ id: $event, data: {} })"
                        @resize-start="startTableResize"
                        @update-color="updateTableColor"
                        @update-note="updateNote"
                        @update-actions="updateTableActions"
                    />
                </template>

                <template #node-pipeline-transform="nodeProps">
                    <PipelineTransformNode
                        :id="nodeProps.id"
                        :data="nodeProps.data"
                        :label="nodeProps.label"
                        :canEdit="canEdit"
                        @delete-node="deleteNode"
                        @attach-selected="onAttachSelectedRowsToTransform"
                        @update-label="updateTransformLabel"
                    />
                </template>

                <template #node-row="nodeProps">
                    <RowNode
                        v-if="!isLargeOverview"
                        :id="nodeProps.id"
                        :data="nodeProps.data"
                        :label="nodeProps.label"
                        :dbType="diagramDbType"
                        :canEdit="canEdit"
                        :tableColumns="tableColumnLabels.get(nodeProps.parentNodeId) ?? []"
                        :tableUniqueTogether="tableById.get(nodeProps.parentNodeId)?.data?.uniqueTogether ?? []"
                        :tableFulltextIndexes="tableById.get(nodeProps.parentNodeId)?.data?.fulltextIndexes ?? []"
                        :valueTypes="valueTypes"
                        :compact="isLargeDiagram && !nodeProps.data.editing && !nodeProps.data.showOptionsModal"
                        :selected="selectedRowIds.includes(nodeProps.id)"
                        :hasTypeError="valueTypeMismatchRowIds.has(nodeProps.id)"
                        @update-label="updateLabel"
                        @toggle-options-modal="toggleOptionsModal"
                        @delete-node="deleteNode"
                        @add-row-after="addRowAfter"
                        @tab-next="tabToRow($event, 'next')"
                        @tab-prev="tabToRow($event, 'prev')"
                        @change="onRowChange($event)"
                        @row-drag-start="startRowDrag"
                        @update-table-constraints="onTableConstraintsChange(nodeProps.parentNodeId, $event)"
                        @update-table-fulltext="onTableFulltextChange(nodeProps.parentNodeId, $event)"
                        @update-note="updateNote"
                        @row-select="toggleRowSelection"
                    />
                </template>

                </VueFlow>
            </div>
            <DiagramRightSidebar
                :diagramId="diagramId"
                :open="rightSidebarOpen"
                :refreshKey="changelogRefreshKey"
                :dbType="diagramDbType"
                :valueTypes="valueTypes"
                :sharedPropertyTypes="sharedPropertyTypes"
                :interfaces="interfaces"
                :interfaceLinkConstraints="interfaceLinkConstraints"
                :customActions="customActions"
                :problems="valueTypeProblems"
                @toggle="rightSidebarOpen = !rightSidebarOpen"
                @focus-problem="focusProblemRow"
                @open-value-type="openValueTypeFromSidebar"
                @open-shared-property-type="openSharedPropertyTypeFromSidebar"
                @open-interface="openInterfaceFromSidebar"
                @open-interface-link-constraint="openInterfaceLinkConstraintFromSidebar"
                @open-custom-action="openCustomActionFromSidebar"
            />
        </div>

        <RelationshipModal
            v-if="showRelationshipModal"
            :position="modalPosition"
            :edge-color="selectedEdge?.data?.color"
            :visual-only="selectedEdge?.data?.linkKind === 'reference' || selectedEdge?.data?.linkKind === 'transform' || selectedEdge?.data?.exportable === false"
            :visual-only-label="selectedEdge?.data?.linkKind === 'transform' ? 'Pipeline link' : 'Reference link'"
            @update-type="updateConnectionLineType"
            @delete="deleteEdge"
            @close="closeRelationshipModal"
            @update-color="updateEdgeColor"
        />

        <SqlModal
            v-if="showImportModal"
            v-model="importContent"
            v-model:importType="importType"
            primaryLabel="Import"
            :loading="importLoading"
            :selected-import-file="importFile"
            :upload-progress="importUploadProgress"
            :upload-phase="importUploadPhase"
            @import-file="file => { importFile = file; importUploadProgress = 0; importUploadPhase = '' }"
            @primary-action="importSql"
            @close="showImportModal = false"
        />

        <ReferenceJsonImportModal
            v-if="showReferenceJsonImportModal"
            @import="onImportReferenceJsonFromModal"
            @close="showReferenceJsonImportModal = false"
        />

        <ExportModal
            v-if="showExportModal"
            :filename="diagramName"
            :diagramId="diagramId"
            :dbType="diagramDbType"
            @close="showExportModal = false"
            @capture-png="capturePng"
            @capture-svg="captureSvg"
        />

        <SupportModal
            v-if="showSupportModal"
            :user-email="supportUserEmail"
            @close="showSupportModal = false"
        />

        <HotkeysModal
            v-if="showHotkeysModal"
            @close="showHotkeysModal = false"
        />

        <ValueTypesModal
            v-if="showValueTypesModal"
            :valueTypes="valueTypes"
            :schema="schema"
            :canEdit="canEdit"
            :initialSelectedKey="selectedValueTypeKey"
            @update="updateValueTypes"
            @close="showValueTypesModal = false"
        />

        <SharedPropertyTypesModal
            v-if="showSharedPropertyTypesModal"
            :sharedPropertyTypes="sharedPropertyTypes"
            :canEdit="canEdit"
            :initialSelectedKey="selectedSharedPropertyTypeKey"
            @update="updateSharedPropertyTypes"
            @close="showSharedPropertyTypesModal = false"
        />

        <InterfacesModal
            v-if="showInterfacesModal"
            :interfaces="interfaces"
            :interfaceLinkConstraints="interfaceLinkConstraints"
            :canEdit="canEdit"
            :initialSelectedKey="selectedInterfaceKey"
            @update="updateInterfaces"
            @close="showInterfacesModal = false"
        />

        <CustomActionsModal
            v-if="showCustomActionsModal"
            :customActions="customActions"
            :tables="tables"
            :canEdit="canEdit"
            :initialSelectedKey="selectedCustomActionKey"
            @update="updateCustomActions"
            @close="showCustomActionsModal = false"
        />

    </template>
</template>

<script setup>
import { computed, onBeforeMount, onMounted, onUnmounted, ref, nextTick, watch } from 'vue'
import { Panel, Position, useVueFlow, VueFlow } from '@vue-flow/core'
import { TABLE_STYLE } from '@/services/TableActions.js'
import { repairAndNormalizeSchema } from '@/services/SchemaRepair.js'
import { Diagram } from '@/services/Diagram.js'
import { exportDiagramPng, exportDiagramSvg } from '@/services/DiagramPngExporter.js'
import { DEMO_SCHEMA } from '@/services/demoSchema.js'
import { materializeInlineEnumValueTypes, effectiveBaseTypeToken } from '@/services/valueTypes.js'
import { useDiagramPresence, CURSOR_COLORS } from '@/composables/useDiagramPresence.js'
import { useDiagramPolling } from '@/composables/useDiagramPolling.js'
import { useOffScreenCursors } from '@/composables/useOffScreenCursors.js'
import { useTableInteraction } from '@/composables/useTableInteraction.js'
import { useTableResize } from '@/composables/useTableResize.js'
import { useRowDrag } from '@/composables/useRowDrag.js'
import { useSchemaActions } from '@/composables/useSchemaActions.js'
import { useUndoHistory } from '@/composables/useUndoHistory.js'
import { clearDiagramHeaderActions, setDiagramHeaderActions } from '@/composables/useAppHeaderActions.js'
import SvgIcon from '../SvgIcon.vue'
import DiagramHeader from './DiagramHeader.vue'
import ShareModal from '../Modal/ShareModal.vue'
import ChickenFootEdge from '../ChickenFootEdge.vue'
import TransformEdge from '../TransformEdge.vue'
import TableNode from './TableNode.vue'
import PipelineTransformNode from './PipelineTransformNode.vue'
import RowNode from '../RowNode.vue'
import RelationshipModal from '../Modal/RelationshipModal.vue'
import SqlModal from '../Modal/SqlModal.vue'
import ReferenceJsonImportModal from '../Modal/ReferenceJsonImportModal.vue'
import ExportModal from '../Modal/ExportModal.vue'
import RemoteCursor from '../RemoteCursor.vue'
import SupportModal from '../Modal/SupportModal.vue'
import DiagramRightSidebar from './DiagramRightSidebar.vue'
import HotkeysModal from '../Modal/HotkeysModal.vue'
import ValueTypesModal from '../Modal/ValueTypesModal.vue'
import SharedPropertyTypesModal from '../Modal/SharedPropertyTypesModal.vue'
import InterfacesModal from '../Modal/InterfacesModal.vue'
import CustomActionsModal from '../Modal/CustomActionsModal.vue'
import { useToast } from 'vue-toast-notification'
import { useRoute, useRouter } from 'vue-router'
import axios from '@/axios.js'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import '@/css/diagram.css'
import '@/css/header.css'

const props = defineProps({ isDemo: { type: Boolean, default: false } })

const { updateEdge, addEdges, setElements, viewport, screenToFlowCoordinate, flowToScreenCoordinate, findNode, fitView, setCenter } = useVueFlow()
const router = useRouter()
const $toast = useToast()

const token = useRoute().params.token
const diagramId = ref(null)
const isOwner = ref(false)
const notAvailable = ref(false)
const pendingApproval = ref(false)
const loading = ref(false)
const isSaved = ref(true)
const schema = ref([])
const valueTypes = ref([])
const interfaces = ref([])
const interfaceLinkConstraints = ref([])
const customActions = ref([])
const sharedPropertyTypes = ref([])
const LARGE_DIAGRAM_ELEMENT_COUNT = 2000
const isLargeDiagram = computed(() => schema.value.length > LARGE_DIAGRAM_ELEMENT_COUNT)
const isLargeOverview = computed(() => isLargeDiagram.value && viewport.value.zoom < 0.18)
const tables = computed(() => schema.value.filter(el => el.type === 'table'))
const tableSearch = ref('')
const DEFAULT_VIEW_FILTERS = {
    referenceTables: true,
    referenceLinks: true,
    pipelines: true,
    pipelineLinks: true,
}
const viewFilters = ref({ ...DEFAULT_VIEW_FILTERS })
const hiddenTableIds = ref([])
const viewStateLoaded = ref(false)
const viewStorageKey = computed(() => {
    if (props.isDemo) return 'ontolosql:view:demo'
    if (token) return `ontolosql:view:token:${token}`
    if (diagramId.value) return `ontolosql:view:diagram:${diagramId.value}`
    return null
})
const viewFilterOptions = [
    { key: 'referenceTables', label: 'Reference Tables' },
    { key: 'referenceLinks', label: 'Reference Links' },
    { key: 'pipelines', label: 'Pipelines' },
    { key: 'pipelineLinks', label: 'Pipeline Links' },
]
const hiddenTableIdSet = computed(() => new Set(hiddenTableIds.value))
const allTablesHidden = computed(() => tables.value.length > 0 && tables.value.every(table => hiddenTableIdSet.value.has(table.id)))
const filteredTables = computed(() => {
    const q = tableSearch.value.trim().toLowerCase()
    const list = q
        ? tables.value.filter(table => (table.label ?? '').toLowerCase().includes(q))
        : tables.value

    return [...list].sort((a, b) => (a.label ?? '').localeCompare(b.label ?? '', undefined, { sensitivity: 'base' }))
})
const tableById = computed(() => new Map(tables.value.map(table => [table.id, table])))
const isReferenceTable = (table) => !!(table?.data?.reference || table?.data?.tableKind === 'reference')
const isReferenceLink = (element) => element?.data?.linkKind === 'reference'
const isPipelineNode = (element) => element?.type === 'pipeline-transform'
const isPipelineLink = (element) => element?.type === 'transform' || element?.data?.linkKind === 'transform'
const stripViewOnlyState = (element) => {
    if (!element || typeof element !== 'object') return element
    const { hidden, ...rest } = element
    return rest
}
const stripViewOnlySchema = (elements) => Array.isArray(elements) ? elements.map(stripViewOnlyState) : []
const repairedSchema = (elements, tableIds = null) => repairAndNormalizeSchema(stripViewOnlySchema(elements), tableIds)
let applyingViewVisibility = false
const isTableHidden = (tableId) => hiddenTableIdSet.value.has(tableId)
const isTableEffectivelyHidden = (table) => isTableHidden(table?.id) || (!viewFilters.value.referenceTables && isReferenceTable(table))
const toggleTableVisibility = (table) => {
    const tableId = table?.id
    if (!tableId) return
    if (!viewFilters.value.referenceTables && isReferenceTable(table)) {
        viewFilters.value = { ...viewFilters.value, referenceTables: true }
        hiddenTableIds.value = hiddenTableIds.value.filter(id => id !== tableId)
        return
    }
    hiddenTableIds.value = isTableHidden(tableId)
        ? hiddenTableIds.value.filter(id => id !== tableId)
        : [...hiddenTableIds.value, tableId]
}
const showAllTables = () => {
    hiddenTableIds.value = []
}
const hideAllTables = () => {
    hiddenTableIds.value = tables.value.map(table => table.id)
}
const toggleAllTablesVisibility = () => {
    allTablesHidden.value ? showAllTables() : hideAllTables()
}
const toggleViewFilter = (key) => {
    viewFilters.value = { ...viewFilters.value, [key]: !viewFilters.value[key] }
}
const viewVisibilitySignature = computed(() => schema.value.map(element => [
    element.id,
    element.type,
    element.parentNode,
    element.source,
    element.target,
    element.data?.linkKind,
    element.data?.reference ? 'ref' : '',
    element.data?.tableKind ?? '',
].join(':')).join('|'))
const hiddenElementIds = () => {
    const hiddenIds = new Set()
    const hiddenRows = new Set()

    for (const element of schema.value) {
        if (element.type === 'table' && (hiddenTableIdSet.value.has(element.id) || (!viewFilters.value.referenceTables && isReferenceTable(element)))) {
            hiddenIds.add(element.id)
        }
        if (isPipelineNode(element) && !viewFilters.value.pipelines) {
            hiddenIds.add(element.id)
        }
    }

    for (const element of schema.value) {
        if (element.type === 'row' && hiddenIds.has(element.parentNode)) {
            hiddenIds.add(element.id)
            hiddenRows.add(element.id)
        }
    }

    for (const element of schema.value) {
        if (!element.source && !element.target) continue
        if (hiddenIds.has(element.source)
            || hiddenIds.has(element.target)
            || hiddenRows.has(element.source)
            || hiddenRows.has(element.target)
            || (!viewFilters.value.referenceLinks && isReferenceLink(element))
            || (!viewFilters.value.pipelineLinks && isPipelineLink(element))) {
            hiddenIds.add(element.id)
        }
    }

    return hiddenIds
}
const applyViewVisibility = () => {
    if (applyingViewVisibility) return
    applyingViewVisibility = true
    const hiddenIds = hiddenElementIds()
    let changed = false
    const nextSchema = schema.value.map(element => {
        const shouldHide = hiddenIds.has(element.id)
        if (shouldHide && element.hidden !== true) {
            changed = true
            return { ...element, hidden: true }
        }
        if (!shouldHide && element.hidden !== false) {
            changed = true
            return { ...element, hidden: false }
        }
        return element
    })
    if (changed) {
        schema.value = nextSchema
    }
    applyingViewVisibility = false
}
const loadViewState = (key) => {
    viewStateLoaded.value = false
    viewFilters.value = { ...DEFAULT_VIEW_FILTERS }
    hiddenTableIds.value = []
    if (key) {
        try {
            const stored = JSON.parse(localStorage.getItem(key) || 'null')
            if (stored && typeof stored === 'object') {
                viewFilters.value = { ...DEFAULT_VIEW_FILTERS, ...(stored.filters ?? {}) }
                hiddenTableIds.value = Array.isArray(stored.hiddenTableIds) ? stored.hiddenTableIds.filter(Boolean) : []
            }
        } catch {
            localStorage.removeItem(key)
        }
    }
    viewStateLoaded.value = true
}
watch(viewStorageKey, loadViewState, { immediate: true })
watch([viewFilters, hiddenTableIds], () => {
    if (!viewStateLoaded.value || !viewStorageKey.value) return
    try {
        localStorage.setItem(viewStorageKey.value, JSON.stringify({
            filters: viewFilters.value,
            hiddenTableIds: hiddenTableIds.value,
        }))
    } catch { /* local view state is best-effort */ }
    applyViewVisibility()
}, { deep: true })
watch(tables, (nextTables) => {
    const tableIds = new Set(nextTables.map(table => table.id))
    hiddenTableIds.value = hiddenTableIds.value.filter(id => tableIds.has(id))
}, { deep: true })
const rowsByTableId = computed(() => {
    const rows = new Map()
    for (const element of schema.value) {
        if (element.type !== 'row' || !element.parentNode) continue
        if (!rows.has(element.parentNode)) rows.set(element.parentNode, [])
        rows.get(element.parentNode).push(element)
    }
    for (const tableRows of rows.values()) {
        tableRows.sort((a, b) => (a.position?.y ?? 0) - (b.position?.y ?? 0))
    }
    return rows
})
watch(viewVisibilitySignature, () => nextTick(applyViewVisibility))
const tableColumnLabels = computed(() => {
    const labels = new Map()
    for (const [tableId, rows] of rowsByTableId.value) {
        labels.set(tableId, rows.map(row => row.label))
    }
    return labels
})
// When a value-typed row is linked by a real relationship to another row, the
// other row must have a compatible base type (e.g. a string-backed PK and FK are
// fine even with different value types). Surface incompatibilities as problems.
const valueTypeProblems = computed(() => {
    const problems = new Map()
    const rowById = new Map()
    for (const element of schema.value) {
        if (element.type === 'row') rowById.set(element.id, element)
    }
    const valueTypeById = new Map(valueTypes.value.map(valueType => [valueType.id, valueType]))
    const labelFor = (row) => `${tableById.value.get(row.parentNode)?.label ?? 'table'}.${row.label}`
    const flag = (row, counterpart, rowType, counterpartType) => {
        if (problems.has(row.id)) return
        problems.set(row.id, {
            rowId: row.id,
            tableId: row.parentNode,
            title: labelFor(row),
            detail: `Type ${rowType || 'unknown'} is incompatible with linked ${labelFor(counterpart)} (${counterpartType || 'unknown'})`,
        })
    }
    for (const element of schema.value) {
        if (!element.source || !element.target) continue
        if (element.type === 'transform') continue
        const linkKind = element.data?.linkKind
        if (linkKind === 'reference' || linkKind === 'transform') continue
        if (element.data?.exportable === false) continue
        const source = rowById.get(element.source)
        const target = rowById.get(element.target)
        if (!source || !target) continue
        const sourceHasValueType = !!source.data?.valueTypeId
        const targetHasValueType = !!target.data?.valueTypeId
        if (!sourceHasValueType && !targetHasValueType) continue
        const sourceType = effectiveBaseTypeToken(source.data, valueTypeById)
        const targetType = effectiveBaseTypeToken(target.data, valueTypeById)
        if (!sourceType || !targetType || sourceType === targetType) continue
        // Base types are incompatible: flag the counterpart of each value-typed row.
        if (sourceHasValueType) flag(target, source, targetType, sourceType)
        if (targetHasValueType) flag(source, target, sourceType, targetType)
    }
    return Array.from(problems.values())
})
const valueTypeMismatchRowIds = computed(() => new Set(valueTypeProblems.value.map(problem => problem.rowId)))
const tableColumns = computed(() => {
    const columns = new Map()
    for (const [tableId, rows] of rowsByTableId.value) {
        columns.set(tableId, rows.map(row => ({ id: row.id, label: row.label })))
    }
    return columns
})
const selectedRows = computed(() => selectedRowIds.value
    .map(id => schema.value.find(el => el.id === id && el.type === 'row'))
    .filter(Boolean)
    .map(row => {
        const table = tableById.value.get(row.parentNode)
        return { id: row.id, label: row.label, table: table?.label ?? '', reference: !!table?.data?.reference }
    }))
const diagramName = ref('schema')
const diagramDbType = ref('mysql')
const showShareModal = ref(false)
const showHotkeysModal = ref(false)
const showValueTypesModal = ref(false)
const showSharedPropertyTypesModal = ref(false)
const showInterfacesModal = ref(false)
const showCustomActionsModal = ref(false)
const showReferenceJsonImportModal = ref(false)
const diagramShareAccess = ref(null)
const diagramRequireApproval = ref(false)
const diagramInLibrary = ref(false)
const rightSidebarOpen = ref(true)
const changelogRefreshKey = ref(0)
const selectedValueTypeKey = ref(null)
const selectedSharedPropertyTypeKey = ref(null)
const selectedInterfaceKey = ref(null)
const selectedCustomActionKey = ref(null)
const ownerIdentity = ref(null)
const canvasWrapperRef = ref(null)

const canEdit = computed(() => props.isDemo || isOwner.value || diagramShareAccess.value === 'write')

const headerSharingStatus = computed(() => {
    if (props.isDemo) return null
    if (diagramInLibrary.value) {
        return {
            kind: 'public',
            icon: 'globe',
            title: diagramShareAccess.value === 'write'
                ? 'Company-wide diagram: others can edit'
                : 'Company-wide diagram: others can view',
        }
    }
    if (diagramShareAccess.value) {
        return {
            kind: 'shared',
            icon: 'share',
            title: diagramShareAccess.value === 'write'
                ? 'Shared diagram: others can edit'
                : 'Shared diagram: restricted access',
        }
    }
    return null
})

const ontologyMetadata = { interfaces, interfaceLinkConstraints, customActions, sharedPropertyTypes }
const metadataPayload = () => ({
    interfaces: interfaces.value,
    interfaceLinkConstraints: interfaceLinkConstraints.value,
    customActions: customActions.value,
    sharedPropertyTypes: sharedPropertyTypes.value,
})
const syncPayload = () => ({ schema: stripViewOnlySchema(schema.value), valueTypes: valueTypes.value, metadata: metadataPayload() })

const { snapshot, undo, redo } = useUndoHistory(schema, valueTypes, ontologyMetadata)

const { remoteCursors, whisper, initEcho, cleanupEcho, onCanvasMouseMove, broadcastCursor } = useDiagramPresence({
    token, ownerIdentity, viewport, schema, valueTypes, ontologyMetadata, canvasWrapperRef,
    canEdit,
    onDiagramSaved: () => $toast.success('Diagram saved'),
})

const { hasPendingVisitors, startVisitorPolling, stopVisitorPolling, startGuestAccessPolling, stopGuestAccessPolling } = useDiagramPolling({
    diagramId, isOwner, diagramRequireApproval, token, diagramShareAccess, pendingApproval, notAvailable,
})

const { offScreenCursors } = useOffScreenCursors({ remoteCursors, canvasWrapperRef })

const { onNodeMouseEnter, onNodeMouseLeave, elevateTable, onNodeDragStart, onNodeDrag, onNodeDragStop, lastInteractedTableId } = useTableInteraction({
    schema, whisper, isSaved, broadcastCursor, snapshot,
})

const { startTableResize } = useTableResize({ schema, viewport, whisper, isSaved, snapshot })

const { startRowDrag } = useRowDrag({ schema, isSaved, whisper, snapshot })

const logAction = (action, details = null) => {
    if (props.isDemo || !diagramId.value) return
    Diagram.addChangelogEntry(diagramId.value, action, details)
        .then(() => { changelogRefreshKey.value++ })
}

const changelogName = (item) => item?.apiName || item?.displayName || item?.name || item?.id || 'Unnamed'

const changelogKey = (item) => item?.apiName || item?.id || item?.displayName || JSON.stringify(item)

const normalizeForChangelog = (item) => {
    if (!item || typeof item !== 'object') return item
    const stripIds = (value) => {
        if (Array.isArray(value)) return value.map(stripIds)
        if (!value || typeof value !== 'object') return value
        return Object.fromEntries(
            Object.entries(value)
                .filter(([key]) => key !== 'id')
                .map(([key, child]) => [key, stripIds(child)])
        )
    }
    return stripIds(item)
}

const metadataDiff = (before = [], after = []) => {
    const previous = new Map((before ?? []).map(item => [changelogKey(item), item]))
    const next = new Map((after ?? []).map(item => [changelogKey(item), item]))
    const added = []
    const removed = []
    const updated = []

    for (const [key, item] of next) {
        if (!previous.has(key)) {
            added.push(changelogName(item))
            continue
        }
        if (JSON.stringify(normalizeForChangelog(previous.get(key))) !== JSON.stringify(normalizeForChangelog(item))) {
            updated.push(changelogName(item))
        }
    }
    for (const [key, item] of previous) {
        if (!next.has(key)) removed.push(changelogName(item))
    }

    return {
        added,
        removed,
        updated,
        added_count: added.length,
        removed_count: removed.length,
        updated_count: updated.length,
    }
}

const hasMetadataDiff = (diff) => diff.added_count > 0 || diff.removed_count > 0 || diff.updated_count > 0

const logMetadataChange = (action, before, after) => {
    const diff = metadataDiff(before, after)
    if (hasMetadataDiff(diff)) logAction(action, diff)
}

const defaultTableColor = ref('#3d7a5c')
const defaultConnectionColor = ref('#4a7a9b')

const {
    isPlacingTable, isConnecting, copyingTableId,
    selectedEdge, showRelationshipModal, modalPosition, selectedRowIds,
    addTable, addReferenceTable, importReferenceJsonSchemas, copyTable, onPaneClick,
    addRow, addRowAfter, deleteEdge, deleteNode, onConnect, onEdgeUpdate,
    updateConnectionLineType, onRowChange, updateLabel, updateTransformLabel, updateEdgeColor, updateTableColor, updateNote, updateTableActions,
    onTableConstraintsChange, onTableFulltextChange, toggleOptionsModal,
    toggleRowSelection, clearRowSelection, createTransformFromSelection, createEmptyTransform, attachSelectedRowsToTransform,
    openRelationshipModal, closeRelationshipModal,
} = useSchemaActions({ schema, isSaved, whisper, diagramDbType, addEdges, updateEdge, findNode, screenToFlowCoordinate, flowToScreenCoordinate, snapshot, logAction, defaultTableColor, defaultConnectionColor })

const onCanvasPaneClick = (event) => {
    if (!isPlacingTable.value) clearRowSelection()
    onPaneClick(event)
}

const onImportReferenceJson = ({ content, title } = {}) => {
    try {
        const imported = importReferenceJsonSchemas(content, { title })
        $toast.success(`Imported ${imported.length} reference table${imported.length === 1 ? '' : 's'}`)
    } catch (error) {
        $toast.error(error?.message || 'Could not import reference schema')
    }
}

const onImportReferenceJsonFromModal = (payload) => {
    onImportReferenceJson(payload)
    showReferenceJsonImportModal.value = false
}

const onCreateTransform = () => {
    try {
        createTransformFromSelection()
        $toast.success('Created pipeline transform')
    } catch (error) {
        $toast.error(error?.message || 'Could not create transform')
    }
}

const onAddPipeline = () => {
    try {
        createEmptyTransform()
        $toast.success('Created pipeline transform')
    } catch (error) {
        $toast.error(error?.message || 'Could not create pipeline')
    }
}

const onAttachSelectedRowsToTransform = (transformId) => {
    try {
        const count = attachSelectedRowsToTransform(transformId)
        $toast.success(`Attached ${count} selected row${count === 1 ? '' : 's'} to pipeline`)
    } catch (error) {
        $toast.error(error?.message || 'Could not attach selected rows')
    }
}

const tabToRow = (rowId, direction) => {
    const row = schema.value?.find(el => el.id === rowId)
    if (!row) return
    const siblings = schema.value
        .filter(el => el.parentNode === row.parentNode && el.type === 'row')
        .sort((a, b) => a.position.y - b.position.y)
    const idx = siblings.findIndex(el => el.id === rowId)
    const target = siblings[direction === 'next' ? idx + 1 : idx - 1]
    if (!target) return
    nextTick(() => {
        const nodeEl = document.querySelector(`.vue-flow__node[data-id="${target.id}"]`)
        if (!nodeEl) return
        if (direction === 'next') {
            nodeEl.querySelector('.input_designer_row')?.focus()
        } else {
            const focusables = nodeEl.querySelectorAll('input:not([disabled]), select:not([disabled]), button:not([disabled])')
            focusables[focusables.length - 1]?.focus()
        }
    })
}

const isValidConnection = ({ source, target }) => {
    const sourceElement = schema.value.find(el => el.id === source)
    const targetElement = schema.value.find(el => el.id === target)
    if ((sourceElement?.type === 'row' && targetElement?.type === 'pipeline-transform')
        || (sourceElement?.type === 'pipeline-transform' && targetElement?.type === 'row')) {
        return true
    }
    const sourceNode = findNode(source)
    const targetNode = findNode(target)
    return sourceNode?.parentNode !== targetNode?.parentNode
}

// --- Table navigator & support ---

const tableSidebarOpen = ref(true)
const showSupportModal = ref(false)
const supportUserEmail = ref('')

const openSupportModal = async () => {
    if (!supportUserEmail.value) {
        try {
            const { data } = await axios.get('/api/user')
            supportUserEmail.value = data.email ?? ''
        } catch { /* guest */ }
    }
    showSupportModal.value = true
}

const metadataSelectionKey = (item) => item?.id || item?.apiName || item?.displayName || null

const openValueTypeFromSidebar = (item) => {
    selectedValueTypeKey.value = metadataSelectionKey(item)
    openValueTypesModal()
}

const openSharedPropertyTypeFromSidebar = (item) => {
    selectedSharedPropertyTypeKey.value = metadataSelectionKey(item)
    showSharedPropertyTypesModal.value = true
}

const openInterfaceFromSidebar = (item) => {
    selectedInterfaceKey.value = `interface:${metadataSelectionKey(item)}`
    showInterfacesModal.value = true
}

const openInterfaceLinkConstraintFromSidebar = (item) => {
    selectedInterfaceKey.value = `constraint:${metadataSelectionKey(item)}`
    showInterfacesModal.value = true
}

const openCustomActionFromSidebar = (item) => {
    selectedCustomActionKey.value = metadataSelectionKey(item)
    showCustomActionsModal.value = true
}

const focusProblemRow = (rowId) => {
    const row = schema.value.find(el => el.id === rowId && el.type === 'row')
    if (!row) return
    navigateToTable(row.parentNode)
    selectedRowIds.value = [rowId]
}

const nodeSize = (node, fallbackWidth = 400, fallbackHeight = 40) => {
    const styleWidth = parseFloat(node.style?.width)
    const styleHeight = parseFloat(node.style?.height)
    return {
        width: node.dimensions?.width ?? (Number.isFinite(styleWidth) ? styleWidth : fallbackWidth),
        height: node.dimensions?.height ?? (Number.isFinite(styleHeight) ? styleHeight : fallbackHeight),
    }
}

const tableBounds = (tableNode) => {
    const tableX = tableNode.position?.x ?? 0
    const tableY = tableNode.position?.y ?? 0
    const tableSize = nodeSize(tableNode)
    let maxX = tableX + tableSize.width
    let maxY = tableY + tableSize.height
    for (const row of rowsByTableId.value.get(tableNode.id) ?? []) {
        const rowSize = nodeSize(row)
        maxX = Math.max(maxX, tableX + (row.position?.x ?? 0) + rowSize.width)
        maxY = Math.max(maxY, tableY + (row.position?.y ?? 0) + rowSize.height)
    }
    return {
        x: tableX,
        y: tableY,
        width: maxX - tableX,
        height: maxY - tableY,
    }
}

const navigateToTable = (tableId) => {
    const tableNode = tableById.value.get(tableId)
    if (!tableNode) return
    if (typeof setCenter === 'function') {
        const maxTableNavZoom = 1.25
        const bounds = tableBounds(tableNode)
        const canvasWidth = canvasWrapperRef.value?.clientWidth ?? 0
        const canvasHeight = canvasWrapperRef.value?.clientHeight ?? 0
        const fitZoom = canvasWidth && canvasHeight
            ? Math.min((canvasWidth * 0.8) / bounds.width, (canvasHeight * 0.8) / bounds.height, 4)
            : 4
        const zoom = Math.min(fitZoom, maxTableNavZoom)
        setCenter(
            bounds.x + bounds.width / 2,
            bounds.y + bounds.height / 2,
            { zoom, duration: 350 }
        )
        return
    }
    fitView({ nodes: [tableId, ...(rowsByTableId.value.get(tableId) ?? []).map(row => row.id)], duration: 350, padding: 0.2 })
}

const focusLargeDiagram = () => {
    if (!isLargeDiagram.value || !tables.value.length) return
    nextTick(() => {
        requestAnimationFrame(() => navigateToTable(tables.value[0].id))
    })
}

// --- Import / Export ---

const showImportModal = ref(false)
const importContent = ref('')
const importFile = ref(null)
const importType = ref('sql')
const importLoading = ref(false)
const importUploadProgress = ref(0)
const importUploadPhase = ref('')
const showExportModal = ref(false)

const importSql = async () => {
    if (!importFile.value && !importContent.value.trim()) {
        $toast.error('Cannot import an empty file')
        return
    }
    importLoading.value = true
    importUploadProgress.value = 0
    importUploadPhase.value = importFile.value ? 'Preparing upload' : ''
    // Keep autosave from replacing the queued import with the current editor state.
    isSaved.value = true
    const result = importFile.value
        ? await Diagram.importFile(diagramId.value, importType.value, importFile.value, (progress, phase) => {
            importUploadProgress.value = progress
            importUploadPhase.value = phase
        })
        : await Diagram.import(diagramId.value, importType.value, importContent.value)
    if (!result) {
        importLoading.value = false
        isSaved.value = false
        importUploadPhase.value = ''
        return
    }
    if (importFile.value) importUploadPhase.value = 'Processing import'

    const applySchema = async (schemaJson, importedValueTypes = [], warnings = [], importedDbType = null, importedMetadata = {}) => {
        // Replace Vue Flow's internal graph instead of relying on v-model
        // reconciliation, which can retain runtime state for reused node IDs.
        const repaired = repairedSchema(JSON.parse(JSON.stringify(schemaJson)))
        setElements(repaired.schema)
        schema.value = repaired.schema
        valueTypes.value = importedValueTypes
        interfaces.value = importedMetadata.interfaces ?? []
        interfaceLinkConstraints.value = importedMetadata.interfaceLinkConstraints ?? []
        customActions.value = importedMetadata.customActions ?? []
        sharedPropertyTypes.value = importedMetadata.sharedPropertyTypes ?? []
        if (importedDbType) diagramDbType.value = importedDbType
        // Promote inline ontology enums; the schema-sync whisper below carries the result.
        materializeEnumValueTypes({ markDirty: false, sync: false })
        await nextTick()
        focusLargeDiagram()
        importLoading.value = false
        $toast.success('Imported successfully')
        isSaved.value = false
        showImportModal.value = false
        importFile.value = null
        importUploadProgress.value = 0
        importUploadPhase.value = ''
        whisper('schema-sync', syncPayload())
        for (const warning of warnings) {
            $toast.warning(warning)
        }
    }

    if (result.status === 'done' && result.schema) {
        await applySchema(result.schema, result.value_types ?? [], result.warnings ?? [], result.db_type, {
            interfaces: result.interfaces ?? [],
            interfaceLinkConstraints: result.interface_link_constraints ?? [],
            customActions: result.custom_actions ?? [],
            sharedPropertyTypes: result.shared_property_types ?? [],
        })
        return
    }

    let attempts = 0
    let queueWarningShown = false
    const poll = setInterval(async () => {
        attempts++
        if (attempts > 150) {
            clearInterval(poll)
            importLoading.value = false
            isSaved.value = false
            importUploadPhase.value = ''
            $toast.error('Import timed out')
            return
        }
        const status = await Diagram.importStatus(diagramId.value)
        if (!status) return
        if (status.upload?.status === 'failed') {
            clearInterval(poll)
            importLoading.value = false
            isSaved.value = false
            importUploadPhase.value = ''
            $toast.error('Import failed: ' + (status.upload.error || status.error || 'Unknown error'))
            return
        }
        if (status.status === 'pending' && status.upload?.status === 'uploaded') {
            importUploadPhase.value = status.upload.age_seconds > 60
                ? 'Waiting for import worker'
                : 'Queued for import'
            if (status.upload.age_seconds > 120 && !queueWarningShown) {
                queueWarningShown = true
                $toast.warning('Import is uploaded but waiting for the import worker to process it')
            }
        } else if (status.status === 'processing' || status.upload?.status === 'processing') {
            importUploadPhase.value = 'Processing import'
        }
        if (status.status === 'done') {
            clearInterval(poll)
            await applySchema(status.schema, status.value_types ?? [], status.warnings ?? [], status.db_type, {
                interfaces: status.interfaces ?? [],
                interfaceLinkConstraints: status.interface_link_constraints ?? [],
                customActions: status.custom_actions ?? [],
                sharedPropertyTypes: status.shared_property_types ?? [],
            })
        } else if (status.status === 'failed') {
            clearInterval(poll)
            importLoading.value = false
            isSaved.value = false
            importUploadPhase.value = ''
            $toast.error('Import failed: ' + (status.error || 'Unknown error'))
        }
    }, 2000)
}

const openExportModal = async () => {
    if (await saveDiagram(true)) {
        showExportModal.value = true
    }
}

const capturePng = async () => {
    showExportModal.value = false
    try {
        await exportDiagramPng(schema.value, diagramName.value)
    } catch (e) {
        $toast.error('Failed to export image')
        console.error(e)
    }
}

const captureSvg = async () => {
    showExportModal.value = false
    try {
        await exportDiagramSvg(schema.value, diagramName.value)
    } catch (e) {
        $toast.error('Failed to export SVG')
        console.error(e)
    }
}

// Promote inline ontology row enums into reusable enum value types so they show
// up in the Value Types modal. Stable per-row ids keep this idempotent.
const materializeEnumValueTypes = ({ markDirty = true, sync = true } = {}) => {
    if (diagramDbType.value !== 'ontology') return false
    const result = materializeInlineEnumValueTypes(schema.value, valueTypes.value)
    if (!result.changed) return false
    schema.value = result.schema
    valueTypes.value = result.valueTypes
    setElements(result.schema)
    if (canEdit.value && markDirty) isSaved.value = false
    if (canEdit.value && sync) whisper('schema-sync', syncPayload())
    return true
}

const openValueTypesModal = () => {
    materializeEnumValueTypes()
    showValueTypesModal.value = true
}

const updateValueTypes = (nextValueTypes, nextSchema = null) => {
    snapshot()
    const previousValueTypes = JSON.parse(JSON.stringify(valueTypes.value))
    valueTypes.value = nextValueTypes
    if (nextSchema) schema.value = nextSchema
    isSaved.value = false
    whisper('schema-sync', syncPayload())
    logMetadataChange('value_types_changed', previousValueTypes, nextValueTypes)
}

const updateSharedPropertyTypes = (nextSharedPropertyTypes) => {
    snapshot()
    const previousSharedPropertyTypes = JSON.parse(JSON.stringify(sharedPropertyTypes.value))
    sharedPropertyTypes.value = nextSharedPropertyTypes
    isSaved.value = false
    whisper('schema-sync', syncPayload())
    logMetadataChange('shared_property_types_changed', previousSharedPropertyTypes, nextSharedPropertyTypes)
}

const updateInterfaces = ({ interfaces: nextInterfaces, interfaceLinkConstraints: nextConstraints }) => {
    snapshot()
    const previousInterfaces = JSON.parse(JSON.stringify(interfaces.value))
    const previousConstraints = JSON.parse(JSON.stringify(interfaceLinkConstraints.value))
    interfaces.value = nextInterfaces
    interfaceLinkConstraints.value = nextConstraints
    isSaved.value = false
    whisper('schema-sync', syncPayload())
    logMetadataChange('interfaces_changed', previousInterfaces, nextInterfaces)
    logMetadataChange('interface_link_constraints_changed', previousConstraints, nextConstraints)
}

const updateCustomActions = (nextCustomActions) => {
    snapshot()
    const previousCustomActions = JSON.parse(JSON.stringify(customActions.value))
    customActions.value = nextCustomActions
    isSaved.value = false
    whisper('schema-sync', syncPayload())
    logMetadataChange('custom_actions_changed', previousCustomActions, nextCustomActions)
}

// --- Save ---

const saveDiagram = async (silent = false) => {
    if (props.isDemo) {
        await router.push({ name: 'login' })
        return
    }
    if (importLoading.value) {
        return false
    }
    // Ensure inline ontology enums are promoted to reusable value types before persisting.
    materializeEnumValueTypes({ markDirty: false, sync: false })
    const saved = await (isOwner.value
        ? Diagram.save(diagramId.value, stripViewOnlySchema(schema.value), valueTypes.value, metadataPayload())
        : Diagram.saveByToken(token, stripViewOnlySchema(schema.value), valueTypes.value, metadataPayload()))
    if (!saved) return false
    isSaved.value = true
    if (!silent) {
        whisper('diagram-saved', {})
        whisper('schema-sync', syncPayload())
    }
    return true
}

const diagramHeaderActions = {
    isDemo: computed(() => props.isDemo),
    isSaved,
    diagramName,
    sharingStatus: headerSharingStatus,
    import: {
        visible: computed(() => canEdit.value || props.isDemo),
        run: () => { props.isDemo ? router.push({ name: 'login' }) : showImportModal.value = true },
    },
    export: {
        visible: true,
        run: () => { props.isDemo ? router.push({ name: 'login' }) : openExportModal() },
    },
    save: {
        visible: computed(() => canEdit.value),
        disabled: computed(() => !props.isDemo && isSaved.value),
        run: () => saveDiagram(),
    },
}

setDiagramHeaderActions(diagramHeaderActions)

// --- Load ---

const retryAccess = async () => {
    pendingApproval.value = false
    await getDiagram()
}

const getDiagram = async () => {
    if (props.isDemo) {
        schema.value = repairedSchema(DEMO_SCHEMA).schema
        return
    }

    loading.value = true

    try {
        const { data: user } = await axios.get('/api/user')
        ownerIdentity.value = {
            id: String(user.id),
            name: user.email,
            color: CURSOR_COLORS[user.id % CURSOR_COLORS.length],
        }
    } catch {
        loading.value = false
        await router.push({ name: 'login' })
        return
    }

    const diagramInfo = await Diagram.getByToken(token)

    if (!diagramInfo) {
        loading.value = false
        notAvailable.value = true
        return
    }

    if (diagramInfo.pending_approval) {
        loading.value = false
        pendingApproval.value = true
        return
    }

    diagramId.value = diagramInfo.id
    isOwner.value = diagramInfo.is_owner ?? false
    diagramShareAccess.value = diagramInfo.share_access ?? null
    diagramRequireApproval.value = diagramInfo.require_approval ?? false
    diagramInLibrary.value = diagramInfo.library ?? false

    if (isOwner.value && diagramRequireApproval.value) {
        const visitors = await Diagram.getVisitors(diagramId.value)
        hasPendingVisitors.value = visitors?.some(v => v.status === 'pending') ?? false
        startVisitorPolling()
    }
    diagramDbType.value = diagramInfo.db_type ?? 'mysql'
    diagramName.value = diagramInfo.name ?? ''
    valueTypes.value = diagramInfo.value_types ?? []
    interfaces.value = diagramInfo.interfaces ?? []
    interfaceLinkConstraints.value = diagramInfo.interface_link_constraints ?? []
    customActions.value = diagramInfo.custom_actions ?? []
    sharedPropertyTypes.value = diagramInfo.shared_property_types ?? []


    const loadedSchema = repairedSchema(diagramInfo.schema ?? [{
        id: '1',
        type: 'table',
        label: 'users',
        data: {
            toolbarPosition: Position.Top,
            toolbarVisible: true,
            description: '',
            ontologyActions: { create: false, modify: false, delete: false },
            editsEnabled: false,
            editsHistory: { enabled: false, storeAllPreviousProperties: false },
        },
        position: { x: 0, y: -100 },
        style: TABLE_STYLE,
    }])
    schema.value = loadedSchema.schema

    isSaved.value = !loadedSchema.changed
    // Promote inline ontology enums to reusable value types before first paint.
    // Echo is not connected yet, so don't sync; collaborators materialize on load too.
    materializeEnumValueTypes({ markDirty: true, sync: false })
    loading.value = false
    focusLargeDiagram()

    initEcho()
    if (!isOwner.value) startGuestAccessPolling()
}

const onKeyDown = (event) => {
    if (event.key === 'Escape') {
        isPlacingTable.value = false
        copyingTableId.value = null
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 'd') {
        event.preventDefault()
        if (canEdit.value && lastInteractedTableId.value) copyTable(lastInteractedTableId.value)
    }
    if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'a') {
        event.preventDefault()
        if (canEdit.value) addTable()
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 's') {
        event.preventDefault()
        if (canEdit.value) saveDiagram()
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 'z') {
        event.preventDefault()
        if (!canEdit.value) return
        const prev = undo()
        if (prev !== null) {
            schema.value = prev.schema
            valueTypes.value = prev.valueTypes ?? []
            interfaces.value = prev.interfaces ?? []
            interfaceLinkConstraints.value = prev.interfaceLinkConstraints ?? []
            customActions.value = prev.customActions ?? []
            sharedPropertyTypes.value = prev.sharedPropertyTypes ?? []
            isSaved.value = false
            whisper('schema-sync', syncPayload())
        }
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 'y') {
        event.preventDefault()
        if (!canEdit.value) return
        const next = redo()
        if (next !== null) {
            schema.value = next.schema
            valueTypes.value = next.valueTypes ?? []
            interfaces.value = next.interfaces ?? []
            interfaceLinkConstraints.value = next.interfaceLinkConstraints ?? []
            customActions.value = next.customActions ?? []
            sharedPropertyTypes.value = next.sharedPropertyTypes ?? []
            isSaved.value = false
            whisper('schema-sync', syncPayload())
        }
    }
}

let autoSaveTimer = null

onBeforeMount(getDiagram)

onMounted(() => {
    if (!props.isDemo) {
        autoSaveTimer = setInterval(() => {
            if (!isSaved.value && canEdit.value) saveDiagram()
        }, 60000)
    } else {
        nextTick(() => fitView({ padding: 0.15, duration: 0 }))
    }
    document.addEventListener('keydown', onKeyDown)
})

onUnmounted(() => {
    clearInterval(autoSaveTimer)
    stopVisitorPolling()
    stopGuestAccessPolling()
    if (!isSaved.value && canEdit.value && !props.isDemo) saveDiagram()
    cleanupEcho()
    clearDiagramHeaderActions(diagramHeaderActions)
    document.removeEventListener('keydown', onKeyDown)
})
</script>

<style scoped>
.is-placing-table * {
    cursor: crosshair !important;
}

.diagram-status-screen {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-page);
}

.diagram-status-screen__icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    margin-bottom: 16px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    display: grid;
    place-items: center;
}

.diagram-status-screen__logo-mark {
    width: 22px;
    height: 22px;
    border-radius: 5px;
    background: linear-gradient(135deg, var(--color-primary-text), var(--color-primary));
    display: grid;
    place-items: center;
    color: #0c0c0c;
    font-family: monospace;
    font-weight: 700;
    font-size: 11px;
}

.diagram-status-screen__text {
    font-size: 0.92rem;
    color: var(--text-secondary);
    font-family: monospace;
}

.diagram-status-screen__subtext {
    font-size: 0.78rem;
    color: var(--text-muted);
    text-align: center;
    max-width: 320px;
    line-height: 1.5;
    margin-top: 0.4rem;
}

.diagram-workspace {
    flex: 1;
    min-height: 0;
    display: flex;
    background: var(--bg-page);
}

.schema-sidebar {
    width: 240px;
    flex: 0 0 240px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px;
    border-right: 1px solid var(--border-color);
    background: var(--bg-surface);
    min-height: 0;
}

.diagram-workspace.is-sidebar-collapsed .schema-sidebar {
    width: 48px;
    flex-basis: 48px;
    align-items: center;
}

.schema-sidebar__toggle {
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

.schema-sidebar__toggle:hover {
    background: var(--hover-bg-alt);
}

.schema-sidebar__search {
    width: 100%;
    height: 32px;
    padding: 0 10px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 13px;
}

.schema-sidebar__search:focus {
    outline: none;
    border-color: var(--border-strong);
}

.schema-sidebar__section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 0 2px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.schema-sidebar__section-head button {
    border: none;
    background: transparent;
    color: var(--text-secondary);
    font-size: 11px;
    cursor: pointer;
    text-transform: none;
    letter-spacing: 0;
}

.schema-sidebar__section-head button:hover {
    color: var(--text-primary);
}

.schema-sidebar__list {
    flex: 1 1 50%;
    min-height: 120px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.schema-sidebar__item {
    width: 100%;
    flex: 0 0 auto;
    min-height: 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 0;
    border: 1px solid transparent;
    border-radius: 4px;
    background: none;
    color: var(--text-primary);
}

.schema-sidebar__item--hidden {
    opacity: 0.48;
}

.schema-sidebar__item-main {
    min-width: 0;
    flex: 1;
    min-height: 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 7px 4px 7px 8px;
    border: none;
    background: transparent;
    color: inherit;
    font-size: 13px;
    line-height: 18px;
    cursor: pointer;
    text-align: left;
}

.schema-sidebar__item-main span:first-child {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.schema-sidebar__eye {
    flex: 0 0 auto;
    width: 28px;
    height: 28px;
    margin-right: 2px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 5px;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
}

.schema-sidebar__eye:hover,
.schema-sidebar__eye:focus {
    outline: none;
    background: var(--hover-bg-alt);
    color: var(--text-primary);
}

.schema-sidebar__tag {
    flex: 0 0 auto;
    padding: 1px 5px;
    border-radius: 999px;
    background: rgba(139, 92, 246, 0.18);
    color: #c4b5fd;
    border: 1px solid rgba(139, 92, 246, 0.38);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.06em;
}

.schema-sidebar__item:hover,
.schema-sidebar__item:focus {
    outline: none;
    background: var(--hover-bg-alt);
}

.schema-sidebar__item-main:focus {
    outline: none;
}

.schema-sidebar__empty {
    padding: 8px;
    font-size: 12px;
    color: var(--text-muted);
}

.schema-sidebar__filters {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-top: 10px;
    border-top: 1px solid var(--border-color);
}

.schema-sidebar__filters-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 0 2px 2px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.schema-sidebar__filters-head button {
    border: none;
    background: transparent;
    color: var(--text-secondary);
    font-size: 11px;
    cursor: pointer;
}

.schema-sidebar__filters-head button:hover {
    color: var(--text-primary);
}

.schema-sidebar__filter {
    width: 100%;
    min-height: 34px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 8px;
    border: 1px solid var(--border-color);
    border-radius: 7px;
    background: var(--bg-surface-alt);
    color: var(--text-primary);
    font-size: 12px;
    cursor: pointer;
}

.schema-sidebar__filter:hover {
    border-color: var(--border-strong);
}

.schema-sidebar__filter--off {
    color: var(--text-muted);
    background: transparent;
}

.schema-sidebar__switch {
    width: 24px;
    height: 14px;
    position: relative;
    flex: 0 0 auto;
    border-radius: 999px;
    background: #22c55e;
    transition: background 120ms;
}

.schema-sidebar__switch::after {
    content: '';
    position: absolute;
    top: 2px;
    right: 2px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: white;
    transition: transform 120ms;
}

.schema-sidebar__filter--off .schema-sidebar__switch {
    background: var(--border-strong);
}

.schema-sidebar__filter--off .schema-sidebar__switch::after {
    transform: translateX(-10px);
}

.diagram-canvas-wrapper {
    flex: 1;
    min-width: 0;
    min-height: 0;
    position: relative;
    overflow: hidden;
}

.diagram-canvas {
    width: 100%;
    height: 100%;
}

.cursor-layer {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 100;
    overflow: hidden;
}

.cursor-edge-indicator {
    position: absolute;
    top: 0;
    left: 0;
    display: flex;
    align-items: center;
    gap: 3px;
    pointer-events: none;
}

.cursor-edge-indicator__arrow {
    flex-shrink: 0;
    filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.35));
}

/* ── Support panel ───────────────────────────────────────────── */
.support-panel {
    margin: 0 12px 12px 0;
}

.support-panel__btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--border-color);
    border-radius: 50%;
    background: var(--bg-surface);
    font-size: 18px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(0,0,0,0.12);
}

.support-panel__btn:hover {
    background: var(--hover-bg-alt);
}

/* ── Table navigator ─────────────────────────────────────────── */
.table-navigator {
    display: flex;
    flex-direction: column;
    margin: 12px;
}

.table-navigator__row {
    display: flex;
    gap: 4px;
    align-items: center;
}

.table-navigator__color-btn {
    width: 32px;
    height: 32px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-surface);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 4px rgba(0,0,0,0.12);
    position: relative;
    overflow: hidden;
}

.table-navigator__color-btn:hover {
    background: var(--hover-bg-alt);
}

.table-navigator__color-input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
    border: none;
    padding: 0;
}

.table-navigator__color-swatch {
    display: block;
    border-radius: 3px;
    pointer-events: none;
}

.table-navigator__color-swatch--table {
    width: 14px;
    height: 14px;
}

.table-navigator__color-swatch--line {
    width: 16px;
    height: 3px;
    border-radius: 2px;
}

</style>
