<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal-card sql-modal">
            <div class="modal-header">
                <span class="modal-title">{{ primaryLabel }} {{ primaryLabel === 'Import' ? 'Diagram' : 'SQL' }}</span>
                <button class="modal-close" @click="$emit('close')" aria-label="Close">
                    <SvgIcon name="close" :size="16" />
                </button>
            </div>
            <div v-if="primaryLabel === 'Import'" class="import-types">
                <button
                    v-for="option in importOptions"
                    :key="option.value"
                    type="button"
                    :class="['import-type', { 'import-type--active': importType === option.value }]"
                    @click="selectImportType(option.value)"
                >
                    <strong>{{ option.label }}</strong>
                    <span>{{ option.description }}</span>
                </button>
            </div>
            <div class="sql-textarea-wrap">
                <textarea
                    class="sql-textarea"
                    :value="modelValue"
                    @input="handleTextInput"
                    :placeholder="primaryLabel === 'Import' ? activeImportOption.placeholder : ''"
                ></textarea>
                <button v-if="primaryLabel === 'Export'" class="sql-copy-btn" @click="copyText" title="Copy all">
                    <SvgIcon name="copy" :size="14" />
                </button>
            </div>
            <div v-if="primaryLabel === 'Import' && selectedImportFile" class="selected-import-file">
                <span>{{ selectedImportFile.name }}</span>
                <small>{{ uploadStatusText }}</small>
                <button type="button" @click="clearSelectedFile" :disabled="loading">Remove</button>
            </div>
            <div class="modal-footer">
                <div v-if="primaryLabel === 'Export'" class="download-dropdown">
                    <button class="btn btn-secondary download-dropdown__trigger">
                        <SvgIcon name="download" :size="15" />
                    </button>
                    <div class="download-dropdown__menu">
                        <button @click="downloadSql('sql')">.sql</button>
                        <button @click="downloadJson">.json</button>
                    </div>
                </div>
                <div v-if="primaryLabel === 'Import'" class="import-footer">
                    <label class="btn btn-secondary import-file-btn" title="Upload from file">
                        <SvgIcon name="import" :size="15" />
                        <input type="file" :accept="activeImportOption.accept" @change="handleFileUpload" hidden>
                    </label>
                    <button class="btn btn-primary" @click="$emit('primary-action')" :disabled="loading">
                        {{ loading ? 'Importing…' : primaryLabel }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useToast } from 'vue-toast-notification'
import SvgIcon from '../SvgIcon.vue'

const $toast = useToast()

const props = defineProps({
    modelValue: String,
    primaryLabel: String,
    filename: { type: String, default: 'schema' },
    jsonContent: { type: String, default: null },
    loading: { type: Boolean, default: false },
    importType: { type: String, default: 'sql' },
    selectedImportFile: { type: Object, default: null },
    uploadProgress: { type: Number, default: 0 },
    uploadPhase: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'update:importType', 'import-file', 'primary-action', 'close'])

const importOptions = [
    {
        value: 'sql',
        label: 'SQL',
        description: 'Database DDL',
        accept: '.sql,.txt',
        placeholder: 'Paste SQL CREATE TABLE definitions here…',
    },
    {
        value: 'ontology-json',
        label: 'Ontology JSON',
        description: 'Foundry export',
        accept: '.json',
        placeholder: 'Paste an exported Foundry ontology JSON document here…',
    },
    {
        value: 'backup-json',
        label: 'Backup JSON',
        description: 'Exact diagram backup',
        accept: '.json',
        placeholder: 'Paste an OntoloSQL Designer backup JSON document here…',
    },
    {
        value: 'maker-mts',
        label: 'Maker .mts',
        description: '@osdk/maker definitions',
        accept: '.mts,.ts',
        placeholder: 'Paste declarative @osdk/maker definitions here…',
    },
]

const activeImportOption = computed(() =>
    importOptions.find(option => option.value === props.importType) ?? importOptions[0]
)

const uploadStatusText = computed(() => {
    if (props.uploadPhase) {
        return props.uploadProgress > 0
            ? `${props.uploadPhase} (${props.uploadProgress}%)`
            : props.uploadPhase
    }

    return props.uploadProgress > 0
        ? `${props.uploadProgress}% uploaded`
        : formatBytes(props.selectedImportFile?.size ?? 0)
})

const selectImportType = (type) => {
    emit('update:importType', type)
    emit('update:modelValue', '')
    emit('import-file', null)
}

const handleTextInput = (event) => {
    emit('import-file', null)
    emit('update:modelValue', event.target.value)
}

