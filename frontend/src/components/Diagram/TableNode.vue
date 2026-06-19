<template>
    <input
        :class="['input input_designer_table', { 'input_designer_table--reference': isReference }]"
        :value="label"
        @mousedown.stop
        @click="canEdit && (data.editing = true)"
        @blur="(e) => { data.editing = false; $emit('update-label', id, label); e.target.scrollLeft = 0; }"
        @input="$emit('update-label', id, $event.target.value)"
        :readonly="!data.editing || !canEdit"
    />

    <span v-if="isReference" class="reference_table_badge">REF</span>

    <label v-if="canEdit" class="table_color_picker" @mousedown.stop>
        <input
            type="color"
            :value="data.color || '#898989'"
            @input="$emit('update-color', id, $event.target.value)"
            class="table_color_input"
        />
    </label>

    <button v-if="canEdit" class="table_button table_button--add-row" @mousedown.stop @click="$emit('add-row', id)">
        <SvgIcon name="plus" :size="14" />
    </button>

    <button v-if="canEdit" class="table_button table_button--copy" @mousedown.stop @click="$emit('copy-table', id)">
        <SvgIcon name="copy" :size="13" />
    </button>

    <button
        v-if="canEdit || description"
        ref="noteBtnRef"
        :class="['table_button', 'table_button--note', { 'table_button--has-note': description }]"
        @mousedown.stop
        @click.stop.prevent="showNote = !showNote"
        :title="description || 'Add table description'"
    >
        <SvgIcon name="note" :size="13" />
    </button>

    <button
        v-if="dbType === 'ontology' && !isReference && (canEdit || hasOntologySettings)"
        ref="settingsBtnRef"
        :class="['table_button', 'table_button--settings', { 'table_button--has-actions': hasOntologySettings }]"
        @mousedown.stop
        @click.stop.prevent="showSettings = !showSettings"
        :title="hasOntologySettings ? 'Ontology settings configured' : 'Ontology settings'"
    >
        <SvgIcon name="gear" :size="13" />
    </button>

    <button v-if="canEdit" class="table_button" @mousedown.stop @click="$emit('delete-node', id)">
        <SvgIcon name="trash" :size="13" />
    </button>

    <NodeNoteModal
        v-if="showNote"
        title="Table description"
        :note="description"
        :canEdit="canEdit"
        :anchor="noteBtnRef"
        :ignore="[noteBtnRef]"
        @save="$emit('update-note', id, $event)"
        @close="showNote = false"
    />

    <TableSettingsModal
        v-if="showSettings"
        :actions="ontologyActions"
        :titlePropertyRowId="titlePropertyRowId"
        :columns="columns"
        :interfaces="interfaces"
        :implementsInterfaces="implementsInterfaces"
        :editsHistory="editsHistory"
        :canEdit="canEdit"
        :anchor="settingsBtnRef"
        :ignore="[settingsBtnRef]"
        @change="$emit('update-actions', id, $event)"
        @close="showSettings = false"
    />

    <template v-if="canEdit">
        <div class="table_resize_handle table_resize_handle--left" @mousedown.stop="$emit('resize-start', id, $event, 'left')"></div>
        <div class="table_resize_handle table_resize_handle--right" @mousedown.stop="$emit('resize-start', id, $event, 'right')"></div>
    </template>
</template>

<script setup>
import { computed, ref } from 'vue'
import SvgIcon from '../SvgIcon.vue'
import NodeNoteModal from '../NodeNoteModal.vue'
import TableSettingsModal from '../TableSettingsModal.vue'

const props = defineProps({
    id: String,
    data: Object,
    label: String,
    dbType: { type: String, default: 'mysql' },
    columns: { type: Array, default: () => [] },
    interfaces: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: true },
})

defineEmits(['delete-node', 'update-label', 'copy-table', 'add-row', 'resize-start', 'update-color', 'update-note', 'update-actions'])

const showNote = ref(false)
const showSettings = ref(false)
const noteBtnRef = ref(null)
const settingsBtnRef = ref(null)
const ontologyActions = computed(() => props.data?.ontologyActions ?? {})
const titlePropertyRowId = computed(() => props.data?.titlePropertyRowId ?? '')
const implementsInterfaces = computed(() => props.data?.implementsInterfaces ?? [])
const editsHistory = computed(() => props.data?.editsHistory ?? {})
const isReference = computed(() => props.data?.reference || props.data?.tableKind === 'reference')
const hasOntologyActions = computed(() => !!(ontologyActions.value.create || ontologyActions.value.modify || ontologyActions.value.delete))
const hasOntologySettings = computed(() => hasOntologyActions.value || !!titlePropertyRowId.value || implementsInterfaces.value.length > 0 || !!editsHistory.value.enabled)
const description = computed(() => props.data?.description ?? props.data?.note ?? '')
</script>

<style scoped>
.input_designer_table {
    text-align: center;
    color: white;
    flex-grow: 0;
    width: 30%;
}

.input_designer_table--reference {
    color: #ddd6fe;
    font-weight: 800;
    letter-spacing: 0.01em;
}

.reference_table_badge {
    align-self: center;
    padding: 2px 6px;
    border: 1px solid rgba(221, 214, 254, 0.42);
    border-radius: 999px;
    color: #ddd6fe;
    background: rgba(139, 92, 246, 0.18);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
}
</style>
