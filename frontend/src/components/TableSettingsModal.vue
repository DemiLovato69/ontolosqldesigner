<template>
    <Teleport to="body">
        <div
            ref="modalRef"
            class="table-settings-modal"
            :style="modalStyle"
            @mousedown.stop
            @pointerdown.stop
        >
            <p class="table-settings-modal__title">Ontology Settings</p>

            <label class="table-settings-modal__field">
                <span>Title property</span>
                <select
                    :value="titlePropertyRowId || ''"
                    :disabled="!canEdit"
                    @change="setTitleProperty($event.target.value)"
                >
                    <option value="">Automatic</option>
                    <option v-for="column in columns" :key="column.id" :value="column.id">
                        {{ column.label }}
                    </option>
                </select>
            </label>

            <p class="table-settings-modal__section">Generated Actions</p>
            <p class="table-settings-modal__hint">Generate Maker CRUD action definitions for this object type.</p>

            <label class="table-settings-modal__row">
                <span>Create action</span>
                <input type="checkbox" :checked="actions.create" :disabled="!canEdit" @change="toggle('create')" />
            </label>
            <label class="table-settings-modal__row">
                <span>Modify action</span>
                <input type="checkbox" :checked="actions.modify" :disabled="!canEdit" @change="toggle('modify')" />
            </label>
            <label class="table-settings-modal__row">
                <span>Delete action</span>
                <input type="checkbox" :checked="actions.delete" :disabled="!canEdit" @change="toggle('delete')" />
            </label>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { onClickOutside } from '@vueuse/core'

const props = defineProps({
    actions: { type: Object, default: () => ({}) },
    titlePropertyRowId: { type: String, default: '' },
    columns: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: true },
    anchor: { type: Object, default: null },
    ignore: { type: Array, default: () => [] },
})

const emit = defineEmits(['change', 'close'])
const modalRef = ref(null)
const position = ref({ top: 0, left: 0 })

const modalStyle = computed(() => ({
    top: `${position.value.top}px`,
    left: `${position.value.left}px`,
}))

onClickOutside(modalRef, () => emit('close'), { ignore: props.ignore })

onMounted(() => {
    const rect = props.anchor?.getBoundingClientRect()
    if (rect) {
        const width = 240
        position.value = {
            top: rect.bottom + 8,
            left: Math.max(12, Math.min(rect.right - width, window.innerWidth - width - 12)),
        }
    }
})

const toggle = (key) => {
    emit('change', {
        create: !!props.actions.create,
        modify: !!props.actions.modify,
        delete: !!props.actions.delete,
        titlePropertyRowId: props.titlePropertyRowId || null,
        [key]: !props.actions[key],
    })
}

const setTitleProperty = (titlePropertyRowId) => {
    emit('change', {
        create: !!props.actions.create,
        modify: !!props.actions.modify,
        delete: !!props.actions.delete,
        titlePropertyRowId: titlePropertyRowId || null,
    })
}
</script>

<style scoped>
.table-settings-modal {
    position: fixed;
    z-index: 1000;
    width: 240px;
    padding: 10px;
    border: 1px solid var(--border-strong);
    border-radius: 6px;
    background: var(--bg-surface);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

.table-settings-modal__title {
    margin: 0 0 10px;
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 700;
}

.table-settings-modal__section {
    margin: 12px 0 4px;
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.table-settings-modal__hint {
    margin: 0 0 10px;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.35;
}

.table-settings-modal__field {
    display: grid;
    gap: 5px;
    color: var(--text-primary);
    font-size: 12px;
}

.table-settings-modal__field select {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--border-strong);
    border-radius: 4px;
    color: var(--text-primary);
    background: var(--bg-surface);
}

.table-settings-modal__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 6px 0;
    color: var(--text-primary);
    font-size: 13px;
}
</style>
