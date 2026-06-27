<template>
    <header class="dh">
        <div class="dh-group dh-group--primary">
            <button v-if="canEdit" class="dh-btn" @click="$emit('add-table')" title="Add Table (Ctrl/⌘+Shift+A)">
                <SvgIcon name="plus" :size="17" />
            </button>
            <button v-if="canEdit" class="dh-btn dh-btn--ref-table" @click="$emit('add-reference-table')" title="Add Reference Table">
                <SvgIcon name="plus" :size="17" />
                <span>REF</span>
            </button>
            <div v-if="canEdit" ref="pipelineMenuRef" class="dh-menu">
                <button class="dh-btn" type="button" @click="pipelineMenuOpen = !pipelineMenuOpen" title="Pipeline tools" aria-label="Pipeline tools">
                    <SvgIcon name="pipe-plus" :size="17" />
                </button>
                <div v-if="pipelineMenuOpen" class="dh-menu__content">
                    <button type="button" @click="emitPipelineCreate" title="Add Pipeline" aria-label="Add Pipeline">
                        <SvgIcon name="pipe-plus" :size="17" />
                    </button>
                    <button type="button" @click="emitReferenceJsonImport" title="Import Reference Schema" aria-label="Import Reference Schema">
                        <SvgIcon name="pipe-json" :size="17" />
                    </button>
                </div>
            </div>
        </div>
        <div class="dh-group dh-group--actions">
            <button
                v-if="dbType === 'ontology'"
                class="dh-btn"
                @click="$emit('show-value-types')"
                title="Value Types"
                aria-label="Manage value types"
            >
                <SvgIcon name="value-type" :size="17" />
            </button>
            <button
                v-if="dbType === 'ontology'"
                class="dh-btn"
                @click="$emit('show-shared-property-types')"
                title="Shared Property Types"
                aria-label="Manage shared property types"
            >
                <SvgIcon name="database" :size="17" />
            </button>
            <button
                v-if="dbType === 'ontology'"
                class="dh-btn"
                @click="$emit('show-interfaces')"
                title="Interfaces"
                aria-label="Manage interfaces"
            >
                <SvgIcon name="interface" :size="17" />
            </button>
            <button
                v-if="dbType === 'ontology'"
                class="dh-btn"
                @click="$emit('show-custom-actions')"
                title="Custom Actions"
                aria-label="Manage custom actions"
            >
                <SvgIcon name="bolt" :size="17" />
            </button>
            <button
                v-if="dbType === 'ontology' && !isDemo"
                class="dh-btn"
                @click="$emit('show-foundry')"
                title="Foundry Browser"
                aria-label="Open Foundry browser"
            >
                <SvgIcon name="globe" :size="17" />
            </button>
            <button
                v-if="dbType === 'ontology' && !isDemo"
                class="dh-btn"
                @click="$emit('show-agent')"
                title="Diagram Agent"
                aria-label="Open diagram agent"
            >
                <SvgIcon name="sparkles" :size="17" />
            </button>
            <div v-if="isOwner" class="dh-share-wrap">
                <button class="dh-btn" @click="$emit('show-share')" title="Share">
                    <SvgIcon name="share" :size="17" />
                </button>
                <span v-if="hasPendingVisitors" class="dh-pending-dot"></span>
            </div>
            <button
                class="dh-btn"
                @click="$emit('show-help')"
                title="Keyboard Shortcuts"
                aria-label="Show keyboard shortcuts"
            >
                <SvgIcon name="help" :size="17" />
            </button>
        </div>
    </header>
</template>

<script setup>
import { ref } from 'vue'
import { onClickOutside } from '@vueuse/core'
import SvgIcon from '../SvgIcon.vue'

const props = defineProps({
    canEdit: Boolean,
    isOwner: Boolean,
    isDemo: Boolean,
    dbType: { type: String, default: 'mysql' },
    hasPendingVisitors: { type: Boolean, default: false },
})
const emit = defineEmits(['add-table', 'add-reference-table', 'add-pipeline', 'open-reference-json-import', 'show-share', 'show-help', 'show-value-types', 'show-shared-property-types', 'show-interfaces', 'show-custom-actions', 'show-foundry', 'show-agent'])

const pipelineMenuOpen = ref(false)
const pipelineMenuRef = ref(null)
onClickOutside(pipelineMenuRef, () => { pipelineMenuOpen.value = false })

const emitPipelineCreate = () => {
    pipelineMenuOpen.value = false
    emit('add-pipeline')
}

const emitReferenceJsonImport = () => {
    pipelineMenuOpen.value = false
    emit('open-reference-json-import')
}
</script>

<style scoped>
.dh {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 0.65rem;
    height: 48px;
    flex-shrink: 0;
    background: rgba(38, 38, 38, 0.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border-light);
    position: relative;
    z-index: 50;
}

.dh-group {
    display: flex;
    gap: 4px;
    align-items: center;
}

.dh-group--primary {
    margin-right: auto;
}

.dh-group--actions {
    margin-left: auto;
}

.dh-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 7px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: background 120ms, border-color 120ms, color 120ms;
}

.dh-btn:hover:not(:disabled) {
    background: var(--bg-surface-alt);
    border-color: var(--border-color);
}

.dh-btn:disabled {
    opacity: 0.35;
    cursor: default;
}

.dh-btn--ref-table {
    position: relative;
}

.dh-btn--ref-table span {
    position: absolute;
    right: 2px;
    bottom: 1px;
    padding: 0 2px;
    border-radius: 3px;
    background: rgba(139, 92, 246, 0.95);
    color: #fff;
    font-size: 7px;
    font-weight: 800;
    line-height: 10px;
    letter-spacing: 0.04em;
}

.dh-menu {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.dh-menu__content {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    display: flex;
    gap: 4px;
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: 9px;
    background: var(--bg-surface);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.34);
    z-index: 70;
}

.dh-menu__content button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--text-primary);
    cursor: pointer;
}

.dh-menu__content button:hover {
    background: var(--bg-surface-alt);
}

.dh-share-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.dh-pending-dot {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #f97316;
    border: 1.5px solid var(--bg-page);
    pointer-events: none;
}
</style>
