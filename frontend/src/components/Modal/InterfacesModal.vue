<template>
    <div class="om-overlay" @click.self="$emit('close')">
        <section class="om-modal" role="dialog" aria-modal="true" aria-labelledby="interfaces-title">
            <header class="om-header">
                <div>
                    <h2 id="interfaces-title">Interfaces</h2>
                    <p>Define interface contracts and interface-to-interface link constraints.</p>
                </div>
                <button class="om-icon-button" type="button" aria-label="Close" @click="$emit('close')">
                    <SvgIcon name="close" :size="20" />
                </button>
            </header>

            <div class="om-body">
                <aside class="om-list">
                    <button
                        v-for="item in combinedList"
                        :key="item.key"
                        type="button"
                        :class="['om-list-item', { active: selectedKey === item.key }]"
                        @click="selectedKey = item.key"
                    >
                        <strong>{{ item.displayName }}</strong>
                        <span>{{ item.kind }} · {{ item.apiName }}</span>
                    </button>
                    <p v-if="!combinedList.length" class="om-empty">No interfaces or constraints defined.</p>
                    <button v-if="canEdit" class="om-add-button" type="button" @click="createInterface">+ New interface</button>
                    <button v-if="canEdit" class="om-add-button secondary" type="button" @click="createConstraint">+ New constraint</button>
                </aside>

                <main v-if="selectedInterface" class="om-editor">
                    <div class="om-grid">
                        <label>
                            <span>Display name</span>
                            <input v-model.trim="selectedInterface.displayName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>API name</span>
                            <input v-model.trim="selectedInterface.apiName" :disabled="!canEdit" />
                        </label>
                        <label class="om-checkbox">
                            <input v-model="selectedInterface.searchable" type="checkbox" :disabled="!canEdit" />
                            <span>Searchable</span>
                        </label>
                    </div>
                    <label class="om-description">
                        <span>Description</span>
                        <textarea v-model="selectedInterface.description" :disabled="!canEdit" rows="3"></textarea>
                    </label>
                    <section class="om-section">
                        <div class="om-section-heading">
                            <h3>Properties</h3>
                            <button v-if="canEdit" type="button" class="om-link-button" @click="addInterfaceProperty">+ Add property</button>
                        </div>
                        <div v-for="property in selectedInterface.properties" :key="property.id" class="om-inline-row">
                            <input v-model.trim="property.apiName" :disabled="!canEdit" placeholder="propertyApiName" />
                            <select v-model="property.type" :disabled="!canEdit">
                                <option v-for="type in PROPERTY_TYPES" :key="type" :value="type">{{ type }}</option>
                            </select>
                            <button v-if="canEdit" type="button" class="om-icon-button danger" @click="removeInterfaceProperty(property.id)">
                                <SvgIcon name="trash" :size="15" />
                            </button>
                        </div>
                    </section>
                    <button v-if="canEdit" type="button" class="om-delete-button" @click="deleteSelected">Delete interface</button>
                </main>

                <main v-else-if="selectedConstraint" class="om-editor">
                    <div class="om-grid">
                        <label>
                            <span>Display name</span>
                            <input v-model.trim="selectedConstraint.displayName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>API name</span>
                            <input v-model.trim="selectedConstraint.apiName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>From interface</span>
                            <input v-model.trim="selectedConstraint.from" :disabled="!canEdit" placeholder="SourceInterface" />
                        </label>
                        <label>
                            <span>To interface</span>
                            <input v-model.trim="selectedConstraint.toMany.interface" :disabled="!canEdit" placeholder="TargetInterface" />
                        </label>
                        <label class="om-checkbox">
                            <input v-model="selectedConstraint.required" type="checkbox" :disabled="!canEdit" />
                            <span>Required</span>
                        </label>
                    </div>
                    <label class="om-description">
                        <span>Description</span>
                        <textarea v-model="selectedConstraint.description" :disabled="!canEdit" rows="3"></textarea>
                    </label>
                    <button v-if="canEdit" type="button" class="om-delete-button" @click="deleteSelected">Delete constraint</button>
                </main>

                <div v-else class="om-no-selection">Select or create an interface or constraint.</div>
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

const PROPERTY_TYPES = ['string', 'boolean', 'integer', 'long', 'double', 'decimal', 'date', 'timestamp']

const props = defineProps({
    interfaces: { type: Array, default: () => [] },
    interfaceLinkConstraints: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    initialSelectedKey: { type: String, default: null },
})
const emit = defineEmits(['update', 'close'])

