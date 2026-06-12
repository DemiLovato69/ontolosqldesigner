<template>
    <input
        class="input input_designer_table"
        :value="label"
        @mousedown.stop
        @click="canEdit && (data.editing = true)"
        @blur="(e) => { data.editing = false; $emit('update-label', id, label); e.target.scrollLeft = 0; }"
        @input="$emit('update-label', id, $event.target.value)"
        :readonly="!data.editing || !canEdit"
    />

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
        v-if="dbType === 'ontology' && (canEdit || hasOntologySettings)"
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
    canEdit: { type: Boolean, default: true },
})

defineEmits(['delete-node', 'update-label', 'copy-table', 'add-row', 'resize-start', 'update-color', 'update-note', 'update-actions'])

const showNote = ref(false)
const showSettings = ref(false)
const noteBtnRef = ref(null)
const settingsBtnRef = ref(null)
const ontologyActions = computed(() => props.data?.ontologyActions ?? {})
const titlePropertyRowId = computed(() => props.data?.titlePropertyRowId ?? '')
const hasOntologyActions = computed(() => !!(ontologyActions.value.create || ontologyActions.value.modify || ontologyActions.value.delete))
const hasOntologySettings = computed(() => hasOntologyActions.value || !!titlePropertyRowId.value)
const description = computed(() => props.data?.description ?? props.data?.note ?? '')
</script>

<style scoped>
.input_designer_table {
    text-align: center;
    color: white;
    flex-grow: 0;
    width: 30%;
}
</style>