const clearSelectedFile = () => {
    emit('import-file', null)
}

const copyText = async () => {
    await navigator.clipboard.writeText(props.modelValue)
    $toast.success('Text copied')
}

const downloadSql = (ext) => {
    const blob = new Blob([props.modelValue], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${props.filename}.${ext}`
    a.click()
    URL.revokeObjectURL(url)
}

const downloadJson = () => {
    const blob = new Blob([props.jsonContent], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${safeFilename(props.filename)}.json`
    a.click()
    URL.revokeObjectURL(url)
}

const safeFilename = (name) => {
    const cleaned = String(name || 'schema')
        .trim()
        .replace(/[\\/:*?"<>|\x00-\x1F]/g, '_')
        .replace(/\s+/g, '_')
        .replace(/_+/g, '_')
        .slice(0, 120)

    return cleaned || 'schema'
}

const formatBytes = (bytes) => {
    if (bytes < 1024) return `${bytes} B`
    const units = ['KB', 'MB', 'GB']
    let value = bytes / 1024
    let unitIndex = 0
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024
        unitIndex++
    }
    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unitIndex]}`
}

const handleFileUpload = (event) => {
    const file = event.target.files[0]
    if (!file) return
    event.target.value = ''
    if (file.size > 2 * 1024 * 1024 * 1024) {
        $toast.error('Import files must be 2GB or smaller')
        return
    }
    emit('update:modelValue', '')
    emit('import-file', file)
}
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 200;
}

.modal-card {
    background: var(--bg-surface);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.sql-modal {
    width: 700px;
    max-width: calc(100vw - 2rem);
}

.import-types {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--border-color);
}

.import-type {
    display: grid;
    gap: 3px;
    padding: 9px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    background: var(--bg-surface-alt);
    text-align: left;
    cursor: pointer;
}

.import-type span {
    color: var(--text-muted);
    font-size: 10px;
}

.import-type--active {
    border-color: var(--color-primary);
    box-shadow: inset 0 0 0 1px var(--color-primary);
}

@media (max-width: 700px) {
    .import-types {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
}

.modal-title {
    font-size: 0.76rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    padding: 4px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    transition: color 120ms, background 120ms;
}

.modal-close:hover {
    color: var(--text-primary);
    background: var(--hover-bg);
}

.sql-textarea-wrap {
    position: relative;
    flex: 1;
    display: flex;
}

.sql-textarea {
    flex: 1;
    width: 100%;
    min-width: 0;
    min-height: 380px;
    padding: 16px;
    border: none;
    border-bottom: 1px solid var(--border-light);
    resize: none;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 0.82rem;
    line-height: 1.7;
    color: var(--text-primary);
    background: var(--bg-surface-alt);
    box-sizing: border-box;
    outline: none;
}

.sql-textarea:focus {
    background: var(--bg-surface);
}

.sql-copy-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 30px;
    height: 30px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-surface);
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 120ms, background 120ms;
}

.sql-copy-btn:hover {
    color: var(--text-primary);
    background: var(--hover-bg);
}

.modal-footer {
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-surface);
    border-top: 1px solid var(--border-light);
    flex-shrink: 0;
}

.selected-import-file {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-top: 1px solid var(--border-color);
    color: var(--text-primary);
    font-size: 13px;
}

.selected-import-file span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selected-import-file small {
    color: var(--text-secondary);
    margin-left: auto;
    white-space: nowrap;
}

.selected-import-file button {
    background: none;
    border: 0;
    color: var(--accent-color);
    cursor: pointer;
    font-size: 12px;
}

.import-footer {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-left: auto;
}

.import-file-btn {
    cursor: pointer;
}

/* Download dropdown */
.download-dropdown {
    position: relative;
    display: flex;
    align-items: stretch;
}

.download-dropdown__menu {
    display: none;
    position: absolute;
    left: 100%;
    top: 0;
    bottom: 0;
    background: var(--bg-surface-alt);
    border: 1px solid var(--border-color);
    border-left: none;
    border-radius: 0 7px 7px 0;
    overflow: hidden;
    flex-direction: row;
}

.download-dropdown:hover .download-dropdown__menu {
    display: flex;
}

.download-dropdown__trigger {
    border-radius: 7px 0 0 7px !important;
}

.download-dropdown__menu button {
    display: flex;
    align-items: center;
    height: 100%;
    padding: 0 14px;
    border: none;
    border-left: 1px solid var(--border-color);
    background: var(--bg-surface-alt);
    color: var(--text-secondary);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 120ms;
}

.download-dropdown__menu button:hover {
    background: var(--hover-bg-alt);
}
</style>
