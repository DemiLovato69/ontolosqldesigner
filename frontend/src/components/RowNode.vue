<template>
    <template v-if="compact">
        <span class="row_compact_label" @dblclick.stop="canEdit && (data.editing = true)">{{ label }}</span>
        <span v-if="badges.length" class="constraint_badges">
            <span v-for="b in badges" :key="b.label" :class="['constraint_badge', b.cls]">{{ b.label }}</span>
        </span>
        <span class="row_compact_type">{{ data.sqlType }}</span>
        <Handle type="source" position="left" id="source-left" />
        <Handle type="target" position="left" id="target-left" />
        <Handle type="source" position="right" id="source-right" />
        <Handle type="target" position="right" id="target-right" />
    </template>

    <template v-else>
        <!-- Drag handle -->
        <span
            v-if="canEdit"
            class="drag_handle_icon"
            title="Drag to reorder"
            @mousedown.stop.prevent="$emit('row-drag-start', id)"
        ><SvgIcon name="drag" :size="14" /></span>

        <input
            class="input input_designer_row ml-5 mr-5"
            :value="label"
            @mousedown.stop
            @focus="(e) => { if (!canEdit) return; data.editing = true; e.target.select(); }"
            @blur="(e) => { data.editing = false; $emit('update-label', id, label); e.target.scrollLeft = 0; }"
            @input="$emit('update-label', id, $event.target.value)"
            @keydown="(e) => {
                if (e.shiftKey && e.key === 'Enter' && canEdit) { e.preventDefault(); $emit('add-row-after', id) }
                else if (e.key === 'Tab' && e.shiftKey) { e.preventDefault(); $emit('tab-prev', id) }
            }"
            :readonly="!data.editing || !canEdit"
        />

        <!-- Constraint badges + enum button grouped together -->
        <div v-if="badges.length || (isEnum && canEdit)" class="constraint_badges">
            <span v-for="b in badges" :key="b.label" :class="['constraint_badge', b.cls]">{{ b.label }}</span>
            <button v-if="isEnum && canEdit" ref="enumBtnRef" class="table_button enum_values_btn" @mousedown.stop @click="showEnumModal = !showEnumModal" title="Edit enum values">
                <SvgIcon name="list" :size="13" />
            </button>
            <span v-if="isEnum && isEnumEmpty && canEdit" class="enum_empty_wrapper">
                <SvgIcon name="warning" :size="13" stroke="#ef4444" />
            </span>
        </div>

        <!-- Enum values modal -->
        <EnumValuesModal
            v-if="isEnum && showEnumModal"
            :data="data"
            :dbType="dbType"
            :ignore="[enumBtnRef]"
            @change="emitChange()"
            @close="showEnumModal = false"
        />

        <!-- SQL Type -->
        <div class="type_cell">
            <input
                v-if="isLengthType"
                type="number"
                class="length_input"
                :value="typeLengthValue"
                min="1"
                :disabled="!canEdit"
                @mousedown.stop
                @change="onLengthChange"
            />
            <select v-model="sqlTypeForSelect" @change="emitChange()" :disabled="!canEdit">
                <optgroup v-if="dbType === 'ontology' && valueTypes.length" label="Value Types">
                    <option v-for="valueType in valueTypes" :key="valueType.id" :value="`__value_type__:${valueType.id}`">
                        {{ valueType.displayName }}
                    </option>
                </optgroup>
                <optgroup v-for="(options, groupLabel) in typeGroups" :key="groupLabel" :label="groupLabel">
                    <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </optgroup>
                <option :value="data.sqlType">{{ data.sqlType }}</option>
            </select>
        </div>

        <!-- Options -->
        <button
            v-if="canEdit || description"
            ref="noteBtnRef"
            :class="['table_button', { 'table_button--has-note': description }]"
            @mousedown.stop
            @click.stop.prevent="showNote = !showNote"
            :title="description || 'Add row description'"
        >
            <SvgIcon name="note" :size="13" />
        </button>

        <button v-if="canEdit" ref="gearBtnRef" class="table_button" @mousedown.stop @click="$emit('toggle-options-modal', id)">
            <SvgIcon name="gear" :size="13" />
        </button>

        <NodeNoteModal
            v-if="showNote"
            title="Row description"
            :note="description"
            :canEdit="canEdit"
            :anchor="noteBtnRef"
            :ignore="[noteBtnRef]"
            @save="$emit('update-note', id, $event)"
            @close="showNote = false"
        />

        <!-- Options modal -->
        <RowOptionsModal
            v-if="data.showOptionsModal"
            :data="data"
            :dbType="dbType"
            :label="label"
            :tableColumns="tableColumns"
            :tableUniqueTogether="tableUniqueTogether"
            :tableFulltextIndexes="tableFulltextIndexes"
            :ignore="[gearBtnRef]"
            @change="emitChange()"
            @close="$emit('toggle-options-modal', id)"
            @update-table-constraints="$emit('update-table-constraints', $event)"
            @update-table-fulltext="$emit('update-table-fulltext', $event)"
        />

        <!-- Delete row -->
        <button v-if="canEdit" class="table_button" @mousedown.stop @click="$emit('delete-node', id)" @keydown="(e) => { if (e.key === 'Tab' && !e.shiftKey) { e.preventDefault(); $emit('tab-next', id) } }">
            <SvgIcon name="trash" :size="13" />
        </button>

        <!-- Left side handles -->
        <Handle type="source" position="left" id="source-left" />
        <Handle type="target" position="left" id="target-left" />

        <!-- Right side handles -->
        <Handle type="source" position="right" id="source-right" />
        <Handle type="target" position="right" id="target-right" />
    </template>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Handle } from '@vue-flow/core'
