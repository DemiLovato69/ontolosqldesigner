<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal-card reference-json-modal">
            <div class="modal-header">
                <span class="modal-title">Import Reference Schema</span>
                <button class="modal-close" type="button" @click="$emit('close')" aria-label="Close">
                    <SvgIcon name="close" :size="16" />
                </button>
            </div>

            <div class="reference-json-modal__body">
                <p class="reference-json-modal__hint">
                    Paste a JSON Schema draft-07 object schema or a Polars <code>pl.Schema({{ '{' }}...{{ '}' }})</code> expression, or upload a file.
                    For JSON Schema the schema title is used when no name is provided.
                </p>
                <label class="reference-json-modal__field">
                    <span class="reference-json-modal__field-label">Reference table name</span>
                    <input
                        v-model="tableName"
                        type="text"
                        class="reference-json-modal__input"
                        placeholder="Optional (e.g. WorkspaceReference)"
                    />
                </label>
                <textarea
                    v-model="content"
                    class="reference-json-modal__textarea"
                    placeholder="pl.Schema({
    'workspaceId': pl.Int64(),
    'workspaceName': pl.String(),
    'isProduction': pl.Boolean(),
})"
                ></textarea>
                <div v-if="selectedFileName" class="reference-json-modal__file-name">
                    {{ selectedFileName }}
                </div>
            </div>

            <div class="modal-footer">
                <label class="btn btn-secondary reference-json-modal__upload" title="Upload schema file">
                    <SvgIcon name="import" :size="15" />
                    <span>Upload File</span>
                    <input type="file" accept="application/json,.json,.py,.txt" hidden @change="onFileChange" />
                </label>
                <button class="btn btn-primary" type="button" :disabled="!content.trim()" @click="importJson">
                    Import
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useToast } from 'vue-toast-notification'
import SvgIcon from '../SvgIcon.vue'

const emit = defineEmits(['import', 'close'])
const $toast = useToast()
const content = ref('')
const tableName = ref('')
const selectedFileName = ref('')

const onFileChange = async (event) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    if (file.size > 10 * 1024 * 1024) {
        $toast.error('Reference schema files must be 10MB or smaller')
        return
    }
    selectedFileName.value = file.name
    content.value = await file.text()
}

const importJson = () => {
    emit('import', { content: content.value, title: tableName.value.trim() })
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

.reference-json-modal {
    width: 700px;
    max-width: calc(100vw - 2rem);
}

.modal-header,
.modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-color);
}

.modal-footer {
    border-top: 1px solid var(--border-color);
    border-bottom: 0;
}

.modal-title {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 700;
}

.modal-close {
    width: 28px;
    height: 28px;
    border: 0;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
}

.reference-json-modal__body {
    padding: 16px 18px;
}

.reference-json-modal__hint {
    margin: 0 0 10px;
    color: var(--text-secondary);
    font-size: 0.82rem;
    line-height: 1.45;
}

.reference-json-modal__hint code {
    font-family: Consolas, Monaco, monospace;
    font-size: 0.78rem;
    color: var(--text-primary);
}

.reference-json-modal__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}

.reference-json-modal__field-label {
    color: var(--text-secondary);
    font-size: 0.78rem;
    font-weight: 600;
}

.reference-json-modal__input {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 0.85rem;
}

.reference-json-modal__textarea {
    width: 100%;
    min-height: 320px;
    resize: vertical;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-family: Consolas, Monaco, monospace;
    font-size: 0.82rem;
}

.reference-json-modal__file-name {
    margin-top: 8px;
    color: var(--text-muted);
    font-size: 0.76rem;
}

.reference-json-modal__upload {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
</style>