const localInterfaces = ref(JSON.parse(JSON.stringify(props.interfaces ?? [])).map(withInterfaceDefaults))
const localConstraints = ref(JSON.parse(JSON.stringify(props.interfaceLinkConstraints ?? [])).map(withConstraintDefaults))
const selectedKey = ref(resolveInitialSelectedKey())
const error = ref('')

const combinedList = computed(() => [
    ...localInterfaces.value.map(item => ({ key: `interface:${item.id}`, kind: 'Interface', apiName: item.apiName, displayName: item.displayName || item.apiName })),
    ...localConstraints.value.map(item => ({ key: `constraint:${item.id}`, kind: 'Constraint', apiName: item.apiName, displayName: item.displayName || item.apiName })),
])
const selectedInterface = computed(() => selectedKey.value?.startsWith('interface:') ? localInterfaces.value.find(item => item.id === selectedKey.value.slice(10)) ?? null : null)
const selectedConstraint = computed(() => selectedKey.value?.startsWith('constraint:') ? localConstraints.value.find(item => item.id === selectedKey.value.slice(11)) ?? null : null)

function resolveInitialSelectedKey() {
    const requested = props.initialSelectedKey ?? ''
    if (requested.startsWith('interface:')) {
        const key = requested.slice(10)
        const item = localInterfaces.value.find(item => [item.id, item.apiName, item.displayName].includes(key))
        if (item) return `interface:${item.id}`
    }
    if (requested.startsWith('constraint:')) {
        const key = requested.slice(11)
        const item = localConstraints.value.find(item => [item.id, item.apiName, item.displayName].includes(key))
        if (item) return `constraint:${item.id}`
    }
    return localInterfaces.value[0] ? `interface:${localInterfaces.value[0].id}` : localConstraints.value[0] ? `constraint:${localConstraints.value[0].id}` : null
}

const createInterface = () => {
    const index = localInterfaces.value.length + 1
    const item = { id: crypto.randomUUID(), apiName: `Interface${index}`, displayName: `Interface ${index}`, description: '', searchable: true, properties: [] }
    localInterfaces.value.push(item)
    selectedKey.value = `interface:${item.id}`
}

const createConstraint = () => {
    const index = localConstraints.value.length + 1
    const item = { id: crypto.randomUUID(), apiName: `interfaceLinkConstraint${index}`, displayName: `Interface Link Constraint ${index}`, description: '', from: '', toMany: { interface: '' }, required: false }
    localConstraints.value.push(item)
    selectedKey.value = `constraint:${item.id}`
}

const addInterfaceProperty = () => selectedInterface.value?.properties.push({ id: crypto.randomUUID(), apiName: 'property', type: 'string' })
const removeInterfaceProperty = (id) => selectedInterface.value.properties = selectedInterface.value.properties.filter(property => property.id !== id)

const deleteSelected = () => {
    if (selectedInterface.value) localInterfaces.value = localInterfaces.value.filter(item => item.id !== selectedInterface.value.id)
    if (selectedConstraint.value) localConstraints.value = localConstraints.value.filter(item => item.id !== selectedConstraint.value.id)
    selectedKey.value = combinedList.value[0]?.key ?? null
}

function withInterfaceDefaults(item) {
    return {
        id: item.id ?? crypto.randomUUID(),
        apiName: item.apiName ?? '',
        displayName: item.displayName ?? item.apiName ?? '',
        description: item.description ?? '',
        searchable: item.searchable ?? true,
        ...item,
        properties: Array.isArray(item.properties)
            ? item.properties.map(property => ({ id: property.id ?? crypto.randomUUID(), ...property }))
            : [],
    }
}

function withConstraintDefaults(item) {
    return {
        id: item.id ?? crypto.randomUUID(),
        apiName: item.apiName ?? '',
        displayName: item.displayName ?? item.apiName ?? '',
        description: item.description ?? '',
        from: item.from ?? '',
        toMany: item.toMany ?? { interface: '' },
        required: item.required ?? false,
        ...item,
    }
}

const applyChanges = () => {
    error.value = ''
    for (const item of [...localInterfaces.value, ...localConstraints.value]) {
        if (!item.apiName?.trim()) {
            error.value = 'Every interface and constraint needs an API name.'
            return
        }
    }
    emit('update', { interfaces: JSON.parse(JSON.stringify(localInterfaces.value)), interfaceLinkConstraints: JSON.parse(JSON.stringify(localConstraints.value)) })
    emit('close')
}
</script>

<style scoped src="./ontology-modal.css"></style>
