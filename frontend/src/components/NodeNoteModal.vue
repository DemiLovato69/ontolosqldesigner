<template>
    <div
        ref="modalRef"
        class="node-note-modal"
        @mousedown.stop
        @pointerdown.stop
    >
        <label class="node-note-modal__label">{{ title }}</label>
        <textarea
            ref="textareaRef"
            v-model="draft"
            class="node-note-modal__input"
            :readonly="!canEdit"
            :placeholder="canEdit ? 'Add a note…' : 'No note'"
            rows="4"
            @keydown.meta.enter.prevent="save"
            @keydown.ctrl.enter.prevent="save"
        />
        <div class="node-note-modal__actions">
            <button v-if="canEdit" class="node-note-modal__save" @click="save">Save</button>
            <button class="node-note-modal__close" @click="emit('close')">{{ canEdit ? 'Cancel' : 'Close' }}</button>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { onClickOutside } from '@vueuse/core'

const props = defineProps({
    title: { type: String, default: 'Note' },
    note: { type: String, default: '' },
    canEdit: { type: Boolean, default: true },
    ignore: { type: Array, default: () => [] },
})

const emit = defineEmits(['save', 'close'])
const modalRef = ref(null)
const textareaRef = ref(null)
const draft = ref(props.note)

onClickOutside(modalRef, () => emit('close'), { ignore: props.ignore })

onMounted(() => {
    if (props.canEdit) nextTick(() => textareaRef.value?.focus())
})

const save = () => {
    emit('save', draft.value.trim())
    emit('close')
}
</script>

<style scoped>
.node-note-modal {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    z-index: 30;
    width: 260px;
    padding: 10px;
    border: 1px solid var(--border-strong);
    border-radius: 6px;
    background: var(--bg-surface);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

.node-note-modal__label {
    display: block;
    margin-bottom: 6px;
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 600;
}

.node-note-modal__input {
    box-sizing: border-box;
    width: 100%;
    resize: vertical;
    padding: 7px 8px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--input-bg);
    color: var(--text-primary);
    font: inherit;
    font-size: 13px;
    line-height: 1.4;
}

.node-note-modal__input:focus {
    outline: none;
    border-color: var(--color-primary);
}

.node-note-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    margin-top: 8px;
}

.node-note-modal__actions button {
    padding: 4px 9px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.node-note-modal__save {
    background: var(--color-primary);
    color: white;
}

.node-note-modal__close {
    background: var(--bg-surface-alt);
    color: var(--text-secondary);
}
</style>
