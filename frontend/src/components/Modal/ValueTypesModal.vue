<template>
    <div class="vt-overlay" @click.self="$emit('close')">
        <section class="vt-modal" role="dialog" aria-modal="true" aria-labelledby="value-types-title">
            <header class="vt-header">
                <div>
                    <h2 id="value-types-title">Value Types</h2>
                    <p>Reusable ontology types with validation constraints.</p>
                </div>
                <button class="vt-icon-button" type="button" aria-label="Close" @click="$emit('close')">
                    <SvgIcon name="close" :size="20" />
                </button>
            </header>

            <div class="vt-body">
                <aside class="vt-list">
                    <button
                        v-for="valueType in localValueTypes"
                        :key="valueType.id"
                        type="button"
                        :class="['vt-list-item', { active: selectedId === valueType.id }]"
                        @click="selectedId = valueType.id"
                    >
                        <strong>{{ valueType.displayName }}</strong>
                        <span>{{ valueType.apiName }} · {{ baseTypeLabel(valueType.baseType) }}</span>
                    </button>
                    <p v-if="!localValueTypes.length" class="vt-empty">No value types defined.</p>
                    <button v-if="canEdit" class="vt-add-button" type="button" @click="createValueType">
                        + New value type
                    </button>
                </aside>

                <main v-if="selectedValueType" class="vt-editor">
                    <div class="vt-grid">
                        <label>
                            <span>Display name</span>
                            <input v-model.trim="selectedValueType.displayName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>API name</span>
                            <input v-model.trim="selectedValueType.apiName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>Version</span>
                            <input v-model.trim="selectedValueType.version" :disabled="!canEdit" placeholder="1.0.0" />
                        </label>
                        <label>
                            <span>Base type</span>
                            <select
                                v-model="selectedValueType.baseType.type"
                                :disabled="!canEdit"
                                @change="onBaseTypeChange"
                            >
                                <option v-for="type in BASE_TYPES" :key="type" :value="type">
                                    {{ typeLabel(type) }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <label class="vt-description">
                        <span>Description</span>
                        <textarea v-model="selectedValueType.description" :disabled="!canEdit" rows="2"></textarea>
                    </label>

                    <section v-if="selectedValueType.baseType.type === 'array'" class="vt-section">
                        <h3>Array element</h3>
                        <label>
                            <span>Element type</span>
                            <select v-model="selectedValueType.baseType.elementType" :disabled="!canEdit">
                                <option v-for="type in SIMPLE_TYPES" :key="type" :value="type">
                                    {{ typeLabel(type) }}
                                </option>
                            </select>
                        </label>
                    </section>

                    <section v-if="selectedValueType.baseType.type === 'struct'" class="vt-section">
                        <div class="vt-section-heading">
                            <h3>Struct fields</h3>
                            <button v-if="canEdit" type="button" class="vt-link-button" @click="addStructField">
                                + Add field
                            </button>
                        </div>
                        <div v-for="field in selectedValueType.baseType.fields" :key="field.id" class="vt-struct-field">
                            <input v-model.trim="field.apiName" :disabled="!canEdit" placeholder="fieldName" />
                            <select v-model="field.type" :disabled="!canEdit">
                                <option v-for="type in SIMPLE_TYPES" :key="type" :value="type">
                                    {{ typeLabel(type) }}
                                </option>
                            </select>
                            <button
                                v-if="canEdit"
                                type="button"
                                class="vt-icon-button danger"
                                aria-label="Remove struct field"
                                @click="removeStructField(field.id)"
                            >
                                <SvgIcon name="trash" :size="15" />
                            </button>
                        </div>
                    </section>

                    <section v-if="selectedValueType.baseType.type === 'string'" class="vt-section">
                        <div class="vt-section-heading">
                            <h3>Constraints</h3>
                            <select v-if="canEdit && availableConstraintTypes.length" v-model="constraintToAdd" @change="addConstraint">
                                <option value="">+ Add constraint</option>
                                <option v-for="type in availableConstraintTypes" :key="type" :value="type">
                                    {{ constraintLabel(type) }}
                                </option>
                            </select>
                        </div>

                        <article
                            v-for="constraint in selectedValueType.constraints"
                            :key="constraint.id"
                            class="vt-constraint"
                        >
                            <div class="vt-section-heading">
                                <strong>{{ constraintLabel(constraint.type) }}</strong>
                                <button
                                    v-if="canEdit"
                                    type="button"
                                    class="vt-icon-button danger"
                                    aria-label="Remove constraint"
                                    @click="removeConstraint(constraint.id)"
                                >
                                    <SvgIcon name="trash" :size="15" />
                                </button>
                            </div>

                            <template v-if="constraint.type === 'regex'">
                                <label>
                                    <span>Pattern</span>
                                    <input v-model="constraint.regexPattern" :disabled="!canEdit" placeholder="^[^@]+@[^@]+\\.[^@]+$" />
                                </label>
                                <label class="vt-checkbox">
                                    <input v-model="constraint.usePartialMatch" type="checkbox" :disabled="!canEdit" />
                                    <span>Allow a partial match</span>
                                </label>
                            </template>

                            <div v-if="constraint.type === 'length'" class="vt-grid">
                                <label>
                                    <span>Minimum length</span>
                                    <input v-model.number="constraint.minSize" type="number" min="0" :disabled="!canEdit" />
                                </label>
                                <label>
                                    <span>Maximum length</span>
                                    <input v-model.number="constraint.maxSize" type="number" min="0" :disabled="!canEdit" />
                                </label>
                            </div>

                            <label>
                                <span>Failure message</span>
                                <input
                                    v-model="constraint.failureMessage"
                                    :disabled="!canEdit"
                                    placeholder="Value failed validation"
                                />
                            </label>
                        </article>
                        <p v-if="!selectedValueType.constraints.length" class="vt-empty">No constraints.</p>
                    </section>

                    <section class="vt-delete-section">
                        <p v-if="references.length" class="vt-reference-warning">
                            Used by {{ references.join(', ') }}. Reassign these fields before deleting.
                        </p>
                        <button
                            v-if="canEdit"
                            type="button"
                            class="vt-delete-button"
                            :disabled="references.length > 0"
                            @click="deleteSelected"
                        >
                            Delete value type
                        </button>
                    </section>
                </main>

                <div v-else class="vt-no-selection">Select or create a value type.</div>
            </div>

            <footer class="vt-footer">
                <p v-if="error" class="vt-error">{{ error }}</p>
                <div class="vt-footer-actions">
                    <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
                    <button v-if="canEdit" type="button" class="btn btn-primary" @click="applyChanges">
                        Apply changes
                    </button>
                </div>
            </footer>
        </section>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import SvgIcon from '../SvgIcon.vue'

const props = defineProps({
    valueTypes: { type: Array, default: () => [] },
    schema: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['update', 'close'])

const BASE_TYPES = ['array', 'boolean', 'date', 'decimal', 'double', 'float', 'integer', 'long', 'short', 'string', 'struct', 'timestamp']
const SIMPLE_TYPES = ['boolean', 'date', 'decimal', 'double', 'float', 'integer', 'long', 'short', 'string', 'timestamp']
const CONSTRAINT_TYPES = ['regex', 'isRid', 'isUuid', 'length']
const SEMVER_RE = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/
const API_NAME_RE = /^[A-Za-z][A-Za-z0-9_]{0,99}$/

const clone = (value) => JSON.parse(JSON.stringify(value))
const createId = (prefix) => `${prefix}-${crypto.randomUUID?.() ?? Math.random().toString(36).slice(2)}`
const localValueTypes = ref(clone(props.valueTypes))
const selectedId = ref(localValueTypes.value[0]?.id ?? null)
const constraintToAdd = ref('')
const error = ref('')

const selectedValueType = computed(() => localValueTypes.value.find(item => item.id === selectedId.value) ?? null)
const references = computed(() => {
    if (!selectedId.value) return []
    const tables = new Map(props.schema.filter(item => item.type === 'table').map(item => [item.id, item.label]))
    return props.schema
        .filter(item => item.type === 'row' && item.data?.valueTypeId === selectedId.value)
        .map(item => `${tables.get(item.parentNode) ?? 'table'}.${item.label}`)
})
const availableConstraintTypes = computed(() => {
    const used = new Set(selectedValueType.value?.constraints?.map(item => item.type) ?? [])
    return CONSTRAINT_TYPES.filter(type => !used.has(type))
})

const typeLabel = (type) => type.charAt(0).toUpperCase() + type.slice(1)
const constraintLabel = (type) => ({
    regex: 'Regular expression',
    isRid: 'RID',
    isUuid: 'UUID',
    length: 'Length',
}[type] ?? type)
const baseTypeLabel = (baseType) => {
    if (baseType?.type === 'array') return `Array<${typeLabel(baseType.elementType ?? 'string')}>`
    return typeLabel(baseType?.type ?? 'string')
}

const createValueType = () => {
    const id = createId('value-type')
    localValueTypes.value.push({
        id,
        apiName: 'newValueType',
        displayName: 'New Value Type',
        description: '',
        version: '1.0.0',
        baseType: { type: 'string' },
        constraints: [],
    })
    selectedId.value = id
}

const onBaseTypeChange = () => {
    const valueType = selectedValueType.value
    if (!valueType) return
    valueType.constraints = valueType.baseType.type === 'string' ? valueType.constraints : []
    if (valueType.baseType.type === 'array') {
        valueType.baseType = { type: 'array', elementType: 'string' }
    } else if (valueType.baseType.type === 'struct') {
        valueType.baseType = {
            type: 'struct',
            fields: [{ id: createId('struct-field'), apiName: 'field', type: 'string' }],
        }
    } else {
        valueType.baseType = { type: valueType.baseType.type }
    }
}

const addStructField = () => {
    selectedValueType.value.baseType.fields.push({
        id: createId('struct-field'),
        apiName: `field${selectedValueType.value.baseType.fields.length + 1}`,
        type: 'string',
    })
}
const removeStructField = (id) => {
    selectedValueType.value.baseType.fields = selectedValueType.value.baseType.fields.filter(field => field.id !== id)
}

const addConstraint = () => {
    const type = constraintToAdd.value
    if (!type || !selectedValueType.value) return
    const constraint = { id: createId('constraint'), type, failureMessage: '' }
    if (type === 'regex') Object.assign(constraint, { regexPattern: '', usePartialMatch: false })
    if (type === 'length') Object.assign(constraint, { minSize: 0, maxSize: null })
    selectedValueType.value.constraints.push(constraint)
    constraintToAdd.value = ''
}
const removeConstraint = (id) => {
    selectedValueType.value.constraints = selectedValueType.value.constraints.filter(item => item.id !== id)
}

const deleteSelected = () => {
    if (references.value.length) return
    const index = localValueTypes.value.findIndex(item => item.id === selectedId.value)
    localValueTypes.value.splice(index, 1)
    selectedId.value = localValueTypes.value[index]?.id ?? localValueTypes.value[index - 1]?.id ?? null
}

const validate = () => {
    const apiNames = new Set()
    for (const valueType of localValueTypes.value) {
        if (!valueType.displayName.trim()) return 'Every value type needs a display name.'
        if (!API_NAME_RE.test(valueType.apiName)) return `${valueType.displayName} has an invalid API name.`
        if (apiNames.has(valueType.apiName.toLowerCase())) return `API name ${valueType.apiName} is duplicated.`
        apiNames.add(valueType.apiName.toLowerCase())
        if (!SEMVER_RE.test(valueType.version)) return `${valueType.displayName} has an invalid semantic version.`
        if (valueType.baseType.type === 'struct') {
            if (!valueType.baseType.fields.length) return `${valueType.displayName} must have at least one struct field.`
            const fields = new Set()
            for (const field of valueType.baseType.fields) {
                if (!API_NAME_RE.test(field.apiName)) return `${valueType.displayName} has an invalid struct field name.`
                if (fields.has(field.apiName.toLowerCase())) return `${valueType.displayName} has duplicate struct fields.`
                fields.add(field.apiName.toLowerCase())
            }
        }
        for (const constraint of valueType.constraints) {
            if (constraint.type === 'regex') {
                if (!constraint.regexPattern) return `${valueType.displayName} has an empty regex.`
                try {
                    new RegExp(constraint.regexPattern)
                } catch {
                    return `${valueType.displayName} has an invalid regex.`
                }
            }
            if (constraint.type === 'length') {
                const min = constraint.minSize === '' ? null : constraint.minSize
                const max = constraint.maxSize === '' ? null : constraint.maxSize
                if (min == null && max == null) return `${valueType.displayName} needs a minimum or maximum length.`
                if (min != null && max != null && min > max) return `${valueType.displayName} has an invalid length range.`
            }
        }
    }
    return ''
}

const applyChanges = () => {
    error.value = validate()
    if (error.value) return
    emit('update', clone(localValueTypes.value))
    emit('close')
}
</script>

<style scoped>
.vt-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(0, 0, 0, 0.62);
}

.vt-modal {
    display: flex;
    flex-direction: column;
    width: min(980px, 100%);
    height: min(760px, calc(100vh - 48px));
    color: var(--text-primary);
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.45);
}

