<template>
    <div class="modal flex-centered" @click.self="$emit('close')">
        <div class="export-modal">
            <div class="export-modal__header">
                <span class="export-modal__title">Export</span>
                <button class="export-modal__close" @click="$emit('close')">
                    <img src="../../icons/close.svg" alt="Close">
                </button>
            </div>
            <div v-if="isExporting" class="export-modal__status">
                <span class="export-modal__status-spinner"></span>
                <span class="export-modal__status-text">Export in progress…</span>
            </div>

            <div class="export-modal__body">
                <button class="export-card" :class="{ 'export-card--active': activeExport === 'copy' }" :disabled="isExporting" @click="copyText">
                    <div class="export-card__icon">
                        <svg v-if="activeExport !== 'copy'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="6" y="10" width="26" height="32" rx="3" fill="var(--bg-surface-alt)" stroke="var(--border-color)" stroke-width="2"/>
                            <rect x="16" y="6" width="26" height="32" rx="3" fill="var(--bg-surface)" stroke="var(--color-primary)" stroke-width="2"/>
                            <line x1="22" y1="16" x2="36" y2="16" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round"/>
                            <line x1="22" y1="22" x2="36" y2="22" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round"/>
                            <line x1="22" y1="28" x2="30" y2="28" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span v-else class="export-spinner"></span>
                    </div>
                    <span class="export-card__label">{{ isOntology ? 'Copy Ontology' : 'Copy SQL' }}</span>
                    <span class="export-card__desc">{{ activeExport === 'copy' ? 'Copying…' : 'Copy to clipboard' }}</span>
                </button>

                <button class="export-card" :class="{ 'export-card--active': activeExport === 'sql' }" :disabled="isExporting" @click="downloadScript">
                    <div class="export-card__icon">
                        <svg v-if="activeExport !== 'sql'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 6h20l10 10v26a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z" fill="var(--bg-surface-alt)" stroke="var(--color-primary)" stroke-width="2"/>
                            <path d="M30 6v10h10" stroke="var(--color-primary)" stroke-width="2" stroke-linejoin="round"/>
                            <text x="24" y="35" text-anchor="middle" font-size="10" font-weight="700" font-family="monospace" fill="var(--color-primary)">{{ isOntology ? '.mts' : '.sql' }}</text>
                        </svg>
                        <span v-else class="export-spinner"></span>
                    </div>
                    <span class="export-card__label">{{ isOntology ? '.mts' : '.sql' }}</span>
                    <span class="export-card__desc">{{ activeExport === 'sql' ? 'Downloading…' : (isOntology ? 'Foundry Maker module' : 'SQL script file') }}</span>
                </button>

                <button class="export-card" :class="{ 'export-card--active': activeExport === 'json' }" :disabled="isExporting" @click="downloadJson">
                    <div class="export-card__icon">
                        <svg v-if="activeExport !== 'json'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 6h20l10 10v26a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z" fill="var(--bg-surface-alt)" stroke="var(--border-color)" stroke-width="2"/>
                            <path d="M30 6v10h10" stroke="var(--border-color)" stroke-width="2" stroke-linejoin="round"/>
                            <text x="24" y="35" text-anchor="middle" font-size="9" font-weight="700" font-family="monospace" fill="var(--text-secondary)">.json</text>
                        </svg>
                        <span v-else class="export-spinner"></span>
                    </div>
                    <span class="export-card__label">.json</span>
                    <span class="export-card__desc">{{ activeExport === 'json' ? 'Downloading…' : 'Diagram JSON backup' }}</span>
                </button>

                <button v-if="!isOntology" class="export-card" :class="{ 'export-card--active': activeExport === 'ontology' }" :disabled="isExporting" @click="downloadOntology">
                    <div class="export-card__icon">
                        <svg v-if="activeExport !== 'ontology'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 6h20l10 10v26a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z" fill="var(--bg-surface-alt)" stroke="var(--color-primary)" stroke-width="2"/>
                            <path d="M30 6v10h10" stroke="var(--color-primary)" stroke-width="2" stroke-linejoin="round"/>
                            <circle cx="17" cy="27" r="3" fill="var(--color-primary)"/>
                            <circle cx="31" cy="22" r="3" fill="var(--color-primary)"/>
                            <circle cx="31" cy="34" r="3" fill="var(--color-primary)"/>
                            <path d="M20 26l8-3M20 28l8 5" stroke="var(--color-primary)" stroke-width="2"/>
                        </svg>
                        <span v-else class="export-spinner"></span>
                    </div>
                    <span class="export-card__label">Ontology</span>
                    <span class="export-card__desc">{{ activeExport === 'ontology' ? 'Generating…' : 'Foundry Maker (.mts)' }}</span>
                </button>

                <button class="export-card export-card--png" :class="{ 'export-card--active': activeExport === 'png' }" :disabled="isExporting" @click="capturePng">
                    <div class="export-card__icon">
                        <svg v-if="activeExport !== 'png'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="4" y="8" width="40" height="32" rx="4" fill="var(--bg-surface-alt)" stroke="var(--color-primary)" stroke-width="2"/>
                            <circle cx="16" cy="19" r="4" fill="var(--color-primary)" opacity="0.7"/>
                            <path d="M4 34l10-10 8 8 6-6 16 12" stroke="var(--color-primary)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                            <text x="24" y="44" text-anchor="middle" font-size="9" font-weight="700" font-family="monospace" fill="var(--color-primary)">.png</text>
                        </svg>
                        <span v-else class="export-spinner"></span>
                    </div>
                    <span class="export-card__label">.png</span>
                    <span class="export-card__desc">{{ activeExport === 'png' ? 'Rendering…' : 'Full diagram image' }}</span>
                </button>

                <button class="export-card export-card--svg" :class="{ 'export-card--active': activeExport === 'svg' }" :disabled="isExporting" @click="captureSvg">
                    <div class="export-card__icon">
                        <svg v-if="activeExport !== 'svg'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 6h20l10 10v26a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z" fill="var(--bg-surface-alt)" stroke="#a855f7" stroke-width="2"/>
                            <path d="M30 6v10h10M14 31l7-7 5 5 7-9" stroke="#a855f7" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                            <circle cx="14" cy="31" r="2" fill="#a855f7"/>
                            <circle cx="21" cy="24" r="2" fill="#a855f7"/>
                            <circle cx="26" cy="29" r="2" fill="#a855f7"/>
                            <circle cx="33" cy="20" r="2" fill="#a855f7"/>
                            <text x="24" y="40" text-anchor="middle" font-size="8" font-weight="700" font-family="monospace" fill="#a855f7">.svg</text>
                        </svg>
                        <span v-else class="export-spinner"></span>
                    </div>
                    <span class="export-card__label">.svg</span>
                    <span class="export-card__desc">{{ activeExport === 'svg' ? 'Rendering…' : 'Scalable full diagram' }}</span>
                </button>

            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useToast } from 'vue-toast-notification'
