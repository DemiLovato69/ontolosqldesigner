<template>
    <div class="om-overlay" @click.self="$emit('close')">
        <section class="om-modal" role="dialog" aria-modal="true" aria-labelledby="actions-title">
            <header class="om-header">
                <div>
                    <h2 id="actions-title">Custom Actions</h2>
                    <p>Build generic and function-backed Ontology as Code actions.</p>
                </div>
                <button class="om-icon-button" type="button" aria-label="Close" @click="$emit('close')">
                    <SvgIcon name="close" :size="20" />
                </button>
            </header>

            <div class="om-body">
                <aside class="om-list">
                    <button
                        v-for="action in localCustomActions"
                        :key="action.id"
                        type="button"
                        :class="['om-list-item', { active: selectedId === action.id }]"
                        @click="selectedId = action.id"
                    >
                        <strong>{{ action.displayName || action.apiName }}</strong>
                        <span>{{ action.apiName }} · {{ action.actionType || 'rule-backed' }}</span>
                    </button>
                    <p v-if="!localCustomActions.length" class="om-empty">No custom actions defined.</p>
                    <button v-if="canEdit" class="om-add-button" type="button" @click="createAction">+ New action</button>
                </aside>

                <main v-if="selectedAction" class="om-editor">
                    <div class="om-grid">
                        <label>
                            <span>Display name</span>
                            <input v-model.trim="selectedAction.displayName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>API name</span>
                            <input v-model.trim="selectedAction.apiName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>Kind</span>
                            <select v-model="selectedAction.actionType" :disabled="!canEdit" @change="onActionTypeChange">
                                <option value="rules">Rule-backed</option>
                                <option value="function">Function-backed</option>
                            </select>
                        </label>
                    </div>
                    <label class="om-description">
                        <span>Description</span>
                        <textarea v-model="selectedAction.description" :disabled="!canEdit" rows="2"></textarea>
                    </label>

                    <section v-if="selectedAction.actionType === 'function'" class="om-section">
                        <h3>Function backing</h3>
                        <div class="om-grid">
                            <label>
                                <span>Function RID</span>
                                <input v-model.trim="selectedAction.functionRid" :disabled="!canEdit" placeholder="ri.function-registry.main.function..." />
                            </label>
                            <label>
                                <span>Function version</span>
                                <input v-model.trim="selectedAction.functionVersion" :disabled="!canEdit" placeholder="latest" />
                            </label>
                        </div>
                    </section>

                    <section v-else class="om-section">
                        <div class="om-section-heading">
                            <h3>Rules</h3>
                            <select v-if="canEdit" v-model="ruleToAdd" @change="addRule">
                                <option value="">+ Add rule</option>
                                <option value="addObjectRule">Add object</option>
                                <option value="modifyObjectRule">Modify object</option>
                                <option value="deleteObjectRule">Delete object</option>
                                <option value="addLinkRule">Add link</option>
                                <option value="deleteLinkRule">Delete link</option>
                            </select>
                        </div>
                        <article v-for="rule in selectedAction.rules" :key="rule.id" class="om-card">
                            <div class="om-section-heading">
                                <strong>{{ rule.type }}</strong>
                                <button v-if="canEdit" type="button" class="om-icon-button danger" @click="removeRule(rule.id)">
                                    <SvgIcon name="trash" :size="15" />
                                </button>
                            </div>
                            <div class="om-grid">
                                <label>
                                    <span>Target object/link API</span>
                                    <input v-model.trim="rule.target" :disabled="!canEdit" :list="`tables-${rule.id}`" />
                                    <datalist :id="`tables-${rule.id}`">
                                        <option v-for="table in tables" :key="table.id" :value="table.label" />
                                    </datalist>
                                </label>
                                <label>
                                    <span>Parameter mapping JSON</span>
                                    <textarea v-model="rule.mappingJson" :disabled="!canEdit" rows="3" placeholder='{"propertyApiName":"parameterName"}'></textarea>
                                </label>
                            </div>
                        </article>
                        <p v-if="!selectedAction.rules.length" class="om-empty">No rules.</p>
                    </section>

                    <section class="om-section">
                        <div class="om-section-heading">
                            <h3>Parameters</h3>
                            <button v-if="canEdit" type="button" class="om-link-button" @click="addParameter">+ Add parameter</button>
                        </div>
                        <div v-for="parameter in selectedAction.parameters" :key="parameter.id" class="om-inline-row">
                            <input v-model.trim="parameter.apiName" :disabled="!canEdit" placeholder="parameterName" />
                            <select v-model="parameter.type" :disabled="!canEdit">
                                <option v-for="type in PARAMETER_TYPES" :key="type" :value="type">{{ type }}</option>
                            </select>
                            <button v-if="canEdit" type="button" class="om-icon-button danger" @click="removeParameter(parameter.id)">
                                <SvgIcon name="trash" :size="15" />
                            </button>
                        </div>
                    </section>

                    <button v-if="canEdit" type="button" class="om-delete-button" @click="deleteSelected">Delete action</button>
                </main>
                <div v-else class="om-no-selection">Select or create a custom action.</div>
            </div>

            <footer class="om-footer">
                <p v-if="error" class="om-error">{{ error }}</p>
                <div class="om-footer-actions">
                    <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
                    <button v-if="canEdit" type="button" class="btn btn-primary" @click="applyChanges">Apply changes</button>
                </div>
            </footer>
        </section>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import SvgIcon from '../SvgIcon.vue'