import SvgIcon from './SvgIcon.vue'
import RowOptionsModal from './RowOptionsModal.vue'
import EnumValuesModal from './EnumValuesModal.vue'
import NodeNoteModal from './NodeNoteModal.vue'
import { typeGroupsFor } from '@/services/rowTypes.js'

const props = defineProps({
    id: String,
    data: Object,
    label: String,
    dbType: { type: String, default: 'mysql' },
    canEdit: { type: Boolean, default: true },
    tableColumns: { type: Array, default: () => [] },
    tableUniqueTogether: { type: Array, default: () => [] },
    tableFulltextIndexes: { type: Array, default: () => [] },
    valueTypes: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
})

const emit = defineEmits(['update-label', 'toggle-options-modal', 'delete-node', 'change', 'row-drag-start', 'update-table-constraints', 'update-table-fulltext', 'add-row-after', 'tab-next', 'tab-prev', 'update-note'])

const emitChange = () => emit('change', props.id)
const description = computed(() => props.data.description ?? props.data.comment ?? '')

// --- Constraint badges ---

const badges = computed(() => {
    const result = []
    const km = props.data.keyMod
    if (km === 'PRIMARY KEY') result.push({ label: 'PK', cls: 'badge--pk' })
    else if (km === 'UNIQUE') result.push({ label: 'UQ', cls: 'badge--uq' })
    else if (km === 'INDEX') result.push({ label: 'IDX', cls: 'badge--idx' })
    if (props.tableUniqueTogether?.some(g => g.includes(props.label))) {
        result.push({ label: 'U+', cls: 'badge--uq-together' })
    }
    if (props.tableFulltextIndexes?.some(g => g.includes(props.label))) {
        result.push({ label: 'FT', cls: 'badge--ft' })
    }
    if (props.data.nullable) {
        result.push({ label: 'NULL', cls: 'badge--null' })
    }
    if ((props.data.indexed ?? true) && !result.some(badge => badge.label === 'IDX')) {
        result.push({ label: 'IDX', cls: 'badge--idx' })
    }
    if (props.data.valueTypeId) {
        result.push({ label: 'VT', cls: 'badge--vt' })
    }
    return result
})

const typeGroups = computed(() => typeGroupsFor(props.dbType))

const isEnum = computed(() => /^ENUM\(/i.test(props.data.sqlType))

const isEnumEmpty = computed(() => {
    const m = props.data.sqlType.match(/^ENUM\((.*)\)$/i)
    if (!m) return false
    const re = /'([^']*)'|"([^"]*)"/g
    let match
    while ((match = re.exec(m[1])) !== null) {
        if ((match[1] ?? match[2]).trim() !== '') return false
    }
    return true
})

const showEnumModal = ref(false)
const showNote = ref(false)
const enumBtnRef = ref(null)
const gearBtnRef = ref(null)
const noteBtnRef = ref(null)

watch(isEnum, (val) => { if (!val) showEnumModal.value = false })

// Types that take a single integer length param, e.g. VARCHAR(32)
const LENGTH_TYPE_RE = /^(CHAR|VARCHAR|NCHAR|NVARCHAR|BINARY|VARBINARY|VARCHAR2|NVARCHAR2|TEXT|RAW)\((\d+)\)$/i

const isLengthType = computed(() => LENGTH_TYPE_RE.test(props.data.sqlType))

const typeLengthValue = computed(() => {
    const m = props.data.sqlType?.match(LENGTH_TYPE_RE)
    return m ? parseInt(m[2]) : 255
})

const onLengthChange = (e) => {
    const num = parseInt(e.target.value)
    if (!num || num < 1) { e.target.value = typeLengthValue.value; return }
    const m = props.data.sqlType?.match(LENGTH_TYPE_RE)
    if (!m) return
    props.data.sqlType = `${m[1].toUpperCase()}(${num})`
    emitChange()
}