import { Diagram } from '@/services/Diagram.js'

const $toast = useToast()

const props = defineProps({
    filename:  { type: String, default: 'schema' },
    diagramId: { type: Number, default: null },
    dbType:    { type: String, default: 'mysql' },
})

const isOntology = computed(() => props.dbType === 'ontology')
const isExporting  = ref(false)
const activeExport = ref(null)
const sqlCache     = ref(null)

const withExporting = async (key, fn) => {
    isExporting.value  = true
    activeExport.value = key
    try {
        await fn()
    } catch (e) {
        $toast.error(e?.message || 'Export failed')
        console.error(e)
    } finally {
        isExporting.value  = false
        activeExport.value = null
    }
}

const generateSql = () => new Promise((resolve, reject) => {
    if (sqlCache.value) { resolve(sqlCache.value); return }
    if (isOntology.value) {
        Diagram.exportOntology(props.diagramId)
            .then(blob => blob.text())
            .then(module => {
                sqlCache.value = module
                resolve(module)
            })
            .catch(reject)
        return
    }
    Diagram.export(props.diagramId).then(result => {
        if (!result) { reject(new Error('Export failed')); return }
        if (result.status === 'done' && result.script) {
            sqlCache.value = result.script
            resolve(sqlCache.value)
            return
        }
        let attempts = 0
        const poll = setInterval(async () => {
            attempts++
            if (attempts > 150) {
                clearInterval(poll)
                reject(new Error('Export timed out'))
                return
            }
            const status = await Diagram.exportStatus(props.diagramId)
            if (!status) return
            if (status.status === 'done') {
                clearInterval(poll)
                sqlCache.value = status.script
                resolve(sqlCache.value)
            } else if (status.status === 'failed') {
                clearInterval(poll)
                reject(new Error(status.error || 'Export failed'))
            }
        }, 2000)
    }).catch(reject)
})

