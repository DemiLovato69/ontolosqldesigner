<template>
    <div class="om-overlay" @click.self="$emit('close')">
        <section class="om-modal" role="dialog" aria-modal="true" aria-labelledby="shared-props-title">
            <header class="om-header">
                <div>
                    <h2 id="shared-props-title">Shared Property Types</h2>
                    <p>Reusable ontology property definitions for interfaces and objects.</p>
                </div>
                <button class="om-icon-button" type="button" aria-label="Close" @click="$emit('close')">
                    <SvgIcon name="close" :size="20" />
                </button>
            </header>

            <div class="om-body">
                <aside class="om-list">
                    <button
                        v-for="property in localSharedPropertyTypes"
                        :key="property.id"
                        type="button"
                        :class="['om-list-item', { active: selectedId === property.id }]"
                        @click="selectedId = property.id"
                    >
                        <strong>{{ property.displayName || property.apiName }}</strong>
                        <span>{{ property.apiName }} · {{ property.type }}</span>
                    </button>
                    <p v-if="!localSharedPropertyTypes.length" class="om-empty">No shared property types defined.</p>
                    <button v-if="canEdit" class="om-add-button" type="button" @click="createProperty">
                        + New shared property
                    </button>
                </aside>

                <main v-if="selectedProperty" class="om-editor">
                    <div class="om-grid">
                        <label>
                            <span>Display name</span>
                            <input v-model.trim="selectedProperty.displayName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>API name</span>
                            <input v-model.trim="selectedProperty.apiName" :disabled="!canEdit" />
                        </label>
                        <label>
                            <span>Type</span>
                            <select v-model="selectedProperty.type" :disabled="!canEdit">
                                <option v-for="type in PROPERTY_TYPES" :key="type" :value="type">{{ type }}</option>
                            </select>
                        </label>
                        <label class="om-checkbox">
                            <input v-model="selectedProperty.nullability.noNulls" type="checkbox" :disabled="!canEdit" />
                            <span>Required</span>
                        </label>
                    </div>
                    <label class="om-description">
                        <span>Description</span>
                        <textarea v-model="selectedProperty.description" :disabled="!canEdit" rows="3"></textarea>
                    </label>
                    <button v-if="canEdit" type="button" class="om-delete-button" @click="deleteSelected">
                        Delete shared property
                    </button>
                </main>
                <div v-else class="om-no-selection">Select or create a shared property type.</div>
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

const PROPERTY_TYPES = ['string', 'boolean', 'integer', 'long', 'double', 'decimal', 'date', 'timestamp', 'attachment', 'mediaReference', 'geohash', 'geoshape']

const props = defineProps({
    sharedPropertyTypes: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    initialSelectedKey: { type: String, default: null },
})
const emit = defineEmits(['update', 'close'])

const localSharedPropertyTypes = ref(JSON.parse(JSON.stringify(props.sharedPropertyTypes ?? [])))
const selectedId = ref(
    localSharedPropertyTypes.value.find(item => [item.id, item.apiName, item.displayName].includes(props.initialSelectedKey))?.id
    ?? localSharedPropertyTypes.value[0]?.id
    ?? null
)
const error = ref('')

const selectedProperty = computed(() => localSharedPropertyTypes.value.find(property => property.id === selectedId.value) ?? null)

const createProperty = () => {
    const index = localSharedPropertyTypes.value.length + 1
    const property = {
        id: crypto.randomUUID(),
        apiName: `sharedProperty${index}`,
        displayName: `Shared Property ${index}`,
        description: '',
        type: 'string',
        nullability: { noNulls: false, noEmptyCollections: false },
    }
    localSharedPropertyTypes.value.push(property)
    selectedId.value = property.id
}

const deleteSelected = () => {
    localSharedPropertyTypes.value = localSharedPropertyTypes.value.filter(property => property.id !== selectedId.value)
    selectedId.value = localSharedPropertyTypes.value[0]?.id ?? null
}

const applyChanges = () => {
    error.value = ''
    const seen = new Set()
    for (const property of localSharedPropertyTypes.value) {
        if (!property.apiName?.trim()) {
            error.value = 'Every shared property type needs an API name.'
            return
        }
        if (seen.has(property.apiName)) {
            error.value = `Duplicate API name: ${property.apiName}`
            return
        }
        seen.add(property.apiName)
    }
    emit('update', JSON.parse(JSON.stringify(localSharedPropertyTypes.value)))
    emit('close')
}
</script>

<style scoped src="./ontology-modal.css"></style>