const PARAMETER_TYPES = ['string', 'boolean', 'integer', 'long', 'double', 'decimal', 'date', 'timestamp', 'objectTypeReference', 'attachment']

const props = defineProps({
    customActions: { type: Array, default: () => [] },
    tables: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    initialSelectedKey: { type: String, default: null },
})
const emit = defineEmits(['update', 'close'])

const localCustomActions = ref(JSON.parse(JSON.stringify(props.customActions ?? [])).map(withActionDefaults))
const selectedId = ref(
    localCustomActions.value.find(item => [item.id, item.apiName, item.displayName].includes(props.initialSelectedKey))?.id
    ?? localCustomActions.value[0]?.id
    ?? null
)
const ruleToAdd = ref('')
const error = ref('')

const selectedAction = computed(() => localCustomActions.value.find(action => action.id === selectedId.value) ?? null)

const createAction = () => {
    const index = localCustomActions.value.length + 1
    const action = { id: crypto.randomUUID(), apiName: `customAction${index}`, displayName: `Custom Action ${index}`, description: '', actionType: 'rules', parameters: [], rules: [] }
    localCustomActions.value.push(action)
    selectedId.value = action.id
}

const onActionTypeChange = () => {
    if (selectedAction.value.actionType === 'function') selectedAction.value.rules = []
}

const addParameter = () => selectedAction.value?.parameters.push({ id: crypto.randomUUID(), apiName: 'parameter', type: 'string' })
const removeParameter = (id) => selectedAction.value.parameters = selectedAction.value.parameters.filter(parameter => parameter.id !== id)

const addRule = () => {
    if (!ruleToAdd.value || !selectedAction.value || selectedAction.value.actionType === 'function') return
    selectedAction.value.rules.push({ id: crypto.randomUUID(), type: ruleToAdd.value, target: '', mappingJson: '{}' })
    ruleToAdd.value = ''
}
const removeRule = (id) => selectedAction.value.rules = selectedAction.value.rules.filter(rule => rule.id !== id)

const deleteSelected = () => {
    localCustomActions.value = localCustomActions.value.filter(action => action.id !== selectedId.value)
    selectedId.value = localCustomActions.value[0]?.id ?? null
}

const toMakerAction = (action) => {
    const makerParameters = Object.fromEntries((action.parameters ?? []).filter(parameter => parameter.apiName).map(parameter => [parameter.apiName, { type: parameter.type }]))
    if (action.actionType === 'function') {
        return {
            id: action.id ?? crypto.randomUUID(),
            apiName: action.apiName,
            displayName: action.displayName,
            description: action.description,
            actionType: action.actionType,
            parameters: action.parameters ?? [],
            functionRid: action.functionRid ?? '',
            functionVersion: action.functionVersion ?? 'latest',
            makerParameters,
            function: { rid: action.functionRid, version: action.functionVersion || 'latest' },
        }
    }
    return {
        id: action.id ?? crypto.randomUUID(),
        apiName: action.apiName,
        displayName: action.displayName,
        description: action.description,
        actionType: action.actionType,
        parameters: action.parameters ?? [],
        rules: action.rules ?? [],
        makerParameters,
        logic: {
            rules: (action.rules ?? []).map((rule) => ({
                type: rule.type,
                target: rule.target,
                mapping: parseJson(rule.mappingJson, {}),
            })),
        },
    }
}

function withActionDefaults(action) {
    return {
        id: action.id ?? crypto.randomUUID(),
        apiName: action.apiName ?? '',
        displayName: action.displayName ?? action.apiName ?? '',
        description: action.description ?? '',
        actionType: action.actionType ?? (action.function ? 'function' : 'rules'),
        functionRid: action.functionRid ?? action.function?.rid ?? '',
        functionVersion: action.functionVersion ?? action.function?.version ?? 'latest',
        ...action,
        parameters: Array.isArray(action.parameters)
            ? action.parameters.map(parameter => ({ id: parameter.id ?? crypto.randomUUID(), ...parameter }))
            : parametersFromObject(action.parameters),
        rules: Array.isArray(action.rules)
            ? action.rules.map(rule => ({ id: rule.id ?? crypto.randomUUID(), mappingJson: rule.mappingJson ?? JSON.stringify(rule.mapping ?? {}, null, 2), ...rule }))
            : (action.logic?.rules ?? []).map(rule => ({ id: crypto.randomUUID(), mappingJson: JSON.stringify(rule.mapping ?? {}, null, 2), ...rule })),
    }
}

function parametersFromObject(parameters) {
    if (!parameters || typeof parameters !== 'object') return []
    return Object.entries(parameters).map(([apiName, definition]) => ({
        id: crypto.randomUUID(),
        apiName,
        type: definition?.type ?? 'string',
    }))
}

const parseJson = (value, fallback) => {
    try {
        return JSON.parse(value || '{}')
    } catch {
        return fallback
    }
}

const applyChanges = () => {
    error.value = ''
    for (const action of localCustomActions.value) {
        if (!action.apiName?.trim()) {
            error.value = 'Every action needs an API name.'
            return
        }
        for (const rule of action.rules ?? []) {
            try {
                JSON.parse(rule.mappingJson || '{}')
            } catch {
                error.value = `Rule mapping on ${action.apiName} must be valid JSON.`
                return
            }
        }
    }
    emit('update', localCustomActions.value.map(toMakerAction))
    emit('close')
}
</script>

<style scoped src="./ontology-modal.css"></style>