const copyText = () => withExporting('copy', async () => {
    const sql = await generateSql()
    await navigator.clipboard.writeText(sql)
    $toast.success('Copied to clipboard')
})

const downloadScript = () => withExporting('sql', async () => {
    const script = await generateSql()
    const extension = isOntology.value ? 'mts' : 'sql'
    const blob = new Blob([script], { type: isOntology.value ? 'text/typescript' : 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${props.filename}.${extension}`
    a.click()
    URL.revokeObjectURL(url)
})

const downloadJson = () => withExporting('json', async () => {
    const json = await Diagram.exportJson(props.diagramId)
    if (!json) { $toast.error('Failed to export JSON'); return }
    const blob = new Blob([json], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${props.filename}.json`
    a.click()
    URL.revokeObjectURL(url)
})

const emit = defineEmits(['close', 'capture-png', 'capture-svg'])

const capturePng = () => withExporting('png', async () => {
    emit('capture-png')
})

const captureSvg = () => withExporting('svg', async () => {
    emit('capture-svg')
})

const downloadOntology = () => withExporting('ontology', async () => {
    const blob = await Diagram.exportOntology(props.diagramId)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${props.filename}.mts`
    a.click()
    URL.revokeObjectURL(url)
})
</script>

<style scoped>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.export-modal {
    background: var(--bg-surface);
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    width: 660px;
    max-width: calc(100vw - 2rem);
    overflow: hidden;
}

.export-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: var(--color-primary);
}

.export-modal__title {
    font-size: 14px;
    font-weight: 600;
    color: white;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}

.export-modal__close {
    width: 26px;
    height: 26px;
    padding: 4px;
    border: none;
    background: rgba(255, 255, 255, 0.15);
    cursor: pointer;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.15s;
}

.export-modal__close:hover {
    background-color: rgba(255, 255, 255, 0.3);
}

.export-modal__close img {
    width: 14px;
    height: 14px;
    filter: brightness(0) invert(1);
}

.export-modal__status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 24px;
    background: var(--bg-surface-alt);
    border-bottom: 1px solid var(--border-color);
}

.export-modal__status-text {
    font-size: 11px;
    color: var(--text-secondary);
}

.export-modal__status-spinner {
    display: block;
    width: 12px;
    height: 12px;
    border: 1.5px solid var(--border-color);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    flex-shrink: 0;
}

.export-modal__body {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    padding: 24px;
}

.export-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px 8px 16px;
    background: var(--bg-surface-alt);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, transform 0.1s;
    text-align: center;
}

.export-card:hover {
    border-color: var(--color-primary);
    background: var(--bg-surface);
    transform: translateY(-2px);
}

.export-card--png:hover {
    border-color: var(--color-primary);
}

.export-card--svg:hover {
    border-color: #a855f7;
}

.export-card__icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.export-card__icon svg {
    width: 100%;
    height: 100%;
}

.export-card__label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-primary);
    font-family: 'Consolas', 'Monaco', monospace;
}

.export-card__desc {
    font-size: 10px;
    color: var(--text-secondary);
    line-height: 1.3;
}

.export-card:disabled {
    cursor: not-allowed;
    transform: none;
    opacity: 0.45;
}

.export-card:disabled:hover {
    border-color: var(--border-color);
    background: var(--bg-surface-alt);
    transform: none;
}

.export-card--active,
.export-card--active:disabled {
    opacity: 1;
    border-color: var(--color-primary);
    background: var(--bg-surface);
}

.export-spinner {
    display: block;
    width: 28px;
    height: 28px;
    border: 2.5px solid var(--border-color);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

</style>
