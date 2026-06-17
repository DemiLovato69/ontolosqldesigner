<template>
    <div class="pipeline-transform-node">
        <Handle type="target" position="left" id="target-left" />
        <div class="pipeline-transform-node__icon">fx</div>
        <div class="pipeline-transform-node__body">
            <input
                v-if="editing"
                ref="labelInputRef"
                class="pipeline-transform-node__title-input"
                :value="draftLabel"
                @click.stop
                @mousedown.stop
                @input="draftLabel = $event.target.value"
                @blur="commitLabel"
                @keydown.enter.prevent="commitLabel"
                @keydown.esc.prevent="cancelEdit"
            />
            <strong v-else @mousedown.stop @click.stop="startEdit">{{ label || 'Pipeline Transform' }}</strong>
            <span>{{ inputCount }} refs -> {{ outputCount }} rows</span>
        </div>
        <button v-if="canEdit" class="pipeline-transform-node__delete" type="button" title="Delete transform" @click.stop="$emit('delete-node', id)">
            <SvgIcon name="trash" :size="13" />
        </button>
        <button v-if="canEdit" class="pipeline-transform-node__attach" type="button" title="Attach selected rows" @click.stop="$emit('attach-selected', id)">
            <SvgIcon name="pipe" :size="13" />
        </button>
        <Handle type="source" position="right" id="source-right" />
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Handle } from '@vue-flow/core'
import SvgIcon from '../SvgIcon.vue'

const props = defineProps({
    id: String,
    label: String,
    data: { type: Object, default: () => ({}) },
    canEdit: { type: Boolean, default: true },
})

const emit = defineEmits(['delete-node', 'attach-selected', 'update-label'])

const inputCount = computed(() => props.data?.sourceRowIds?.length ?? 0)
const outputCount = computed(() => props.data?.targetRowIds?.length ?? 0)
const editing = ref(false)
const draftLabel = ref(props.label || 'Pipeline Transform')
const labelInputRef = ref(null)

watch(() => props.label, (value) => {
    if (!editing.value) draftLabel.value = value || 'Pipeline Transform'
})

const startEdit = () => {
    if (!props.canEdit) return
    draftLabel.value = props.label || 'Pipeline Transform'
    editing.value = true
    nextTick(() => {
        labelInputRef.value?.focus()
        labelInputRef.value?.select()
    })
}

const commitLabel = () => {
    if (!editing.value) return
    editing.value = false
    emit('update-label', props.id, draftLabel.value)
}

const cancelEdit = () => {
    draftLabel.value = props.label || 'Pipeline Transform'
    editing.value = false
}
</script>

<style scoped>
.pipeline-transform-node {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 190px;
    padding: 10px 12px;
    border: 1px solid rgba(245, 158, 11, 0.9);
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(120, 53, 15, 0.9));
    color: #fffbeb;
    box-shadow: 0 16px 30px rgba(0, 0, 0, 0.24);
}

.pipeline-transform-node__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.14);
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 0.04em;
}

.pipeline-transform-node__body {
    display: flex;
    flex: 1;
    min-width: 0;
    flex-direction: column;
    gap: 2px;
}

.pipeline-transform-node__body strong {
    font-size: 12px;
    cursor: text;
}

.pipeline-transform-node__title-input {
    width: 100%;
    min-width: 0;
    padding: 2px 4px;
    border: 1px solid rgba(255, 251, 235, 0.32);
    border-radius: 5px;
    background: rgba(0, 0, 0, 0.18);
    color: #fffbeb;
    font-size: 12px;
    font-weight: 700;
}

.pipeline-transform-node__title-input:focus {
    outline: none;
    border-color: #fde68a;
}

.pipeline-transform-node__body span {
    color: rgba(255, 251, 235, 0.74);
    font-size: 11px;
}

.pipeline-transform-node__delete,
.pipeline-transform-node__attach {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.16);
    color: #fffbeb;
}

.pipeline-transform-node__attach {
    color: #fde68a;
}

.pipeline-transform-node :deep(.vue-flow__handle) {
    width: 12px;
    height: 12px;
    border: 2px solid rgba(255, 251, 235, 0.95);
    background: #f59e0b;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s, transform 0.15s;
}

.pipeline-transform-node:hover :deep(.vue-flow__handle),
.diagram-canvas.is-connecting .pipeline-transform-node :deep(.vue-flow__handle) {
    opacity: 1;
    pointer-events: all;
}

.pipeline-transform-node :deep(.vue-flow__handle:hover) {
    transform: scale(1.2);
}
</style>
