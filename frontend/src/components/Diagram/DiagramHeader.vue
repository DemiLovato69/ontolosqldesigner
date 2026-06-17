<template>
    <header class="dh">
        <div class="dh-group">
            <button v-if="canEdit" class="dh-btn" @click="$emit('add-table')" title="Add Table (Ctrl/⌘+Shift+A)">
                <SvgIcon name="plus" :size="17" />
            </button>
            <button v-if="canEdit || isDemo" class="dh-btn" @click="$emit('import')" title="Import schema">
                <SvgIcon name="import" :size="17" />
            </button>
            <button class="dh-btn" @click="$emit('export')" title="Export">
                <SvgIcon name="export" :size="17" />
            </button>
            <span
                v-if="sharingStatus"
                class="dh-share-status"
                :class="`dh-share-status--${sharingStatus.kind}`"
                :title="sharingStatus.title"
                :aria-label="sharingStatus.title"
            >
                <SvgIcon :name="sharingStatus.icon" :size="15" />
            </span>
            <span v-if="!isOwner && !isDemo" class="dh-name">{{ diagramName }}</span>
        </div>
        <div class="dh-group">
            <div v-if="canEdit" class="dh-save-wrap">
                <button class="dh-btn" @click="$emit('save')" title="Save (Ctrl/⌘+S)" :disabled="!isDemo && isSaved">
                    <SvgIcon name="save" :size="17" />
                </button>
                <span v-if="!isDemo" class="dh-save-dot" :class="{ 'dh-save-dot--saved': isSaved }"></span>
            </div>
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
import { computed } from 'vue'
import SvgIcon from '../SvgIcon.vue'

const props = defineProps({
    canEdit: Boolean,
    isOwner: Boolean,
    isDemo: Boolean,
    isSaved: Boolean,
    diagramName: String,
    dbType: { type: String, default: 'mysql' },
    shareAccess: { type: String, default: null },
    inLibrary: { type: Boolean, default: false },
    hasPendingVisitors: { type: Boolean, default: false },
})
defineEmits(['add-table', 'import', 'export', 'save', 'show-share', 'show-help', 'show-value-types', 'show-shared-property-types', 'show-interfaces', 'show-custom-actions'])

const sharingStatus = computed(() => {
    if (props.isDemo) return null
    if (props.inLibrary) {
        return {
            kind: 'public',
            icon: 'globe',
            title: props.shareAccess === 'write'
                ? 'Company-wide diagram: others can edit'
                : 'Company-wide diagram: others can view',
        }
    }
    if (props.shareAccess) {
        return {
            kind: 'shared',
            icon: 'share',
            title: props.shareAccess === 'write'
                ? 'Shared diagram: others can edit'
                : 'Shared diagram: restricted access',
        }
    }
    return null
})
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

.dh-name {
    font-size: 0.82rem;
    color: var(--text-secondary);
    margin-left: 4px;
}

.dh-share-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    margin-left: 2px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    background: color-mix(in srgb, var(--bg-surface-alt) 80%, transparent);
}

.dh-share-status--public {
    border-color: color-mix(in srgb, var(--color-primary) 55%, var(--border-color));
    color: var(--color-primary);
    background: color-mix(in srgb, var(--color-primary) 14%, transparent);
}

.dh-share-status--shared {
    border-color: color-mix(in srgb, #60a5fa 45%, var(--border-color));
    color: #93c5fd;
    background: color-mix(in srgb, #60a5fa 12%, transparent);
}

.dh-save-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.dh-save-dot {
    position: absolute;
    top: 6px;
    right: 4px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #f59e0b;
    border: 1.5px solid var(--bg-page);
    pointer-events: none;
}

.dh-save-dot--saved {
    background: var(--color-primary-text);
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