const sqlTypeForSelect = computed({
    get() {
        if (props.data.valueTypeId) return `__value_type__:${props.data.valueTypeId}`
        if (isEnum.value) return "ENUM('')"
        const m = props.data.sqlType?.match(LENGTH_TYPE_RE)
        if (m) {
            const baseName = m[1].toUpperCase()
            for (const group of Object.values(typeGroups.value)) {
                for (const opt of group) {
                    if (new RegExp(`^${baseName}\\(`, 'i').test(opt.value)) return opt.value
                }
            }
        }
        return props.data.sqlType
    },
    set(val) {
        if (val?.startsWith('__value_type__:')) {
            const valueTypeId = val.slice('__value_type__:'.length)
            const valueType = props.valueTypes.find(item => item.id === valueTypeId)
            if (!valueType) return
            props.data.valueTypeId = valueTypeId
            props.data.sqlType = canvasTypeForValueType(valueType)
            props.data.ontologyBaseType = null
            props.data.ontologyImportedSqlType = null
            return
        }
        props.data.valueTypeId = null
        const newMatch = val?.match(LENGTH_TYPE_RE)
        const curMatch = props.data.sqlType?.match(LENGTH_TYPE_RE)
        // Same base type — preserve the user's custom length
        if (newMatch && curMatch && newMatch[1].toUpperCase() === curMatch[1].toUpperCase()) return
        props.data.sqlType = val
    }
})

const canvasTypeForValueType = (valueType) => {
    const baseType = valueType.baseType ?? { type: 'string' }
    if (baseType.type === 'array') return `ARRAY<${String(baseType.elementType ?? 'string').toUpperCase()}>`
    if (baseType.type === 'struct') return 'STRUCT'
    if (baseType.type === 'decimal') return 'DECIMAL(10,2)'
    return String(baseType.type ?? 'string').toUpperCase()
}
</script>

<style scoped>
.input_designer_row {
    flex: 1;
    min-width: 0;
    height: 5px;
    padding-top: 10px;
    padding-bottom: 10px;
}

.ml-5 { margin-left: 5px; }
.mr-5 { margin-right: 5px; }

.row_compact_label {
    flex: 1;
    min-width: 0;
    padding: 0 6px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
}

.row_compact_type {
    max-width: 120px;
    padding-left: 6px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-secondary);
    font-size: 11px;
}

.enum_values_btn {
    width: 18px;
    height: 18px;
    padding: 2px;
    color: var(--color-primary);
}

/* ── Drag handle ─────────────────────────────────────────────── */
.drag_handle_icon {
    flex-shrink: 0;
    width: 24px;
    opacity: 0.35;
    cursor: grab;
    user-select: none;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
}

.drag_handle_icon:hover { opacity: 0.7; }
.drag_handle_icon:active { cursor: grabbing; opacity: 1; }

/* ── Constraint badges ───────────────────────────────────────── */
.constraint_badges {
    display: flex;
    gap: 3px;
    flex-shrink: 0;
    margin-right: 4px;
}

.constraint_badge {
    font-size: 9px;
    font-weight: 700;
    padding: 1px 4px;
    border-radius: 3px;
    letter-spacing: 0.3px;
    white-space: nowrap;
    line-height: 15px;
    user-select: none;
}

.badge--pk          { background: #f59e0b; color: #fff; }
.badge--uq          { background: #3b82f6; color: #fff; }
.badge--idx         { background: #6b7280; color: #fff; }
.badge--uq-together { background: #10b981; color: #fff; }
.badge--vt          { background: #7c3aed; color: #fff; }
.badge--ft          { background: #f97316; color: #fff; }
.badge--null        { background: #7c3aed; color: #fff; }

.type_cell {
    display: flex;
    align-items: center;
    gap: 2px;
}

.length_input {
    width: 38px;
    padding: 4px 6px;
    background-color: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 5px;
    color: var(--text-primary);
    font-size: 12px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.15s;
    -moz-appearance: textfield;
}

.length_input::-webkit-outer-spin-button,
.length_input::-webkit-inner-spin-button {
    -webkit-appearance: none;
}

.length_input:hover {
    border-color: var(--border-strong);
    background-color: var(--hover-bg-alt);
}

.length_input:focus {
    outline: none;
    border-color: var(--border-strong);
    cursor: text;
}

.length_input:disabled {
    opacity: 0.6;
    cursor: default;
}

.enum_empty_wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    cursor: default;
}

.enum_empty_wrapper::after {
    content: 'ENUM must have at least one value';
    position: absolute;
    left: 50%;
    bottom: calc(100% + 6px);
    transform: translateX(-50%);
    background: var(--bg-surface);
    border: 1px solid var(--border-strong);
    color: var(--text-primary);
    font-size: 11px;
    white-space: nowrap;
    padding: 4px 8px;
    border-radius: 4px;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 100;
}

.enum_empty_wrapper:hover::after {
    opacity: 1;
}
</style>