.vt-header,
.vt-footer,
.vt-section-heading,
.vt-footer-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.vt-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
}

.vt-header h2,
.vt-section h3 {
    margin: 0;
}

.vt-header p,
.vt-empty,
.vt-no-selection {
    color: var(--text-secondary);
}

.vt-header p {
    margin: 4px 0 0;
    font-size: 13px;
}

.vt-body {
    display: grid;
    grid-template-columns: 250px 1fr;
    min-height: 0;
    flex: 1;
}

.vt-list {
    padding: 14px;
    overflow-y: auto;
    border-right: 1px solid var(--border-color);
}

.vt-list-item,
.vt-add-button {
    width: 100%;
    padding: 10px;
    margin-bottom: 6px;
    color: var(--text-primary);
    text-align: left;
    cursor: pointer;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 7px;
}

.vt-list-item:hover,
.vt-list-item.active {
    background: var(--bg-surface-alt);
    border-color: var(--border-color);
}

.vt-list-item strong,
.vt-list-item span {
    display: block;
}

.vt-list-item span {
    margin-top: 3px;
    color: var(--text-secondary);
    font-size: 11px;
}

.vt-add-button,
.vt-link-button {
    color: var(--color-primary-text);
}

.vt-editor {
    padding: 22px 24px;
    overflow-y: auto;
}

.vt-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.vt-editor label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    color: var(--text-secondary);
    font-size: 12px;
}

.vt-editor input,
.vt-editor select,
.vt-editor textarea,
.vt-section-heading select {
    box-sizing: border-box;
    width: 100%;
    padding: 8px 9px;
    color: var(--text-primary);
    background: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 5px;
}

.vt-description {
    margin-top: 14px;
}

.vt-section {
    padding-top: 18px;
    margin-top: 18px;
    border-top: 1px solid var(--border-color);
}

.vt-section h3 {
    font-size: 14px;
}

.vt-section-heading select {
    width: auto;
}

.vt-struct-field {
    display: grid;
    grid-template-columns: 1fr 180px 34px;
    gap: 8px;
    margin-top: 9px;
}

.vt-constraint {
    padding: 13px;
    margin-top: 10px;
    background: var(--bg-surface-alt);
    border: 1px solid var(--border-color);
    border-radius: 7px;
}

.vt-constraint label {
    margin-top: 10px;
}

.vt-checkbox {
    align-items: center;
    flex-direction: row !important;
}

.vt-checkbox input {
    width: auto;
}

.vt-icon-button,
.vt-link-button {
    padding: 6px;
    cursor: pointer;
    background: transparent;
    border: 0;
}

.vt-icon-button {
    display: inline-flex;
    color: var(--text-secondary);
}

.danger,
.vt-delete-button,
.vt-error,
.vt-reference-warning {
    color: #ef4444;
}

.vt-delete-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.vt-reference-warning {
    margin: 0;
    font-size: 12px;
}

.vt-delete-button {
    padding: 7px 10px;
    cursor: pointer;
    background: transparent;
    border: 1px solid #7f1d1d;
    border-radius: 5px;
}

.vt-delete-button:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

.vt-no-selection {
    display: flex;
    align-items: center;
    justify-content: center;
}

.vt-footer {
    min-height: 62px;
    padding: 10px 18px;
    border-top: 1px solid var(--border-color);
}

.vt-footer-actions {
    gap: 8px;
    margin-left: auto;
}

.vt-error {
    margin: 0 16px 0 0;
    font-size: 12px;
}

@media (max-width: 760px) {
    .vt-overlay {
        padding: 8px;
    }

    .vt-body {
        grid-template-columns: 1fr;
    }

    .vt-list {
        max-height: 180px;
        border-right: 0;
        border-bottom: 1px solid var(--border-color);
    }

    .vt-grid {
        grid-template-columns: 1fr;
    }
}
</style>
